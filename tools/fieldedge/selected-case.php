<?php
/** Private stdin/outputs, selected evidence only. No production payload in stdout. */
ini_set( 'display_errors', '0' ); ini_set( 'log_errors', '0' ); ini_set( 'zend.exception_ignore_args', '1' );
require_once __DIR__ . '/../../includes/connectors/fieldedge/SelectedCaseDiscovery.php';
use SiteEngine\AdsBridge\Connectors\FieldEdge\OAuthClient;
use SiteEngine\AdsBridge\Connectors\FieldEdge\SelectedCaseJournal;
use SiteEngine\AdsBridge\Connectors\FieldEdge\SelectedCaseDiscovery;
$stage = 'selected_input_unconfirmed';
try {
	$input = stream_get_contents( STDIN, 32769 );
	if ( PHP_SAPI !== 'cli' || strlen( $input ) > 32768 ) { throw new RuntimeException(); }
	$config = json_decode( $input, true, 4, JSON_THROW_ON_ERROR ); unset( $input );
	$stage = 'selected_config_unconfirmed';
	if ( ( $config['environment'] ?? null ) !== 'Production_ClimatrolAirLLC' || ! preg_match( '/\A[a-f0-9]{64}\z/D', $config['evidence_sha256'] ?? '' ) ) { throw new RuntimeException(); }
	$stage = 'selected_evidence_unconfirmed';
	$bytes = file_get_contents( $config['evidence_file'], false, null, 0, 131073 );
	if ( ! is_string( $bytes ) || strlen( $bytes ) > 131072 || hash( 'sha256', $bytes ) !== $config['evidence_sha256'] ||
		file_exists( $config['findings_file'] ) || is_link( $config['findings_file'] ) ) { throw new RuntimeException(); }
	$entries = json_decode( $bytes, true, 32, JSON_THROW_ON_ERROR ); unset( $bytes );
	if ( ! is_array( $entries ) || count( $entries ) !== 2 ) { throw new RuntimeException(); }
	$stage = 'selected_journal_unconfirmed';
	$journal = new SelectedCaseJournal( $config['journal_file'], $config['evidence_sha256'] );
	$stage = 'selected_execution_unconfirmed';
	$report = ( new OAuthClient() )->selectedCase( $config, $journal, static fn( $get, $j ) => SelectedCaseDiscovery::run( $get, $j, $entries ) );
	unset( $entries );
	$file = fopen( $config['findings_file'], 'xb' );
	if ( ! $file ) { throw new RuntimeException(); }
	$private = json_encode( $report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR );
	if ( fwrite( $file, $private ) !== strlen( $private ) || ! fflush( $file ) || ! fsync( $file ) ) { throw new RuntimeException(); }
	fclose( $file );
	$safe = array( 'reason_code' => $report['reason_code'], 'authentication_succeeded' => $report['authentication_succeeded'],
		'token_http_status' => $report['token_http_status'], 'token_attempts' => $report['journal']['token_attempts'],
		'business_get_attempts' => count( $report['journal']['attempts'] ), 'attempt_statuses' => array_map( static fn( $a ) => array_intersect_key( $a, array_flip( array( 'number', 'operation', 'status', 'http_status' ) ) ), $report['journal']['attempts'] ),
		'entry_findings' => array_map( static fn( $f ) => array_intersect_key( $f, array_flip( array( 'entry_id', 'disposition', 'reason_code' ) ) ), $report['findings'] ?? array() ),
		'private_findings_file' => $config['findings_file'], 'private_journal_file' => $config['journal_file'] );
	echo json_encode( $safe, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES ) . "\n";
	exit( $report['reason_code'] === 'selected_case_completed' ? 0 : 2 );
} catch ( Throwable $error ) {
	echo json_encode( array( 'reason_code' => $stage ), JSON_THROW_ON_ERROR ) . "\n"; exit( 2 );
}
