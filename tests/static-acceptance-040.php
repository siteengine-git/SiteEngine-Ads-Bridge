<?php
$root = dirname( __DIR__ );
$main = file_get_contents( $root . '/siteengine-ads-bridge.php' );
$config = file_get_contents( $root . '/includes/class-siteengine-ads-bridge-config.php' );
$admin = file_get_contents( $root . '/includes/class-siteengine-ads-bridge-admin.php' );
$settings = file_get_contents( $root . '/includes/class-siteengine-ads-bridge-settings.php' );
$doctor = file_get_contents( $root . '/includes/class-siteengine-ads-bridge-doctor.php' );
$example = file_get_contents( $root . '/config/client.example.php' );
$passes = 0; $fails = 0;
$check = function( $ok, $msg ) use ( &$passes, &$fails ) {
    if ( $ok ) { $passes++; echo "PASS: {$msg}\n"; }
    else { $fails++; echo "FAIL: {$msg}\n"; }
};
$check( false !== strpos( $main, 'Version: 0.6.3' ) && false !== strpos( $main, "SEAB_VERSION', '0.6.3" ), 'current release metadata is consistent' );
$check( false !== strpos( $main, 'Requires Plugins: siteengine-base' ), 'SiteEngine Base is declared as a plugin dependency' );
$check( false === strpos( $main, "if ( ! file_exists( SEAB_CONFIG_FILE ) )" ), 'activation no longer requires a PHP config file' );
$check( false !== strpos( $main, 'class-siteengine-ads-bridge-settings.php' ) && false !== strpos( $main, 'class-siteengine-ads-bridge-admin.php' ), 'settings and Base admin layers load from bootstrap' );
$ext = strpos( $config, "SEAB_CONFIG_IS_EXTERNAL" );
$opt = strpos( $config, "SiteEngine_Ads_Bridge_Settings::exists()" );
$local = strpos( $config, "file_exists( SEAB_CONFIG_FILE )", $opt ?: 0 );
$check( false !== $ext && false !== $opt && false !== $local && $ext < $opt && $opt < $local, 'configuration precedence is external override -> WordPress settings -> local fallback' );
$check( false !== strpos( $config, "'client.name'                   => 'business_name'" ) && false !== strpos( $config, "'client.google_ads_customer_id' => 'google_ads_customer_id'" ), 'settings mode overlays shared client facts from SiteEngine Base' );
$check( false !== strpos( $config, "self::SRC_SETTINGS" ) && false !== strpos( $config, 'mark_provenance_recursive( $section, $raw[ $section ], self::SRC_SETTINGS )' ), 'settings-mode diagnostics relabel plugin-owned provenance as Ads Bridge settings' );
$check( false !== strpos( $admin, "add_filter( 'siteengine_modules'" ) && false !== strpos( $admin, "'ads-bridge'" ), 'Ads Bridge registers with the Base module registry' );
$check( false !== strpos( $admin, "add_filter( 'siteengine_readme_tabs'" ) && false !== strpos( $admin, "register_help_tab" ) && false !== strpos( $admin, "SiteEngine Ads Bridge Setup" ), 'Ads Bridge contributes versioned setup guidance to Base Help / Reference' );
$check( false !== strpos( $admin, "'business_name'" ) && false !== strpos( $admin, "'timezone'" ) && false !== strpos( $admin, "'currency'" ) && false !== strpos( $admin, "'google_ads_customer_id'" ), 'Base prerequisites include shared identity, timezone, currency, and Google Ads CID' );
$check( false !== strpos( $admin, 'handle_import' ) && false !== strpos( $admin, 'Import Current Configuration' ), 'one-click PHP config migration is present' );
$check( false !== strpos( $admin, 'Master Fluent Form' ) && false !== strpos( $admin, 'CRM & Lifecycle' ) && false !== strpos( $admin, 'Google Sheets' ), 'Base admin exposes setup, lifecycle, export, and Sheet configuration surfaces' );
$check( false !== strpos( $settings, "'all_leads'  => array(" ) && false !== strpos( $settings, "'enabled'          => false" ), 'fresh settings keep exports disabled' );
$check( false !== strpos( $settings, "'date_field'         => 'closed_date'" ), 'fresh lost configuration uses shared Outcome Date field' );
$check( false !== strpos( $doctor, 'SiteEngine Base 2.2 or newer is required' ), 'doctor reports missing Base as a failure' );
$check( false !== strpos( $example, "'date_field'         => 'closed_date'" ), 'client example reflects shared Outcome Date default' );
$check( false === strpos( $admin, 'update_field(' ) && false === strpos( $admin, 'update_post_meta(' ), 'configuration UI does not mutate CRM or form data' );
echo "\nSUMMARY: {$passes} PASS, {$fails} FAIL\n";
exit( $fails ? 1 : 0 );
