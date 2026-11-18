<?php
/** Runnable local demonstration. No provider network, WordPress or export writes. */
require_once __DIR__ . '/../../tests/support/fieldedge/ClimatrolFixture.php';
require_once __DIR__ . '/../../tests/support/fieldedge/SyntheticOutcomeApi.php';
require_once __DIR__ . '/../../includes/connectors/fieldedge/SubmissionAdapter.php';

use SiteEngine\AdsBridge\Tests\FieldEdge\ClimatrolFixture;
use SiteEngine\AdsBridge\Tests\FieldEdge\SyntheticOutcomeApi;
use SiteEngine\AdsBridge\Connectors\FieldEdge\SubmissionAdapter;

$api = new SyntheticOutcomeApi();
$adapter = new SubmissionAdapter( array( $api, 'capability' ), array( $api, 'submit' ), array( $api, 'read' ) );
$results = array();
$first_body = '';
foreach ( array( 'attributed', 'unattributed', 'review' ) as $scenario ) {
	$packet = ClimatrolFixture::packet( $scenario );
	$body = $adapter->prepare( $packet, 'climatrol-synthetic-' . $scenario, 'synthetic-' . $scenario . '-1' );
	$submitted = $adapter->send( $body );
	if ( 'attributed' === $scenario ) {
		$first_body = $body;
	}
	$results[] = array(
		'scenario' => $scenario,
		'attribution_status' => $submitted['attribution_status'],
		'local_attribution_hint' => $packet['attribution_status'],
		'reason_code' => $submitted['reason_code'],
		'provider' => $packet['outcome']['provider'],
		'tenant_id' => $packet['outcome']['tenant_id'],
		'customer_id' => $packet['outcome']['customer_id'],
		'work_order_id' => $packet['outcome']['work_order_id'],
		'invoice_id' => $packet['outcome']['invoice_id'],
		'inquiry_id' => $packet['outcome']['inquiry_id'] ?? null,
		'approved_amount' => $packet['source_evidence']['approved_invoice_value']['amount'] ?? null,
		'currency' => $packet['source_evidence']['approved_invoice_value']['currency'] ?? null,
		'completed_at' => $packet['source_evidence']['completed_at'],
		'invoice_at' => $packet['source_evidence']['invoice_at'],
		'event_at' => $packet['source_evidence']['notification']['occurred_at'],
		'packet_sha256' => $packet['packet_sha256'],
		'export_eligible' => false,
		'submission_status' => $submitted['status'],
		'receipt_verified_against_double' => $submitted['receipt_verified'],
		'receipt_id' => $submitted['ab_response']['receipt_id'] ?? null,
	);
}
$replay = $adapter->send( $first_body );
$conflicting = json_decode( $first_body, true, 16, JSON_THROW_ON_ERROR );
$conflicting['amount'] = '1400.00';
$conflict = $adapter->send( json_encode( $conflicting, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES ) );
echo json_encode( array(
	'verification' => 'local synthetic fixture + in-memory AB API double; no WordPress/durable receipt/export',
	'results' => $results,
	'replay' => array( 'status' => $replay['status'], 'same_receipt' => $replay['ab_response']['receipt_id'] === $results[0]['receipt_id'] ),
	'conflict' => array( 'status' => $conflict['status'], 'reason_code' => $conflict['reason_code'] ),
	'receipts_in_double' => $api->insertions,
	'submission_dependency' => 'AB contract 1 candidate; installed durable verification owned by AB',
), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . "\n";
