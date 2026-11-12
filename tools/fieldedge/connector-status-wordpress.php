<?php
/**
 * Explicit authenticated read-only FieldEdge diagnostic runner for WP-CLI.
 *
 * Usage from a known WordPress home/root through the existing approved site runner:
 * wp --user=<admin> eval '$args=["<home>","<root>"]; require "<FE-path>/tools/fieldedge/connector-status-wordpress.php";'
 *
 * @package SiteEngineAdsBridge
 */

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || true !== WP_CLI ||
	! function_exists( 'current_user_can' ) || ! current_user_can( 'manage_options' ) ||
	! isset( $args ) || ! is_array( $args ) || count( $args ) !== 2 ||
	! is_string( $args[0] ) || ! is_string( $args[1] ) ||
	! hash_equals( home_url(), $args[0] ) ||
	! hash_equals( realpath( ABSPATH ) ?: '', realpath( $args[1] ) ?: '' ) ) {
	throw new RuntimeException( 'verified_administrator_and_site_required' );
}

require_once __DIR__ . '/../../includes/connectors/fieldedge/ConnectorDiagnostics.php';

use SiteEngine\AdsBridge\Connectors\FieldEdge\ConnectorDiagnostics;

echo wp_json_encode( ConnectorDiagnostics::wordpress(), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . "\n";
