<?php
/**
 * Inspect private live FieldEdge provider evidence before site dispatch.
 * Reports no contact, provider UUID, token, email, financial value, or raw body.
 */
require_once __DIR__ . '/../../includes/connectors/fieldedge/LiveCRMProjection.php';
use SiteEngine\AdsBridge\Connectors\FieldEdge\LiveCRMProjection;
$path = $argv[1] ?? '';
try {
	if ( PHP_SAPI !== 'cli' || ! is_file( $path ) || filesize( $path ) > 131072 ) {
		throw new RuntimeException( 'private_provider_receipt_unavailable' );
	}
	$report = json_decode( file_get_contents( $path ), true, 16, JSON_THROW_ON_ERROR );
	if ( ( $report['reason_code'] ?? null ) !== 'selected_case_completed' ||
		( $report['journal']['closed'] ?? null ) !== true ||
		( $report['journal']['stop_reason'] ?? null ) !== 'selected_case_completed' ||
		count( $report['journal']['attempts'] ?? array() ) !== 4 ||
		! is_array( $report['findings'] ?? null ) ) {
		throw new RuntimeException( 'provider_receipt_incomplete' );
	}
	$payload = $report['findings'];
	$plan = LiveCRMProjection::plan( $payload, array() );
	$out = array(
		'status' => $plan['status'],
		'reason_code' => $plan['reason_code'],
		'job_count' => $plan['job_count'] ?? null,
		'completed_count' => count( array_filter( $payload['jobs'] ?? array(), static fn( $j ) => ( $j['completed'] ?? false ) === true ) ),
		'invoice_reference_count' => count( array_filter( $payload['jobs'] ?? array(), static fn( $j ) => ! empty( $j['invoice_id'] ) ) ),
		'projection_sha256' => $plan['projection_sha256'] ?? null,
	);
	echo json_encode( $out, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES ) . "\n";
	exit( $plan['status'] === 'ready' ? 0 : 2 );
} catch ( Throwable $error ) {
	echo '{"status":"held","reason_code":"private_provider_receipt_unconfirmed"}' . "\n";
	exit( 2 );
}
