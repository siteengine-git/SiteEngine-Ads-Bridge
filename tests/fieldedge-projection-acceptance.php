<?php
/** Local synthetic contact store, not installed FluentCRM verification. */
require_once __DIR__ . '/../includes/connectors/fieldedge/CRMProjection.php';
require_once __DIR__ . '/support/fieldedge/ProjectionFixture.php';
use SiteEngine\AdsBridge\Connectors\FieldEdge\CRMProjection as P;
use SiteEngine\AdsBridge\Connectors\FieldEdge\ProjectionContacts;
use SiteEngine\AdsBridge\Tests\FieldEdge\ProjectionFixture as F;
final class ContactsFixture implements ProjectionContacts {
	public array $rows = array(); public int $writes = 0; public bool $busy = false; public bool $uncertain = false;
	public function locked( callable $run ): array { return $this->busy ? array( 'status' => 'held', 'reason' => 'projection_busy' ) : $run(); }
	public function candidates( string $identity, string $email, string $job_id ): array {
		$r = array( 'identity' => array(), 'email' => array(), 'job' => array() ); $tenant = json_decode( $identity, true )['tenant_id'];
		foreach ( $this->rows as $id => $c ) {
			if ( ( $c['fields'][P::IDENTITY] ?? null ) === $identity ) { $r['identity'][] = $id; }
			if ( strtolower( $c['email'] ) === $email ) { $r['email'][] = $id; }
			$i = json_decode( $c['fields'][P::IDENTITY] ?? '{}', true ); $jobs = json_decode( $c['fields'][P::JOBS] ?? '{}', true );
			if ( ( $i['tenant_id'] ?? null ) === $tenant && isset( $jobs[$job_id] ) ) { $r['job'][] = $id; }
		} return $r;
	}
	public function create( string $email, array $fields ): int {
		$id = count( $this->rows ) + 1; $this->rows[$id] = array( 'email' => $email, 'status' => 'unsubscribed', 'DNC' => true, 'tags' => array(), 'lists' => array(), 'fields' => array() );
		$this->updateFields( $id, $fields ); return $id;
	}
	public function fields( int $id ): array { return $this->rows[$id]['fields']; }
	public function updateFields( int $id, array $fields ): void { ++$this->writes; $this->rows[$id]['fields'] = array_replace( $this->rows[$id]['fields'], $fields ); if ( $this->uncertain ) { throw new RuntimeException(); } }
}
$pass = 0;
function check( bool $value, string $label ): void { global $pass; if ( ! $value ) { throw new RuntimeException( 'FAIL: ' . $label ); } ++$pass; echo "PASS: $label\n"; }
$s = new ContactsFixture(); $input = F::input();
$r = P::run( $input, $s ); check( $r['status'] === 'created', 'new synthetic contact created' );
check( P::run( $input, $s )['operation_id'] === $r['operation_id'] && P::run( $input, $s )['snapshot_sha256'] === $r['snapshot_sha256'], 'stable operation and exact snapshot evidence on replay' );
check( count( $s->rows ) === 1 && $r['job_count'] === 1, 'one contact and one job summary' );
$identity = json_decode( $s->rows[1]['fields'][P::IDENTITY], true );
check( $identity === array( 'provider' => 'fieldedge', 'tenant_id' => $input['tenant_id'], 'customer_id' => $input['customer']['id'] ), 'provider tenant customer identity retained' );
$s->rows[1]['fields']['unrelated'] = 'keep'; $s->rows[1]['status'] = 'complained'; $s->rows[1]['tags'] = array( 9 ); $s->rows[1]['lists'] = array( 7 );
$before = $s->rows; $writes = $s->writes;
check( P::run( $input, $s )['status'] === 'replayed', 'exact replay recognized' );
check( $s->rows === $before && $s->writes === $writes, 'replay performs zero writes and no duplicates' );
$updated = $input; $updated['job']['status'] = 'completed'; $updated['job']['completed_at'] = '2026-11-12T15:00:00Z'; $updated['job']['source_updated_at'] = '2026-11-12T15:05:00Z';
check( P::run( $updated, $s )['status'] === 'updated', 'newer job summary updates' );
check( $s->rows[1]['status'] === 'complained' && $s->rows[1]['DNC'] && $s->rows[1]['tags'] === array(9) && $s->rows[1]['lists'] === array(7) && $s->rows[1]['fields']['unrelated'] === 'keep', 'suppression DNC tags lists and unrelated custom field preserved' );
check( count( json_decode( $s->rows[1]['fields'][P::JOBS], true ) ) === 1, 'update replaces same job key without appending duplicate' );
check( P::run( $updated, $s )['status'] === 'replayed', 'updated snapshot replays exactly' );
$second = $updated; $second['job']['id'] = 'synthetic-job-2';
check( P::run( $second, $s )['job_count'] === 2 && count( $s->rows ) === 1, 'distinct provider job shares the same customer contact' );
check( P::run( $second, $s )['status'] === 'replayed', 'second job exact replay retains two unique summaries' );
$phone = $updated; unset( $phone['customer']['email'] ); $before_phone = $s->rows; $phone_writes = $s->writes;
check( P::run( $phone, $s )['status'] === 'replayed' && $s->rows === $before_phone && $s->writes === $phone_writes, 'verified customer binding permits missing source email without changing CRM email' );
$phone_new = new ContactsFixture();
check( P::run( $phone, $phone_new )['reason'] === 'missing_email_contact_creation_unsupported' && $phone_new->rows === array() && $phone_new->writes === 0, 'phone-only new contact held without fabricated email or mutation' );
check( P::run( $input, $s )['reason'] === 'stale_or_conflicting_source_snapshot', 'older snapshot held' );
$conflict = $updated; $conflict['job']['status'] = 'canceled';
check( P::run( $conflict, $s )['reason'] === 'stale_or_conflicting_source_snapshot', 'same revision conflicting content held' );
$foreign = $updated; $foreign['customer']['id'] = $foreign['job']['customer_id'] = 'synthetic-foreign'; $foreign['customer']['email'] = 'other@example.invalid';
check( P::run( $foreign, $s )['reason'] === 'provider_identity_conflict', 'same tenant job cannot move to foreign customer' );
$s->rows[2] = $s->rows[1];
$snapshot = $s->rows; check( P::run( $updated, $s )['reason'] === 'ambiguous_identity' && $s->rows === $snapshot, 'ambiguous duplicate bindings held without writes' );
unset( $s->rows[2] );
$s->busy = true; check( P::run( $updated, $s )['reason'] === 'projection_busy', 'concurrent executor held' ); $s->busy = false;
$tenant = $updated; $tenant['tenant_id'] = 'synthetic-other-tenant';
check( P::run( $tenant, $s )['reason'] === 'provider_identity_conflict', 'same email cannot cross tenant ownership' );
$s->rows[1]['fields'][P::JOBS] = P::json( array( 'foreign-key' => array( 'job_id' => 'foreign-key', 'customer_id' => 'wrong' ) ) );
check( P::run( $updated, $s )['reason'] === 'existing_summary_invalid', 'foreign customer in existing job map cannot be overwritten' );
$s->rows[1]['fields'][P::JOBS] = P::json( array() );
$capacity = array(); for ( $i = 0; $i < 20; ++$i ) { $capacity['prior-job-' . $i] = array( 'job_id' => 'prior-job-' . $i, 'customer_id' => $input['customer']['id'] ); }
$s->rows[1]['fields'][P::JOBS] = P::json( $capacity );
check( P::run( $updated, $s )['reason'] === 'bounded_job_summary_capacity', 'twenty-job ceiling holds without deleting retained jobs' );
$unbound = new ContactsFixture(); $unbound->rows[1] = array( 'email' => $input['customer']['email'], 'status' => 'subscribed', 'DNC' => true, 'fields' => array( 'unrelated' => 'keep' ) );
check( P::run( $input, $unbound )['reason'] === 'email_match_requires_explicit_contact_binding', 'unique unbound email alone does not silently bind' );
$approved = $input; $approved['approved_contact_id'] = 1;
check( P::run( $approved, $unbound )['status'] === 'updated' && $unbound->rows[1]['status'] === 'subscribed', 'explicit synthetic contact binding preserves subscribed status' );
$bad = $input; $bad['approved_contact_id'] = 99;
check( P::run( $bad, $s )['reason'] === 'approved_contact_mismatch', 'foreign approved contact refused' );
$s->rows[1]['fields'][P::JOBS] = '{broken';
check( P::run( $updated, $s )['reason'] === 'existing_summary_invalid', 'corrupt summary held without reset' );
$uncertain = new ContactsFixture(); $uncertain->uncertain = true;
check( P::run( $input, $uncertain )['reason'] === 'write_result_uncertain_inspect_before_retry' && count( $uncertain->rows ) === 1, 'uncertain write held without second insert' );
$uncertain->uncertain = false;
check( P::run( $input, $uncertain )['status'] === 'replayed' && count( $uncertain->rows ) === 1, 'independent retained read reconciles exact persisted result' );
foreach ( array( 'synthetic', 'customer', 'job' ) as $case ) {
	$bad = $input;
	if ( $case === 'synthetic' ) { $bad['synthetic'] = false; }
	if ( $case === 'customer' ) { $bad['customer']['email'] = 'real@example.com'; }
	if ( $case === 'job' ) { $bad['job']['customer_id'] = 'wrong'; }
	try { P::run( $bad, new ContactsFixture() ); check( false, 'invalid input refused' ); } catch ( InvalidArgumentException $e ) { check( true, $case . ' production/mismatch input refused' ); }
}
echo "RESULT: $pass PASS, 0 FAIL; local synthetic fixtures only; no WordPress/provider requests.\n";
