<?php
/**
 * 0.5.x Golden Account contract compatibility acceptance.
 * Run: php tests/static-acceptance-050.php
 */
$root = dirname( __DIR__ );
$main = file_get_contents( $root . '/siteengine-ads-bridge.php' );
$runtime = file_get_contents( $root . '/includes/class-siteengine-ads-bridge-runtime.php' );
$cli = file_get_contents( $root . '/includes/class-siteengine-ads-bridge-cli.php' );
$settings = file_get_contents( $root . '/includes/class-siteengine-ads-bridge-settings.php' );
$config = file_get_contents( $root . '/includes/class-siteengine-ads-bridge-config.php' );
$doctor = file_get_contents( $root . '/includes/class-siteengine-ads-bridge-doctor.php' );
$admin = file_get_contents( $root . '/includes/class-siteengine-ads-bridge-admin.php' );
$example = file_get_contents( $root . '/config/client.example.php' );
$gdm = trim( file_get_contents( $root . '/docs/headers/google-data-manager.csv' ) );
$all = trim( file_get_contents( $root . '/docs/headers/all-leads.csv' ) );
$passes = 0; $fails = 0;
$check = function( $ok, $msg ) use ( &$passes, &$fails ) {
    if ( $ok ) { $passes++; echo "PASS: {$msg}\n"; }
    else { $fails++; echo "FAIL: {$msg}\n"; }
};
$check( false !== strpos( $main, 'Version: 0.6.3' ) && false !== strpos( $main, "SEAB_VERSION', '0.6.3'" ), 'current 0.6.3 release metadata is locked' );
$check( false !== strpos( $main, 'class-siteengine-ads-bridge-runtime.php' ) && false !== strpos( $main, 'SiteEngine_Ads_Bridge_Runtime::init()' ), 'front-end lead bridge is loaded by the plugin' );
$check( false !== strpos( $runtime, "fluentform/submission_confirmation" ) && false !== strpos( $runtime, "fluentform_submission_success" ), 'SE | Lead bridge uses documented Fluent Forms server and client success hooks' );
$check( false !== strpos( $runtime, "siteengine_ads_bridge_lead" ) && false !== strpos( $runtime, "'conversion_action' => 'SE | Lead'" ), 'browser bridge emits the standardized SE | Lead event' );
$check( false !== strpos( $runtime, 'SiteEngine_Ads_Bridge_Consent::resolve' ) && false !== strpos( $runtime, "'ad_user_data'" ) && false !== strpos( $runtime, "'granted'" ), 'browser bridge is gated by authoritative receipt-backed ad_user_data consent' );
$check( false !== strpos( $runtime, 'se_hashed_email' ) && false !== strpos( $runtime, 'se_hashed_phone' ) && false !== strpos( $runtime, "hash( 'sha256'" ), 'browser bridge exposes SHA-256 user identifiers to dataLayer' );
$check( false !== strpos( $settings, "'qualified_leads'" ) && false !== strpos( $settings, "'conversion_action'  => 'SE | Qualified Lead'" ) && false !== strpos( $settings, "'date_field'         => 'qualified_at'" ), 'settings define the durable Qualified Lead stage' );
$check( false !== strpos( $settings, "'conversion_action'  => 'SE | Converted Lead'" ), 'settings define the standardized Converted Lead action' );
$check( false !== strpos( $cli, "google_ads_import_qualified_lead" ) && false !== strpos( $cli, "google_ads_import_converted_lead" ), 'Qualified and Converted use independent ledger scopes' );
$check( false !== strpos( $cli, "'google_ads_import_sheet'" ) && false !== strpos( $cli, 'ledger_appended_in_any_sink' ), 'Converted migration honors the historical 0.4.x Won ledger sink without collapsing stage scopes' );
$check( false === strpos( $cli, "ff:{form_id}:{entry_id}:ql" ) && false === strpos( $cli, "ff:{form_id}:{entry_id}:cl" ), 'stage suffixes are not introduced into canonical transaction IDs' );
$q = strpos( $cli, 'All_Leads intake -> lifecycle ledger -> qualified_leads -> converted_leads' );
$check( false !== $q, 'aggregate exporter executes intake, lifecycle ledger, Qualified, then Converted' );
$g = strpos( $cli, "'label'   => 'google_ads_import'" );
$a = strpos( $cli, "'label'   => 'all_leads'", $g ?: 0 );
$check( false !== $g && false !== $a && $g < $a, 'Google_Ads_Import is the first physical worksheet contract' );
$check( false !== strpos( $cli, 'legacy 0.4.1 header' ) && false !== strpos( $cli, 'append Conversion action header' ), '0.4.1 20-column Google import headers have a safe 0.5.0 migration path' );
$check( false !== strpos( $cli, 'google_sheets_move_tab_to_index' ) && false !== strpos( $cli, "'fields'     => 'index'" ), 'worksheet-order migration changes index only' );
$check( false !== strpos( $cli, "'se_cid'" ) && false !== strpos( $cli, "'se_loc'" ) && false !== strpos( $all, 'Google campaign ID' ) && false !== strpos( $all, 'Google physical location criterion ID' ), 'Golden Account ValueTrack context is retained in operational exports' );
$check( false !== strpos( $gdm, 'Conversion action' ), 'Google Data Manager header contract includes Conversion action' );
$check( false !== strpos( $doctor, 'seab_last_export_runtime' ) && false !== strpos( $doctor, 'Cron PHP runtime' ), 'doctor monitors scheduled export freshness and PHP runtime' );
$check( false !== strpos( $admin, '*/10 * * * *' ) && false !== strpos( $admin, 'se-ads export-enabled --apply --limit=25' ), 'Setup generates the canonical 10-minute aggregate cron command' );
$check( false !== strpos( $admin, 'qualified_at' ) && false !== strpos( $admin, 'populated by the CRM automation' ), 'Setup makes the durable qualification timestamp requirement explicit' );
$check( false === strpos( $example, "'conversion_action' => 'CHANGE_ME'" ) && false !== strpos( $example, "'conversion_action'  => 'SE | Converted Lead'" ), 'fresh example removes Lost conversion actions and uses Golden Account conversion names' );
$check( false !== strpos( $config, "'exports.qualified_leads.conversion_action'" ) && false !== strpos( $config, "'exports.won_jobs.conversion_action'" ), 'config validator understands both Google lifecycle stages' );
$check( false !== strpos( $cli, "10 === strlen( \$digits )" ) && false !== strpos( $cli, "return '+1' . \$digits" ), 'CLI phone normalization accepts ordinary US 10-digit numbers' );
$check( false !== strpos( $runtime, "10 === strlen( \$digits )" ) && false !== strpos( $runtime, "return '+1' . \$digits" ), 'browser EC4L phone normalization matches the server-side E.164 rule' );

echo "\nSUMMARY: {$passes} PASS, {$fails} FAIL\n";
exit( $fails ? 1 : 0 );
