<?php
/** Synthetic, side-effect-free FieldEdge lead-status contract tests. */
require_once __DIR__ . '/../includes/connectors/fieldedge/LeadStatusProjection.php';

use SiteEngine\AdsBridge\Connectors\FieldEdge\LeadStatusProjection as P;

$n = 0;
$check = static function ( bool $pass, string $why ) use ( &$n ): void {
	if ( ! $pass ) { throw new RuntimeException( 'FAIL ' . $why ); }
	++$n;
};
$guid = '11111111-1111-4111-8111-111111111111';
$tenant = '22222222-2222-4222-8222-222222222222';
$statuses = array(
	'Accepted' => 'lead_accepted', 'Rejected' => 'lead_rejected',
	'QuoteAccepted' => 'quote_accepted', 'QuoteRejected' => 'quote_rejected',
	'QuoteExpired' => 'quote_expired', 'JobCanceled' => 'job_canceled',
	'JobCompleted' => 'job_completed', 'JobFinalized' => 'job_finalized',
);
$body = static fn( $status, ?string $tracking = null ) => json_encode( array(
	'data' => array(
		'leadId' => $guid, 'trackingId' => $tracking ?? $guid,
		'status' => $status, 'customer' => array(
			'customerId' => '33333333-3333-4333-8333-333333333333',
			'email' => 'NEVER-COPY-PRIVATE@example.invalid',
		),
		'workOrderId' => '44444444-4444-4444-8444-444444444444',
		'quoteId' => '55555555-5555-4555-8555-555555555555',
		'invoiceId' => '66666666-6666-4666-8666-666666666666',
	) ), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES );
foreach ( $statuses as $raw => $expected ) {
	$result = P::examine( $tenant, 'ff:39:889', $guid, $body( $raw ) );
	$check( $result['provider_status'] === $raw && $result['observed_outcome'] === $expected, $raw . ' preserved' );
	$check( ! $result['needs_review'], $raw . ' mapped' );
	$check( $result['work_order_id'] === null && $result['invoice_id'] === null &&
		$result['approved_revenue'] === null, $raw . ' cannot invent an invoice' );
	$check( ! $result['crm_lifecycle_write_allowed'] && ! $result['advertising_export_allowed'], $raw . ' is evidence-only' );
	$check( ! str_contains( json_encode( $result ), 'NEVER-COPY-PRIVATE' ), $raw . ' contains no customer PII' );
	$check( $result['tenant_id'] === $tenant && $result['inquiry_id'] === 'ff:39:889' && $result['lead_id'] === $guid, $raw . ' identity pinned' );
}
foreach ( array( 'Initiated', 'InProcess', 'OtherNewStatus', 0, 19 ) as $unknown ) {
	$r = P::examine( $tenant, 'ff:39:889', $guid, $body( $unknown ) );
	$check( $r['observed_outcome'] === 'status_unmapped' && $r['needs_review'], 'unconfirmed status remains unmapped' );
}
$a = P::examine( $tenant, 'ff:39:889', $guid, $body( 'JobCompleted' ) );
$b = P::examine( $tenant, 'ff:39:889', $guid, $body( 'JobCompleted' ) );
$check( $a === $b, 'identical snapshot is deterministic' );
$check( strlen( $a['observation_sha256'] ) === 64, 'observation digest retained' );
$check( $a['work_order_id'] === null, 'untrusted injected workOrderId ignored' );
foreach ( array(
	array( $tenant, 'ff:39:889', 'not-guid', $body( 'Accepted' ) ),
	array( $tenant, 'not valid inquiry', $guid, $body( 'Accepted' ) ),
	array( $tenant, 'ff:39:889', $guid, $body( 'Accepted', '77777777-7777-4777-8777-777777777777' ) ),
) as $case ) {
	try { P::examine( ...$case ); $check( false, 'invalid binding admitted' ); }
	catch ( InvalidArgumentException $e ) { $check( true, 'wrong identity refused' ); }
}
$altered = $body( 'JobFinalized' );
$altered = str_replace( $guid, '77777777-7777-4777-8777-777777777777', $altered );
try { P::examine( $tenant, 'ff:39:889', $guid, $altered ); $check( false, 'wrong lead admitted' ); }
catch ( InvalidArgumentException $e ) { $check( true, 'wrong returned leadId refused' ); }
echo "PASS $n provider-confirmed synthetic lead-status assertions; provider GETs=0; CRM writes=0; Google exports=0\n";
