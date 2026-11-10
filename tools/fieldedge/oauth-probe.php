<?php
/** Private stdin only; sanitized JSON stdout. No arguments/environment secrets. */
ini_set( 'display_errors', '0' );
ini_set( 'log_errors', '0' );
ini_set( 'zend.exception_ignore_args', '1' );
require_once __DIR__ . '/../../includes/connectors/fieldedge/OAuthClient.php';

use SiteEngine\AdsBridge\Connectors\FieldEdge\OAuthClient;

try {
	if ( PHP_SAPI !== 'cli' || $argc !== 1 ) {
		throw new RuntimeException();
	}
	$input = stream_get_contents( STDIN, 32769 );
	if ( strlen( $input ) > 32768 ) {
		throw new RuntimeException();
	}
	$credentials = json_decode( $input, true, 4, JSON_THROW_ON_ERROR );
	unset( $input );
	if ( ! is_array( $credentials ) || ( $credentials['environment'] ?? null ) !== 'Production_ClimatrolAirLLC' ) {
		throw new RuntimeException();
	}
	$report = ( new OAuthClient() )->probe( $credentials );
	unset( $credentials );
	echo json_encode( $report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES ) . "\n";
	exit( $report['authentication_succeeded'] && $report['read_succeeded'] ? 0 : 2 );
} catch ( Throwable $error ) {
	echo '{"authentication_succeeded":false,"read_succeeded":false,"reason_code":"secure_input_unavailable"}' . "\n";
	exit( 2 );
}
