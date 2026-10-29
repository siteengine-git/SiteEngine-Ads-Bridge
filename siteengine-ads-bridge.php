<?php
/**
 * Plugin Name: SiteEngine Ads Bridge
 * Description: Reconciliation-first bridge for Fluent Forms, FluentCRM, SiteEngine Consent, and Google Ads Data Manager feeds.
 * Version: 0.6.3.1-rc.1
 * Requires PHP: 7.4
 * Requires Plugins: siteengine-base
 * Author: SiteEngine
 * Text Domain: siteengine-ads-bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SEAB_VERSION', '0.6.3.1-rc.1' );
define( 'SEAB_PLUGIN_FILE', __FILE__ );
define( 'SEAB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SEAB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SEAB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Preserve whether SEAB_CONFIG_FILE was explicitly defined outside the plugin.
if ( ! defined( 'SEAB_CONFIG_IS_EXTERNAL' ) ) {
	define( 'SEAB_CONFIG_IS_EXTERNAL', defined( 'SEAB_CONFIG_FILE' ) );
}
if ( ! defined( 'SEAB_CONFIG_FILE' ) ) {
	define( 'SEAB_CONFIG_FILE', SEAB_PLUGIN_DIR . 'config/client.php' );
}

require_once SEAB_PLUGIN_DIR . 'includes/class-siteengine-ads-bridge-settings.php';
require_once SEAB_PLUGIN_DIR . 'includes/class-siteengine-ads-bridge-config.php';
require_once SEAB_PLUGIN_DIR . 'includes/class-siteengine-ads-bridge-runtime.php';
require_once SEAB_PLUGIN_DIR . 'includes/class-siteengine-ads-bridge-lifecycle.php';
require_once SEAB_PLUGIN_DIR . 'includes/class-siteengine-ads-bridge-inquiries.php';
require_once SEAB_PLUGIN_DIR . 'includes/class-siteengine-ads-bridge-doctor.php';
require_once SEAB_PLUGIN_DIR . 'includes/class-siteengine-ads-bridge-admin.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	require_once SEAB_PLUGIN_DIR . 'includes/class-siteengine-ads-bridge-cli.php';
}

/**
 * Activation guard.
 *
 * 0.6.0 does not require a pre-existing PHP config file. Incomplete setup is
 * represented in the SiteEngine UI and exports remain disabled by default.
 */
function seab_activate_plugin() {
	if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
		deactivate_plugins( SEAB_PLUGIN_BASENAME );

		wp_die(
			esc_html__( 'SiteEngine Ads Bridge requires PHP 7.4 or newer.', 'siteengine-ads-bridge' ),
			esc_html__( 'Plugin activation failed', 'siteengine-ads-bridge' ),
			array( 'back_link' => true )
		);
	}

	update_option( 'seab_version', SEAB_VERSION, false );
}
register_activation_hook( __FILE__, 'seab_activate_plugin' );

/**
 * Initialize Base-integrated administration.
 */
function seab_init_admin() {
	SiteEngine_Ads_Bridge_Admin::init();
	SiteEngine_Ads_Bridge_Runtime::init();
	SiteEngine_Ads_Bridge_Lifecycle::init();
	SiteEngine_Ads_Bridge_Inquiries::init();
}
add_action( 'plugins_loaded', 'seab_init_admin', 20 );

/**
 * Register WP-CLI commands only in CLI context.
 */
function seab_register_cli_commands() {
	if ( defined( 'WP_CLI' ) && WP_CLI && class_exists( 'SiteEngine_Ads_Bridge_CLI' ) ) {
		WP_CLI::add_command( 'se-ads', 'SiteEngine_Ads_Bridge_CLI' );
	}
}
seab_register_cli_commands();

/**
 * Stable internal plugin API for one-time adoption of a pre-0.6 current inquiry.
 *
 * CRM Ops must adopt existing current inquiries before enabling Start New Inquiry
 * on upgraded contacts. This freezes the current canonical Ads attribution binding
 * before any later form feed can replace the contact-level submission_id.
 *
 * @param array $payload Contract v1 adoption payload.
 * @return array
 */
function seab_inquiry_adopt_current( $payload ) {
	return SiteEngine_Ads_Bridge_Inquiries::adopt_current_inquiry( $payload );
}

/**
 * Stable internal plugin API for CRM Ops reconciliation phase 1.
 *
 * CRM Ops should call this function from its AdsBridgeAdapter rather than
 * reaching into Ads Bridge tables or FluentCRM internals.
 *
 * @param array $payload Contract v1 reconciliation-requested payload.
 * @return array
 */
function seab_inquiry_prepare_reconciliation( $payload ) {
	return SiteEngine_Ads_Bridge_Inquiries::prepare_reconciliation( $payload );
}

/**
 * Stable internal plugin API for CRM Ops reconciliation phase 2.
 *
 * CRM Ops must keep customer-facing inquiry automation held until this returns
 * status=committed for the matching reconciliation/activation event.
 *
 * @param array $payload Contract v1 inquiry-activated payload.
 * @return array
 */
function seab_inquiry_commit_activation( $payload ) {
	return SiteEngine_Ads_Bridge_Inquiries::commit_activation( $payload );
}
