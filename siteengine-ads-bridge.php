<?php
/**
 * Plugin Name: SiteEngine Ads Bridge
 * Description: Reconciliation-first bridge for Fluent Forms, FluentCRM, SiteEngine Consent, and Google Ads Data Manager feeds.
 * Version: 0.8.3
 * Requires PHP: 8.3
 * Requires Plugins: siteengine-base
 * Author: SiteEngine
 * Text Domain: siteengine-ads-bridge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SEAB_VERSION', '0.8.3' );
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
require_once SEAB_PLUGIN_DIR . 'includes/class-siteengine-ads-bridge-time.php';
require_once SEAB_PLUGIN_DIR . 'includes/class-siteengine-ads-bridge-fields.php';
require_once SEAB_PLUGIN_DIR . 'includes/class-siteengine-ads-bridge-storage.php';
require_once SEAB_PLUGIN_DIR . 'includes/class-siteengine-ads-bridge-setup.php';
require_once SEAB_PLUGIN_DIR . 'includes/class-siteengine-ads-bridge-config.php';
require_once SEAB_PLUGIN_DIR . 'includes/class-siteengine-ads-bridge-runtime.php';
require_once SEAB_PLUGIN_DIR . 'includes/class-siteengine-ads-bridge-lifecycle.php';
require_once SEAB_PLUGIN_DIR . 'includes/class-siteengine-ads-bridge-coordination.php';
require_once SEAB_PLUGIN_DIR . 'includes/class-siteengine-ads-bridge-inquiries.php';
require_once SEAB_PLUGIN_DIR . 'includes/connectors/fieldedge/Foundation.php';
require_once SEAB_PLUGIN_DIR . 'includes/connectors/fieldedge/Retry.php';
require_once SEAB_PLUGIN_DIR . 'includes/class-siteengine-ads-bridge-outcomes.php';
require_once SEAB_PLUGIN_DIR . 'includes/class-siteengine-ads-bridge-prospective.php';
require_once SEAB_PLUGIN_DIR . 'includes/class-siteengine-ads-bridge-call-import.php';
require_once SEAB_PLUGIN_DIR . 'includes/class-siteengine-ads-bridge-doctor.php';
require_once SEAB_PLUGIN_DIR . 'includes/class-siteengine-ads-bridge-admin.php';
require_once SEAB_PLUGIN_DIR . 'includes/class-siteengine-ads-bridge-crm-import.php';

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
	if ( version_compare( PHP_VERSION, '8.3', '<' ) ) {
		deactivate_plugins( SEAB_PLUGIN_BASENAME );

		wp_die(
			esc_html__( 'SiteEngine Ads Bridge requires PHP 8.3 or newer.', 'siteengine-ads-bridge' ),
			esc_html__( 'Plugin activation failed', 'siteengine-ads-bridge' ),
			array( 'back_link' => true )
		);
	}

	update_option( 'seab_version', SEAB_VERSION, false );
	SiteEngine_Ads_Bridge_Setup::prepare_storage_for_admin();
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
	\SiteEngine\AdsBridge\Import\Admin::init();
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

/** Coordination contract 1; discovery is not installation/write authorization. */
function seab_configuration_capability() {
    return SiteEngine_Ads_Bridge_Coordination::capability();
}
/** Complete destination-wide observation; never a write permission. */
function seab_configuration_inspect_pending( $request ) {
    return SiteEngine_Ads_Bridge_Coordination::inspect( $request );
}
/** Finite same-process protected batch; no transport or vendor operation added. */
function seab_configuration_with_batch( $request, callable $callback ) {
    return SiteEngine_Ads_Bridge_Coordination::batch( $request, $callback );
}
/** Explicit future migration DDL only; this callable performs no SQL. */
function seab_configuration_schema_sql() {
    return SiteEngine_Ads_Bridge_Coordination::schema_sql();
}
/** Explicit administrative provisioning only; defaults to preview, never activation. */
function seab_configuration_migrate( $apply = false ) {
    return SiteEngine_Ads_Bridge_Coordination::migrate( $apply );
}

/** Trusted same-site outcome contract 1; exact JSON bytes own replay identity. */
function seab_external_outcome_capability(): array { return SiteEngine_Ads_Bridge_Outcomes::capability(); }
function seab_external_outcome_submit(string $body): array { return SiteEngine_Ads_Bridge_Outcomes::submit($body); }
function seab_external_outcome_review(array $evidence): array { return SiteEngine_Ads_Bridge_Outcomes::review($evidence); }
function seab_external_outcome_read(string $receipt_id): ?array { return SiteEngine_Ads_Bridge_Outcomes::read($receipt_id); }
function seab_external_call_evidence(string $call_sid,string $inquiry_id): array { return SiteEngine_Ads_Bridge_Outcomes::call_evidence($call_sid,$inquiry_id); }
/** Trusted prospective submission: durable claim; connector transport stays outside. */
function seab_prospective_prepare(string $intent_json): array { return SiteEngine_Ads_Bridge_Prospective::prepare($intent_json); }
function seab_prospective_claim(string $intent_id,string $request_body_sha256): array { return SiteEngine_Ads_Bridge_Prospective::claim($intent_id,$request_body_sha256); }
function seab_prospective_observe(string $intent_id,string $permit,string $observation_json): array { return SiteEngine_Ads_Bridge_Prospective::observe($intent_id,$permit,$observation_json); }
function seab_prospective_read(string $intent_id): array { return SiteEngine_Ads_Bridge_Prospective::read($intent_id); }
function seab_prospective_by_lead(string $tenant_id,string $lead_id): array { return SiteEngine_Ads_Bridge_Prospective::by_lead($tenant_id,$lead_id); }
function seab_prospective_pilot_review(string $card_json): array { return SiteEngine_Ads_Bridge_Prospective::pilot_review($card_json); }
function seab_prospective_pilot_read(string $card_sha256): array { return SiteEngine_Ads_Bridge_Prospective::pilot_read($card_sha256); }
function seab_prospective_pilot_claim(string $card_sha256,string $intent_id,string $request_sha256): array { return SiteEngine_Ads_Bridge_Prospective::pilot_claim($card_sha256,$intent_id,$request_sha256); }
