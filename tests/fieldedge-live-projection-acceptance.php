<?php
/**
 * Pure live FieldEdge operational projection assertions.
 * No WordPress, vendor transport, customer writes, or ad export.
 */
require_once __DIR__ . '/../includes/connectors/fieldedge/LiveCRMProjection.php';
use SiteEngine\AdsBridge\Connectors\FieldEdge\LiveCRMProjection as P;

$n = 0;
$check = static function ( bool $value, string $why ) use ( &$n ): void {
	if ( ! $value ) { throw new RuntimeException( $why ); }
	++$n;
};
$tenant = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$customer = 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
$job1 = 'cccccccc-cccc-4ccc-8ccc-cccccccccccc';
$job2 = 'dddddddd-dddd-4ddd-8ddd-dddddddddddd';
$invoice = 'eeeeeeee-eeee-4eee-8eee-eeeeeeeeeeee';
$one = array(
	'work_order_id' => $job1, 'customer_id' => $customer, 'completed' => true,
	'completed_at' => '2026-11-04T19:30:00Z', 'invoice_id' => $invoice,
	'source_sha256' => str_repeat( '1', 64 ),
);
$payload = array(
	'schema' => 1, 'provider' => 'fieldedge', 'tenant_id' => $tenant,
	'customer_id' => $customer, 'contact_id' => 334,
	'form_id' => 39, 'entry_id' => 889,
	'email_sha256' => str_repeat( 'a', 64 ), 'jobs' => array( $one ),
);
$plan = P::plan( $payload, array() );
$check( $plan['status'] === 'ready', 'valid verified plan' );
$check( array_keys( $plan['fields'] ) === array( 'se_fe_identity', 'se_fe_customer_json', 'se_fe_jobs_json' ), 'only owned fields' );
$check( $plan['job_count'] === 1, 'one verified job' );
$check( ! str_contains( json_encode( $plan ), 'gclid' ), 'no advertising identifiers' );
$check( ! str_contains( json_encode( $plan ), 'won' ), 'never auto won' );
$check( ! str_contains( json_encode( $plan ), 'invoice_total' ), 'no invented revenue' );
$check( is_string( $plan['projection_sha256'] ) && strlen( $plan['projection_sha256'] ) === 64, 'digest reported' );
$prior = $plan['fields'];
$replay = P::plan( $payload, $prior );
$check( $replay['status'] === 'replayed', 'exact replay' );
$check( $replay['projection_sha256'] === $plan['projection_sha256'], 'replay stable digest' );
$twice = $payload;
$twice['jobs'][] = array( 'work_order_id' => $job2, 'customer_id' => $customer, 'completed' => false,
	'completed_at' => null, 'invoice_id' => null, 'source_sha256' => str_repeat( '2', 64 ) );
$next = P::plan( $twice, $prior );
$check( $next['status'] === 'ready' && $next['job_count'] === 2, 'new job merged' );
$check( P::plan( $twice, $next['fields'] )['status'] === 'replayed', 'multi-job replay' );
$bad = $twice;
$bad['jobs'][] = $one;
$check( P::plan( $bad, array() )['reason_code'] === 'duplicate_work_order', 'duplicate held' );
$bad = $payload; $bad['provider'] = 'other';
$check( P::plan( $bad, array() )['status'] === 'held', 'wrong provider' );
$bad = $payload; $bad['extra'] = 'x';
$check( P::plan( $bad, array() )['status'] === 'held', 'extra payload rejected' );
$bad = $payload; $bad['entry_id'] = 0;
$check( P::plan( $bad, array() )['status'] === 'held', 'missing original entry rejected' );
$bad = $payload; $bad['email_sha256'] = '';
$check( P::plan( $bad, array() )['status'] === 'held', 'missing contact evidence rejected' );
$bad = $payload; $bad['jobs'][0]['customer_id'] = $tenant;
$check( P::plan( $bad, array() )['reason_code'] === 'provider_work_order_invalid', 'foreign customer job refused' );
$bad = $payload; $bad['jobs'][0]['completed_at'] = 'not-a-date';
$check( P::plan( $bad, array() )['reason_code'] === 'invalid_verified_date', 'invalid completion held' );
$bad = $payload; $bad['jobs'][0]['invoice_id'] = 'invalid';
$check( P::plan( $bad, array() )['reason_code'] === 'provider_work_order_invalid', 'invalid invoice refused' );
$bad = $payload; $bad['jobs'][0]['source_sha256'] = 'x';
$check( P::plan( $bad, array() )['reason_code'] === 'provider_work_order_invalid', 'missing source proof refused' );
$old = $prior; $old[P::IDENTITY] = str_replace( $customer, $tenant, $old[P::IDENTITY] );
$check( P::plan( $payload, $old )['reason_code'] === 'provider_identity_conflict', 'provider identity conflict held' );
$old = $prior; unset( $old[P::IDENTITY] );
$check( P::plan( $payload, $old )['reason_code'] === 'existing_summary_without_provider_identity', 'orphan summaries held' );
$old = $prior; $old[P::JOBS] = '{not json';
$check( P::plan( $payload, $old )['reason_code'] === 'existing_jobs_invalid', 'corrupt jobs held' );
$old = $prior; $old[P::CUSTOMER] = '{not json';
$check( P::plan( $payload, $old )['reason_code'] === 'existing_customer_invalid', 'corrupt customer held' );
$bad = $payload; $bad['jobs'][0]['completed'] = false; $bad['jobs'][0]['completed_at'] = null;
$check( P::plan( $bad, $prior )['reason_code'] === 'work_order_revision_requires_review', 'completed-to-open cannot roll back' );
$bad = $payload; $bad['jobs'][0]['invoice_id'] = null;
$check( P::plan( $bad, $prior )['reason_code'] === 'work_order_revision_requires_review', 'invoice cannot disappear' );
$bad = $payload; $bad['jobs'][0]['completed_at'] = '2026-11-04T21:30:00Z';
$check( P::plan( $bad, $prior )['reason_code'] === 'work_order_revision_requires_review', 'changed completion held' );
$blank = $payload; $blank['jobs'] = array();
$check( P::plan( $blank, array() )['status'] === 'held', 'empty snapshot refused' );
$bad = $payload; $bad['jobs'][0]['note'] = 'PII';
$check( P::plan( $bad, array() )['status'] === 'held', 'provider arbitrary fields refused' );
echo "PASS $n live FieldEdge projection assertions; network=0; CRM writes=0\n";
