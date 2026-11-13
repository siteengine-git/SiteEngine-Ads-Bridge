<?php
/**
 * Plugin Name: SiteEngine FieldEdge Connector
 * Description: Authenticated FieldEdge operational snapshots to FluentCRM without lifecycle changes or advertising exports.
 * Version: 0.1.1
 * Requires PHP: 8.2
 * Requires Plugins: fluent-crm, siteengine-ads-bridge
 * Author: SiteEngine
 * Text Domain: siteengine-fieldedge-connector
 *
 * @package SiteEngineFieldEdge
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once __DIR__ . '/includes/LiveCRMProjection.php';
	require_once __DIR__ . '/includes/LiveCRMProjectionWordPress.php';

	final class SiteEngine_FieldEdge_Connector_CLI {
		public function status(): void {
			$out = array( 'version' => '0.1.1', 'provider' => 'fieldedge',
				'transport' => 'external_authenticated_read_only', 'auto_sync' => false,
				'lifecycle_writes' => false, 'google_exports' => false, 'production_lead_post' => false );
			try {
				$api = function_exists( 'FluentCrmApi' ) ? \FluentCrmApi( 'contacts' ) : null;
				$fields = $api ? array_column( $api->getCustomFields(), null, 'slug' ) : array();
				$out['crm_fields_ready'] =
					( $fields['se_fe_identity']['type'] ?? null ) === 'text' &&
					( $fields['se_fe_customer_json']['type'] ?? null ) === 'textarea' &&
					( $fields['se_fe_jobs_json']['type'] ?? null ) === 'textarea';
			} catch ( \Throwable $error ) {
				$out['crm_fields_ready'] = false;
			}
			\WP_CLI::line( wp_json_encode( $out ) );
		}

		/**
		 * Apply a verified private snapshot without external or advertising effects.
		 *
		 * ## OPTIONS
		 *
		 * --name=<file>
		 * : Private payload basename, never a path.
		 *
		 * --sha256=<hash>
		 * : Exact SHA256 of private payload bytes.
		 */
		public function apply( array $args, array $assoc_args ): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				\WP_CLI::error( 'authenticated_admin_cli_required', false );
				\WP_CLI::halt( 2 );
			}
			$name = $assoc_args['name'] ?? '';
			$expected = $assoc_args['sha256'] ?? '';
			if ( ! is_string( $name ) || ! preg_match( '/\A[a-z0-9-]{1,60}\.json\z/D', $name ) ||
				! is_string( $expected ) || ! preg_match( '/\A[a-f0-9]{64}\z/D', $expected ) ) {
				\WP_CLI::error( 'private_payload_reference_invalid', false );
				\WP_CLI::halt( 2 );
			}
			$private_dir = dirname( rtrim( ABSPATH, '/\\' ) ) . '/.siteengine-fieldedge';
			$real_dir = realpath( $private_dir );
			$real_file = $real_dir ? realpath( $real_dir . DIRECTORY_SEPARATOR . $name ) : false;
			if ( ! $real_dir || ! $real_file || is_link( $private_dir ) || is_link( $real_file ) ||
				dirname( $real_file ) !== $real_dir || ! is_file( $real_file ) ||
				filesize( $real_file ) > 32768 || ( fileperms( $real_file ) & 0077 ) !== 0 ||
				! function_exists( 'posix_geteuid' ) || fileowner( $real_file ) !== posix_geteuid() ||
				! hash_equals( $expected, hash_file( 'sha256', $real_file ) ) ) {
				\WP_CLI::error( 'private_payload_not_verified', false );
				\WP_CLI::halt( 2 );
			}
			try {
				$bytes = file_get_contents( $real_file );
				$input = json_decode( $bytes, true, 16, JSON_THROW_ON_ERROR );
				unset( $bytes );
				if ( ! is_array( $input ) ) {
					throw new \RuntimeException();
				}
				$result = \SiteEngine\AdsBridge\Connectors\FieldEdge\LiveCRMProjectionWordPress::apply( $input );
			} catch ( \Throwable $error ) {
				$result = array( 'status' => 'held', 'reason_code' => 'private_payload_unconfirmed' );
			}
			\WP_CLI::line( wp_json_encode( $result ) );
			if ( ! in_array( $result['status'] ?? null, array( 'updated', 'replayed' ), true ) ) {
				\WP_CLI::halt( 2 );
			}
		}
	}
	\WP_CLI::add_command( 'siteengine-fieldedge', 'SiteEngine_FieldEdge_Connector_CLI' );
}
