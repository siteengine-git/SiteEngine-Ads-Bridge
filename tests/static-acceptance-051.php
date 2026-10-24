<?php
/**
 * 0.5.2 lifecycle/compliance/two-tab contract acceptance.
 * Run: php tests/static-acceptance-051.php
 */
$root      = dirname( __DIR__ );
$main      = file_get_contents( $root . '/siteengine-ads-bridge.php' );
$cli       = file_get_contents( $root . '/includes/class-siteengine-ads-bridge-cli.php' );
$config    = file_get_contents( $root . '/includes/class-siteengine-ads-bridge-config.php' );
$settings  = file_get_contents( $root . '/includes/class-siteengine-ads-bridge-settings.php' );
$lifecycle = file_get_contents( $root . '/includes/class-siteengine-ads-bridge-lifecycle.php' );
$doctor    = file_get_contents( $root . '/includes/class-siteengine-ads-bridge-doctor.php' );
$admin     = file_get_contents( $root . '/includes/class-siteengine-ads-bridge-admin.php' );
$readme    = file_get_contents( $root . '/README.md' );
$all_csv   = str_getcsv( trim( file_get_contents( $root . '/docs/headers/all-leads.csv' ) ), ',', '"', '' );
$gdm_csv   = str_getcsv( trim( file_get_contents( $root . '/docs/headers/google-data-manager.csv' ) ), ',', '"', '' );

$passes = 0; $fails = 0;
$check = function ( $ok, $msg ) use ( &$passes, &$fails ) {
    if ( $ok ) { $passes++; echo "PASS: {$msg}\n"; }
    else { $fails++; echo "FAIL: {$msg}\n"; }
};

$check( false !== strpos( $main, 'Version: 0.6.3' ) && false !== strpos( $main, "SEAB_VERSION', '0.6.3'" ), 'current 0.6.3 release metadata is locked' );
$check( false !== strpos( $main, 'class-siteengine-ads-bridge-lifecycle.php' ) && false !== strpos( $main, 'SiteEngine_Ads_Bridge_Lifecycle::init()' ), 'lifecycle compliance runtime is loaded and initialized' );
$check( false !== strpos( $config, "'new'," ) && false !== strpos( $config, "'qualified'," ) && false !== strpos( $config, "'unqualified'," ) && false !== strpos( $config, "'won'," ), 'canonical lifecycle contains New, Qualified, Unqualified, Won' );
$check( false !== strpos( $settings, "'unqualified_reason_field' => 'customer_rating'" ) && false !== strpos( $settings, "'unqualified_at_field'     => 'unqualified_at'" ), 'customer_rating and unqualified_at defaults are locked' );
$check( false !== strpos( $lifecycle, 'fluent_crm/contact_added_by_fluentform' ) && false !== strpos( $lifecycle, 'fluent_crm/contact_added_to_tags' ), 'FluentCRM compliance hooks are registered' );
$check( false !== strpos( $lifecycle, "self::stamp_if_blank( \$subscriber, \$qualified" ) && false !== strpos( $lifecycle, "self::stamp_if_blank( \$subscriber, \$closed" ), 'Won preserves qualification and close milestones' );
$check( false === strpos( $lifecycle, "syncCustomFieldValues( array( 'invoiced_amount'" ), 'lifecycle automation never writes invoice revenue' );
$check( false !== strpos( $lifecycle, "in_array( 'won', \$current_roles" ), 'Won is protected as a terminal lifecycle state' );
$check( false !== strpos( $cli, '@subcommand export-lifecycle-ledger' ), 'CLI exposes lifecycle ledger exporter' );
$check( false !== strpos( $cli, 'all_leads_lifecycle_qualified' ) && false !== strpos( $cli, 'all_leads_lifecycle_unqualified' ) && false !== strpos( $cli, 'all_leads_lifecycle_won' ), 'lifecycle snapshots use independent All_Leads ledger scopes' );
$check( false !== strpos( $cli, 'All_Leads intake -> lifecycle ledger -> qualified_leads -> converted_leads' ), 'aggregate export order includes lifecycle ledger before Google stages' );
$check( false !== strpos( $cli, 'export-lost-leads was retired in Ads Bridge 0.5.1' ), 'legacy Lost worksheet command is explicitly retired' );
$check( false !== strpos( $config, '$lost_enabled      = false;' ), 'legacy Lost worksheet configuration is inert' );
$check( 94 === count( $all_csv ) && 'Customer Rating' === $all_csv[88] && 'Qualified at' === $all_csv[89] && 'Unqualified at' === $all_csv[90] && 'Lifecycle event' === $all_csv[91] && 'Lifecycle event at' === $all_csv[92] && 'Inquiry ID' === $all_csv[93], 'All_Leads 94-column lifecycle/inquiry tail is exact' );
$check( 21 === count( $gdm_csv ) && 'Session attributes' === $gdm_csv[19] && 'Conversion action' === $gdm_csv[20], 'Google import retains canonical 21-column contract' );
$check( false !== strpos( $cli, 'manual compact 0.5 header' ) && false !== strpos( $cli, 'T2:T' ), 'known compact Google header is guarded by blank-data validation' );
$check( false !== strpos( $cli, 'manual Golden header' ) && false !== strpos( $cli, "array( 'Customer Rating', 'Customer Rating', 'Qualified at' )" ), 'known manually edited All_Leads header is safely recognized' );
$check( false !== strpos( $cli, 'google_sheets_range_is_blank' ), 'manual header normalization fails closed on nonblank data' );
$check( false !== strpos( $cli, "'slug'  => 'unqualified_at'" ), 'field provisioner includes unqualified_at' );
$check( false !== strpos( $admin, 'Lead Compliance' ) && false !== strpos( $cli, 'public function compliance' ), 'admin and CLI expose client compliance queue' );
$check( false !== strpos( $doctor, 'Unqualified leads are retained in the All_Leads lifecycle ledger' ), 'doctor documents silent-Unqualified Google behavior' );
$check( false !== strpos( $readme, 'Do not separate stages using `Conversion value > 0`' ), 'Data Manager guidance requires Conversion action filter rather than value filter' );
$check( false !== strpos( $readme, 'Google_Ads_Import` first and `All_Leads` second' ) || false !== strpos( $readme, '1. `Google_Ads_Import`' ), 'README documents two-tab workbook' );

echo "\nSUMMARY: {$passes} PASS, {$fails} FAIL\n";
exit( $fails ? 1 : 0 );
