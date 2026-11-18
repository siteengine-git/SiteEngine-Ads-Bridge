<?php
/**
 * AB-owned isolated WordPress integration entry: wp --user=<admin> eval-file ...
 * Arguments: stable-operation reviewed-evidence actual-inquiry actual-form actual-entry destination.
 * Does not create the required inquiry/review or prepare schema; AB owns setup.
 */
if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI || ! current_user_can( 'manage_options' ) ) {
	throw new RuntimeException( 'isolated_administrator_cli_required' );
}
require_once __DIR__ . '/../../tests/support/fieldedge/ClimatrolFixture.php';
require_once __DIR__ . '/../../includes/connectors/fieldedge/SubmissionAdapter.php';

use SiteEngine\AdsBridge\Tests\FieldEdge\ClimatrolFixture;
use SiteEngine\AdsBridge\Connectors\FieldEdge\ClimatrolWorkflow;
use SiteEngine\AdsBridge\Connectors\FieldEdge\Foundation;
use SiteEngine\AdsBridge\Connectors\FieldEdge\SubmissionAdapter;

if ( ! isset( $args ) || 6 !== count( $args ) ||
	! preg_match( '/\A[1-9][0-9]{0,8}\z/D', $args[3] ) || ! preg_match( '/\A[1-9][0-9]{0,8}\z/D', $args[4] ) ) {
	throw new InvalidArgumentException( 'six_explicit_isolated_fixture_arguments_required' );
}
$inputs = ClimatrolFixture::inputs();
$inputs[1]['inquiry_id'] = Foundation::id( $args[2] );
$inputs[1]['source']['form_id'] = (int) $args[3];
$inputs[1]['source']['entry_id'] = (int) $args[4];
$inputs[2][0]['inquiry_id'] = $inputs[1]['inquiry_id'];
$packet = ClimatrolWorkflow::normalize( ...$inputs );
$adapter = new SubmissionAdapter();
$body = $adapter->prepare( $packet, $args[0], $args[1] );
$request = json_decode( $body, true, 16, JSON_THROW_ON_ERROR );
if ( ! hash_equals( $request['destination'], $args[5] ) ) {
	throw new InvalidArgumentException( 'isolated_destination_mismatch' );
}
// Deterministic synthetic inputs only. For any uncertain retry retain the exact
// original body; changing arguments, source, code or destination is not a retry.
echo json_encode( array(
	'verification' => 'actual AB call and receipt readback in operator-selected WordPress',
	'normalized_packet_sha256' => $packet['packet_sha256'],
	'body' => $body,
	'result' => $adapter->send( $body ),
), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . "\n";
