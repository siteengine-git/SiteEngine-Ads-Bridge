<?php
/**
 * Private one-lead status GET. FieldEdge records and credentials never go to
 * stdout or Git. Requires an existing AB-bound provider GUID as input.
 *
 * No real lead POST, WordPress write, advertising submission or customer
 * message. One OAuth attempt and at most one exact GET per private journal.
 *
 * @package SiteEngineFieldEdge
 */
ini_set( 'display_errors', '0' );
ini_set( 'log_errors', '0' );
ini_set( 'zend.exception_ignore_args', '1' );

require_once __DIR__ . '/../../includes/connectors/fieldedge/OAuthClient.php';
require_once __DIR__ . '/../../includes/connectors/fieldedge/LeadStatusDiscovery.php';

use SiteEngine\AdsBridge\Connectors\FieldEdge\OAuthClient;
use SiteEngine\AdsBridge\Connectors\FieldEdge\SelectedCaseJournal;
use SiteEngine\AdsBridge\Connectors\FieldEdge\LeadStatusDiscovery;

$reason = 'lead_status_input_unconfirmed';
try {
	if ( PHP_SAPI !== 'cli' ) {
		throw new RuntimeException();
	}
	$stdin = stream_get_contents( STDIN, 32769 );
	if ( ! is_string( $stdin ) || strlen( $stdin ) > 32768 ) {
		throw new RuntimeException();
	}
	$config = json_decode( $stdin, true, 8, JSON_THROW_ON_ERROR );
	unset( $stdin );
	$reason = 'lead_status_evidence_unconfirmed';
	if ( ( $config['environment'] ?? null ) !== 'Production_ClimatrolAirLLC' ||
		! preg_match( '/\A[a-f0-9]{64}\z/D', $config['evidence_sha256'] ?? '' ) ) {
		throw new RuntimeException();
	}
	$bytes = file_get_contents( $config['evidence_file'], false, null, 0, 8193 );
	if ( ! is_string( $bytes ) || strlen( $bytes ) > 8192 ||
		hash( 'sha256', $bytes ) !== $config['evidence_sha256'] ||
		file_exists( $config['findings_file'] ) || is_link( $config['findings_file'] ) ) {
		throw new RuntimeException();
	}
	$case = json_decode( $bytes, true, 8, JSON_THROW_ON_ERROR );
	unset( $bytes );
	if ( ! is_array( $case ) || isset( $case['tenant_id'] ) ) {
		throw new RuntimeException();
	}
	if ( count( $case ) !== 3 ||
		array_diff( array_keys( $case ), array( 'schema', 'inquiry_id', 'lead_id' ) ) ||
		( $case['schema'] ?? null ) !== 1 ||
		! is_string( $case['inquiry_id'] ?? null ) ||
		! preg_match( '/\A[a-zA-Z0-9:._-]{1,80}\z/D', $case['inquiry_id'] ) ) {
		throw new RuntimeException();
	}
	\SiteEngine\AdsBridge\Connectors\FieldEdge\TargetedRead::id( $case['lead_id'] ?? null );
	\SiteEngine\AdsBridge\Connectors\FieldEdge\TargetedRead::id( $config['target_id'] );
	$case['tenant_id'] = strtolower( $config['target_id'] );

	$reason = 'lead_status_journal_unconfirmed';
	$journal = new SelectedCaseJournal( $config['journal_file'], $config['evidence_sha256'] );
	$reason = 'lead_status_execution_unconfirmed';
	$path = $config['findings_file'];
	$report = ( new OAuthClient() )->selectedCase(
		$config, $journal,
		static fn( $get, $j ) => LeadStatusDiscovery::run( $get, $j, $case )
	);
	unset( $case, $config );
	$serialized = json_encode( $report, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT );
	$reason = 'lead_status_receipt_unconfirmed';
	$out = fopen( $path, 'xb' );
	if ( ! $out || fwrite( $out, $serialized ) !== strlen( $serialized ) ||
		! fflush( $out ) || ! fsync( $out ) ) {
		throw new RuntimeException();
	}
	fclose( $out );
	$status = $report['reason_code'];
	$get_count = count( $report['journal']['attempts'] ?? array() );
	echo json_encode( array(
		'status' => $status === 'selected_case_completed' ? 'observed' : 'held',
		'reason_code' => $status,
		'lead_get_count' => $get_count,
		'http_statuses' => array_map(
			static fn( $attempt ) => $attempt['http_status'] ?? null,
			$report['journal']['attempts'] ?? array()
		),
		'provider_status' => $report['findings']['status'] ?? null,
		'needs_review' => $report['findings']['needs_review'] ?? true,
		'crm_writes' => 0, 'advertising_exports' => 0,
	), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . "\n";
	exit( $status === 'selected_case_completed' ? 0 : 2 );
} catch ( Throwable $error ) {
	echo json_encode( array( 'status' => 'held', 'reason_code' => $reason ) ) . "\n";
	exit( 2 );
}
