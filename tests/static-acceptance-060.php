<?php
/**
 * 0.6.3 inquiry-aware attribution contract acceptance.
 * Run: php tests/static-acceptance-060.php
 */
$root      = dirname( __DIR__ );
$main      = file_get_contents( $root . '/siteengine-ads-bridge.php' );
$inq       = file_get_contents( $root . '/includes/class-siteengine-ads-bridge-inquiries.php' );
$cli       = file_get_contents( $root . '/includes/class-siteengine-ads-bridge-cli.php' );
$doctor    = file_get_contents( $root . '/includes/class-siteengine-ads-bridge-doctor.php' );
$readme    = file_get_contents( $root . '/README.md' );
$all_csv   = str_getcsv( trim( file_get_contents( $root . '/docs/headers/all-leads.csv' ) ), ',', '"', '' );
$gdm_csv   = str_getcsv( trim( file_get_contents( $root . '/docs/headers/google-data-manager.csv' ) ), ',', '"', '' );

$passes = 0; $fails = 0;
$check = function ( $ok, $msg ) use ( &$passes, &$fails ) {
    if ( $ok ) { $passes++; echo "PASS: {$msg}\n"; }
    else { $fails++; echo "FAIL: {$msg}\n"; }
};

$check( false !== strpos( $main, 'Version: 0.6.3.1-rc.1' ) && false !== strpos( $main, "SEAB_VERSION', '0.6.3.1-rc.1'" ), '0.6.3.1-rc.1 candidate metadata is locked' );
$check( false !== strpos( $main, 'class-siteengine-ads-bridge-inquiries.php' ) && false !== strpos( $main, 'SiteEngine_Ads_Bridge_Inquiries::init()' ), 'inquiry runtime is loaded and initialized' );
$check( false !== strpos( $main, 'function seab_inquiry_adopt_current' ) && false !== strpos( $main, 'function seab_inquiry_prepare_reconciliation' ) && false !== strpos( $main, 'function seab_inquiry_commit_activation' ), 'stable CRM Ops adapter functions expose adopt, prepare, and activate phases' );

$check( false !== strpos( $inq, "'se_ads_inquiries'" ) && false !== strpos( $inq, 'UNIQUE KEY inquiry_id' ), 'Ads Bridge owns a dedicated durable inquiry-attribution table' );
$check( false !== strpos( $inq, 'UNIQUE KEY active_contact_id' ), 'schema enforces one active Ads inquiry per contact' );
$check( false !== strpos( $inq, 'UNIQUE KEY transaction_id' ), 'canonical Google transaction binding is unique per inquiry' );
$check( false !== strpos( $inq, 'UNIQUE KEY request_event_id' ) && false !== strpos( $inq, 'UNIQUE KEY activation_event_id' ), 'prepare and activation events have durable idempotency keys' );
$check( false !== strpos( $inq, "'prepared'" ) && false !== strpos( $inq, "'activating'" ) && false !== strpos( $inq, "'active'" ) && false !== strpos( $inq, "'archived'" ), 'persisted saga states include prepared, activating, active, and archived' );
$check( false !== strpos( $inq, 'dbDelta( self::schema_sql() )' ) && false === strpos( $main, 'SiteEngine_Ads_Bridge_Inquiries::migrate( true' ), 'inquiry schema migration is explicit and never automatic on plugin traffic' );

$check( false !== strpos( $inq, 'public static function adopt_current_inquiry' ), 'one-time current-inquiry adoption API exists' );
$check( false !== strpos( $inq, 'current_submission_binding_unresolved' ), 'adoption fails closed when an existing submission_id cannot be canonically resolved' );
$check( false !== strpos( $inq, 'transaction_already_bound' ), 'adoption refuses duplicate immutable transaction binding' );
$check( false !== strpos( $inq, 'previous_inquiry_not_adopted' ), 'repeat prepare fails closed when the previous inquiry was not durably adopted' );
$check( false === strpos( substr( $inq, strpos( $inq, 'public static function prepare_reconciliation' ), strpos( $inq, 'public static function commit_activation' ) - strpos( $inq, 'public static function prepare_reconciliation' ) ), 'snapshot_legacy_inquiry(' ), 'normal repeat prepare never snapshots previous attribution from mutable contact state' );

$check( false !== strpos( $inq, "'fluent_form' !== \$type" ) && false !== strpos( $inq, 'fluent_form_source_requires_form_id_and_entry_id' ), 'Fluent Forms inquiry source requires explicit form_id and entry_id' );
$check( false !== strpos( $inq, "'ff:' . \$form_id . ':' . \$entry_id" ), 'Ads Bridge computes canonical ff:{form}:{entry} transaction ID itself' );
$check( false !== strpos( $inq, 'project_new_inquiry_to_contact' ) && false !== strpos( $inq, "'submission_id'               => \$entry_id" ), 'activation projects the new immutable submission binding into the current contact view' );
$check( false !== strpos( $inq, "'qualified_at'                => ''" ) && false !== strpos( $inq, "'closed_date'                 => ''" ) && false !== strpos( $inq, "'invoiced_amount'             => ''" ), 'activation clears prior current-inquiry milestones and revenue before setting New' );
$check( false !== strpos( $inq, 'self::$reconciling = true' ), 'projection hooks are guarded during activation reset' );
$check( false !== strpos( $inq, 'sync_active_from_subscriber' ) && false !== strpos( $inq, 'Immutable' ), 'active inquiry mirrors mutable milestones without rebinding immutable submission identity' );

$check( false !== strpos( $inq, "state IN ('active','archived')" ), 'stage selectors retain archived inquiry candidates until exported' );
$check( false !== strpos( $cli, 'select_unledgered_inquiries' ), 'Qualified, Unqualified, and Won exporters have inquiry-aware candidate selection' );
$check( false !== strpos( $cli, 'SiteEngine_Ads_Bridge_Inquiries::contact_has_history' ), 'legacy contact fallback is suppressed after inquiry-aware attribution exists' );
$check( false !== strpos( $cli, 'SiteEngine_Ads_Bridge_Inquiries::resolver_for_inquiry' ), 'Google builders resolve inquiry rows through immutable inquiry binding' );
$check( false !== strpos( $cli, "'gclid', ! is_array( \$inquiry )" ) && false !== strpos( $cli, "'gbraid', ! is_array( \$inquiry )" ), 'inquiry exports disable mutable CRM click-ID fallback' );
$check( false !== strpos( $cli, 'private function inquiry_submission_identity' ), 'inquiry exports prefer identity captured on the immutable form submission' );

$check( 94 === count( $all_csv ) && 'Inquiry ID' === $all_csv[93], 'All_Leads adds Inquiry ID as append-only column 94' );
$check( false !== strpos( $cli, "94 === count( \$expected ) && 93 === count( \$actual )" ) && false !== strpos( $cli, 'append Inquiry ID header' ), 'existing 93-column All_Leads has a safe header-only 93-to-94 migration' );
$check( false !== strpos( $cli, "array( 'k' => 'inquiry_id', 'h' => 'Inquiry ID' )" ), 'runtime All_Leads header contract appends Inquiry ID' );
$check( 21 === count( $gdm_csv ) && 'Conversion action' === $gdm_csv[20], 'Google_Ads_Import remains unchanged at 21 columns' );
$check( false === strpos( implode( ',', $gdm_csv ), 'Inquiry ID' ), 'Inquiry ID is intentionally not added to Google Data Manager schema' );

$check( false !== strpos( $cli, 'inquiry-admin <migrate|adopt|inspect>' ), 'CLI exposes explicit inquiry migrate/adopt/inspect administration' );
$check( false !== strpos( $doctor, 'CRM Ops repeat-inquiry integration requires migration and acceptance' ), 'doctor distinguishes pending CRM Ops integration from existing settings' );
$check( false !== strpos( $doctor, 'STATUS_WARN' ) && false !== strpos( $doctor, 'inquiry table is not installed' ), 'missing inquiry table is a compatibility warning rather than breaking legacy exports' );

$check( false !== strpos( $readme, 'Google_Ads_Import' ), 'README retains Google import documentation' );
$check( false === strpos( $main, '987-654-3210' ) && false === strpos( $main, 'forbidden-live-spreadsheet-id' ), 'bootstrap contains no Golden CID or Sheet ID' );


echo "\nSUMMARY: {$passes} PASS, {$fails} FAIL\n";
exit( $fails ? 1 : 0 );
