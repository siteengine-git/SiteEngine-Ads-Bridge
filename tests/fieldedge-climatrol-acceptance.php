<?php
/** Local fixtures; installed WordPress verification is separately owned by AB. */
require_once __DIR__ . '/support/fieldedge/ClimatrolFixture.php';
require_once __DIR__ . '/support/fieldedge/SyntheticOutcomeApi.php';
require_once __DIR__ . '/../includes/connectors/fieldedge/SubmissionAdapter.php';

use SiteEngine\AdsBridge\Connectors\FieldEdge\Foundation as F;
use SiteEngine\AdsBridge\Connectors\FieldEdge\ClimatrolWorkflow as W;
use SiteEngine\AdsBridge\Tests\FieldEdge\ClimatrolFixture as Fixture;
use SiteEngine\AdsBridge\Tests\FieldEdge\SyntheticOutcomeApi;
use SiteEngine\AdsBridge\Connectors\FieldEdge\SubmissionAdapter;

$passes = 0;
function verify( bool $ok, string $label ): void {
	global $passes;
	if ( ! $ok ) {
		fwrite( STDERR, "FAIL: $label\n" );
		exit( 1 );
	}
	++$passes;
	echo "PASS: $label\n";
}
function invalid( callable $call, string $label ): void {
	try {
		$call();
	} catch ( InvalidArgumentException $error ) {
		verify( true, $label );
		return;
	}
	verify( false, $label );
}

$attributed = Fixture::packet( 'attributed' );
$unattributed = Fixture::packet( 'unattributed' );
$review = Fixture::packet( 'review' );
verify( 'Attributed' === $attributed['attribution_status'], 'explicit reviewed association attributed' );
verify( 'Unattributed' === $unattributed['attribution_status'], 'absent association unattributed' );
verify( 'Needs Review' === $review['attribution_status'], 'contact match alone needs review' );
verify( 'inq-climatrol-synthetic-1' === $attributed['outcome']['inquiry_id'], 'one form inquiry linked explicitly' );
verify( array( 'type' => 'fluent_form', 'form_id' => 7, 'entry_id' => 101 ) === $attributed['inquiry']['source'], 'immutable form source preserved without invented Google ID' );
verify( '1250.00' === $attributed['outcome']['amount'] && 'USD' === $attributed['outcome']['currency'], 'synthetic USD value retained exactly' );
verify( 'fieldedge' === $attributed['outcome']['provider'], 'provider identity retained' );
foreach ( array( 'tenant_id', 'customer_id', 'work_order_id', 'invoice_id' ) as $member ) {
	verify( $attributed['outcome'][ $member ] === $unattributed['outcome'][ $member ] && $review['outcome'][ $member ] === $attributed['outcome'][ $member ], "$member retained across all attribution states" );
}
verify( '2026-11-01T14:20:00.1234567Z' === $unattributed['source_evidence']['completed_at'], 'unattributed record retains original completion time' );
verify( '2026-11-01T00:00:00Z' === $review['source_evidence']['invoice_at'], 'review record retains invoice time' );
verify( strlen( $attributed['source_evidence']['invoice_sha256'] ) === 64 && strlen( $review['source_evidence']['notification']['body_sha256'] ) === 64, 'record and event source hashes retained' );
verify( ! $attributed['export_eligible'] && ! $unattributed['export_eligible'] && ! $review['export_eligible'], 'attribution grants no advertising eligibility' );
verify( ! isset( $attributed['transaction_id'] ) && ! str_contains( json_encode( $attributed ), 'synthetic@example.invalid' ), 'no invented transaction identity or contact PII in normalized packet' );
verify( $attributed === Fixture::packet( 'attributed' ), 'identical synthetic normalization is deterministic' );
verify( $attributed['packet_sha256'] !== $unattributed['packet_sha256'], 'association change alters exact normalized evidence digest' );
$inputs = Fixture::inputs();
$inputs[2][] = $inputs[2][0];
verify( 'Needs Review' === W::normalize( ...$inputs )['attribution_status'], 'ambiguous reviewed associations require review' );
$inputs = Fixture::inputs();
$inputs[3] = array();
$unapproved = W::normalize( ...$inputs );
verify( 'Attributed' === $unapproved['attribution_status'] && 'value_approval_missing_or_stale' === $unapproved['outcome']['reason_code'], 'attribution remains separate from missing value approval' );
$inputs = Fixture::inputs();
$inputs[2][0]['inquiry_id'] = 'unrelated-inquiry';
verify( 'Needs Review' === W::normalize( ...$inputs )['attribution_status'], 'association cannot silently point at different form inquiry' );
$inputs = Fixture::inputs();
$inputs[2][0]['lead_id'] = '77777777-7777-4777-8777-777777777777';
verify( 'Needs Review' === W::normalize( ...$inputs )['attribution_status'], 'association must match source lead evidence' );
$inputs = Fixture::inputs();
$inputs[4]['entity_id'] = '77777777-7777-4777-8777-777777777777';
invalid( fn() => W::normalize( ...$inputs ), 'notification cannot refer to different invoice' );
$inputs = Fixture::inputs();
$inputs[1]['source']['type'] = 'call';
invalid( fn() => W::normalize( ...$inputs ), 'unsupported call source cannot become form outcome' );
$inputs = Fixture::inputs();
$inputs[1]['source']['entry_id'] = '101';
invalid( fn() => W::normalize( ...$inputs ), 'noncanonical form source rejected' );
verify( '1250.00' === $unattributed['source_evidence']['approved_invoice_value']['amount'] && 'USD' === $review['source_evidence']['approved_invoice_value']['currency'], 'invoice value/currency preserved separately from association decision' );

$api = new SyntheticOutcomeApi();
$adapter = new SubmissionAdapter( array( $api, 'capability' ), array( $api, 'submit' ), array( $api, 'read' ) );
$body = $adapter->prepare( $attributed, 'climatrol-synthetic-attributed', 'synthetic-attributed-1' );
$request = json_decode( $body, true, 16, JSON_THROW_ON_ERROR );
verify( count( $request ) === 18 && count( array_filter( $request, 'is_string' ) ) === 18, 'exact 18-field all-string AB request' );
verify( $request['job_id'] === $attributed['outcome']['work_order_id'] && ! isset( $request['work_order_id'] ), 'work order maps only to supplied job_id field' );
verify( $request['event_sha256'] === $attributed['source_evidence']['notification']['body_sha256'] && $request['snapshot_sha256'] === $attributed['outcome']['snapshot_sha256'], 'original event and snapshot digests bound to request' );
verify( $request['value_policy_id'] === 'synthetic-usd-demo-1' && $request['destination'] === $api->capability()['destination'], 'supplied candidate policy and discovered destination used' );
verify( ! isset( $request['transaction_id'] ) && ! isset( $request['consent'] ) && ! isset( $request['attributed'] ), 'caller cannot supply Google ID or eligibility/attribution booleans' );
$first = $adapter->send( $body );
verify( 'recorded' === $first['status'] && $first['receipt_verified'] && 'Attributed' === $first['attribution_status'], 'recorded requires matching receipt readback [double]' );
$replay = $adapter->send( $body );
verify( $first === $replay && 1 === $api->insertions, 'identical replay returns exact receipt with one modeled insert [double]' );
verify( 'conflict' === $adapter->send( $body . ' ' )['status'] && 1 === $api->insertions, 'same operation with re-encoded bytes conflicts [double]' );
$changed = $request;
$changed['amount'] = '1400.00';
verify( 'operation_identity_reused' === $adapter->send( json_encode( $changed, JSON_THROW_ON_ERROR ) )['reason_code'], 'same operation with changed amount conflicts [double]' );
$changed = $request;
$changed['operation_id'] = 'rotated-operation';
verify( 'source_event_identity_reused' === $adapter->send( json_encode( $changed, JSON_THROW_ON_ERROR ) )['reason_code'], 'new operation cannot bypass same provider event [double]' );
$unmatched_body = $adapter->prepare( $unattributed, 'climatrol-synthetic-unattributed', 'synthetic-unattributed-1' );
$unmatched = $adapter->send( $unmatched_body );
verify( 'held' === $unmatched['status'] && 'Unattributed' === $unmatched['attribution_status'] && $unmatched['receipt_verified'], 'persisted association_missing receipt yields Unattributed [double]' );
$review_body = $adapter->prepare( $review, 'climatrol-synthetic-review', 'synthetic-review-1' );
$needs_review = $adapter->send( $review_body );
verify( 'held' === $needs_review['status'] && 'Needs Review' === $needs_review['attribution_status'] && $needs_review['receipt_verified'], 'ambiguous receipt yields Needs Review [double]' );
verify( ! $first['export_eligible'] && ! $unmatched['export_eligible'] && ! $needs_review['export_eligible'], 'no receipt permits export in this candidate' );

foreach ( array( 'missing' => 'ab_receipt_unconfirmed', 'wrong_body' => 'ab_receipt_body_mismatch', 'bad_response' => 'ab_receipt_response_unconfirmed', 'throw' => 'ab_call_or_read_unconfirmed' ) as $mode => $reason ) {
	$api->read_mode = $mode;
	$before = $api->calls;
	$result = $adapter->send( $body );
	verify( 'retry' === $result['status'] && $reason === $result['reason_code'] && ! $result['receipt_verified'] && $api->calls === $before + 1, "$mode readback cannot claim recorded or auto-retry [double]" );
}
$api->read_mode = 'normal';
verify( $first === $adapter->send( $body ) && 3 === $api->insertions, 'exact replay after read failure recovers receipt without new insert [double]' );
$uncertain_api = new SyntheticOutcomeApi();
$uncertain_api->uncertain_reply = true;
$uncertain_adapter = new SubmissionAdapter( array( $uncertain_api, 'capability' ), array( $uncertain_api, 'submit' ), array( $uncertain_api, 'read' ) );
verify( 'recorded' === $uncertain_adapter->send( $body )['status'] && 1 === $uncertain_api->insertions, 'matching stored receipt governs uncertain submit reply [double]' );
$altered_packet = $attributed;
$altered_packet['outcome']['amount'] = '9.99';
invalid( fn() => $adapter->prepare( $altered_packet, 'test', 'test-evidence' ), 'changed normalized packet rejected before submission' );
$bad_capability = new SubmissionAdapter( static fn() => array( 'contract_version' => '2', 'destination' => str_repeat( 'a', 64 ) ) );
invalid( fn() => $bad_capability->prepare( $attributed, 'test', 'test-evidence' ), 'unknown AB contract version rejected' );
$no_value = Fixture::inputs();
$no_value[3] = array();
invalid( fn() => $adapter->prepare( W::normalize( ...$no_value ), 'test', 'test-evidence' ), 'unapproved value cannot be submitted' );
$changed_response = new SubmissionAdapter( null, static fn( string $b ) => array( 'status' => 'recorded' ) );
verify( 'ab_response_unconfirmed' === $changed_response->send( $body )['reason_code'], 'malformed reply rejected without claiming persisted state' );
$absent = new SubmissionAdapter();
verify( 'retry' === $absent->send( $body )['status'], 'unavailable WordPress callable yields dependency retry, no fallback ledger' );
$authority_api = new SyntheticOutcomeApi();
$authority_adapter = new SubmissionAdapter( array( $authority_api, 'capability' ), array( $authority_api, 'submit' ), array( $authority_api, 'read' ) );
$authority_body = $authority_adapter->prepare( $unattributed, 'authority-test', 'synthetic-attributed-1' );
verify( 'Attributed' === $authority_adapter->send( $authority_body )['attribution_status'], 'stored AB association governs instead of local hint [double]' );
echo "RESULT: $passes PASS, 0 FAIL; local Climatrol fixtures + non-durable AB API double only.\n";
