<?php
/**
 * Uninstall cleanup for SiteEngine Ads Bridge.
 *
 * The append-only export ledger is intentionally preserved as attribution and
 * recovery evidence. Uninstall removes plugin-owned options only; it never drops
 * tables or rewrites historical export records.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$option_names = array(
	'seab_version',
	'seab_last_doctor_report',
	'seab_last_run_at',
	'seab_last_run_status',
	'seab_activation_error',
	'seab_settings_v2',
);

foreach ( $option_names as $option_name ) {
	delete_option( $option_name );
	delete_site_option( $option_name );
}
