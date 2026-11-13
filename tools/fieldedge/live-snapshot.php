<?php
/**
 * One private, read-only FieldEdge operational snapshot. No provider values
 * printed. Uses the prior separately verified contact/customer association.
 */
ini_set( 'display_errors', '0' );
ini_set( 'log_errors', '0' );
ini_set( 'zend.exception_ignore_args', '1' );
require_once __DIR__ . '/../../includes/connectors/fieldedge/LiveSnapshotDiscovery.php';
use SiteEngine\AdsBridge\Connectors\FieldEdge\OAuthClient;
use SiteEngine\AdsBridge\Connectors\FieldEdge\SelectedCaseJournal;
use SiteEngine\AdsBridge\Connectors\FieldEdge\LiveSnapshotDiscovery;

$stage = 'live_snapshot_input_unconfirmed';
try {
	$raw = stream_get_contents( STDIN, 32769 );
	if ( PHP_SAPI !== 'cli' || strlen( $raw ) > 32768 ) { throw new RuntimeException(); }
	$config = json_decode( $raw, true, 8, JSON_THROW_ON_ERROR );
	unset( $raw );
	$stage = 'live_snapshot_evidence_unconfirmed';
	if ( ( $config['environment'] ?? null ) !== 'Production_ClimatrolAirLLC' ||
		! preg_match( '/\A[a-f0-9]{64}\z/D', $config['evidence_sha256'] ?? '' ) ) {
		throw new RuntimeException();
	}
	$raw = file_get_contents( $config['evidence_file'], false, null, 0, 8193 );
	if ( ! is_string( $raw ) || strlen( $raw ) > 8192 ||
		hash( 'sha256', $raw ) !== $config['evidence_sha256'] ||
		file_exists( $config['findings_file'] ) || is_link( $config['findings_file'] ) ) {
		throw new RuntimeException();
	}
	$case = json_decode( $raw, true, 8, JSON_THROW_ON_ERROR );
	unset( $raw );
	$case['tenant_id'] = $config['target_id'];
	$stage = 'live_snapshot_journal_unconfirmed';
	$journal = new SelectedCaseJournal( $config['journal_file'], $config['evidence_sha256'] );
	$stage = 'live_snapshot_execution_unconfirmed';
	$report = ( new OAuthClient() )->selectedCase(
		$config, $journal,
		static fn( $get, $j ) => LiveSnapshotDiscovery::run( $get, $j, $case )
	);
	unset( $case );
	$body = json_encode( $report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT );
	$fh = fopen( $config['findings_file'], 'xb' );
	if ( ! $fh ) { throw new RuntimeException(); }
	if ( fwrite( $fh, $body ) !== strlen( $body ) || ! fflush( $fh ) || ! fsync( $fh ) ) {
		throw new RuntimeException();
	}
	fclose( $fh );
	$result = array(
		'reason_code' => $report['reason_code'],
		'authentication_succeeded' => $report['authentication_succeeded'],
		'token_http_status' => $report['token_http_status'],
		'business_get_attempts' => count( $report['journal']['attempts'] ),
		'attempt_statuses' => array_map(
			static fn( $a ) => array_intersect_key( $a, array_flip( array( 'number', 'operation', 'status', 'http_status' ) ) ),
			$report['journal']['attempts']
		),
		'work_order_snapshots' => count( $report['findings']['jobs'] ?? array() ),
		'payload_sha256' => hash( 'sha256', json_encode( $report['findings'], JSON_THROW_ON_ERROR ) ),
		'private_findings_retained' => true,
	);
	echo json_encode( $result, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . "\n";
	exit( $report['reason_code'] === 'selected_case_completed' ? 0 : 2 );
} catch ( Throwable $error ) {
	echo json_encode( array( 'reason_code' => $stage ), JSON_THROW_ON_ERROR ) . "\n";
	exit( 2 );
}
