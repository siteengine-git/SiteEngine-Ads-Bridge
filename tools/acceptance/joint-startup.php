<?php
/** WP-CLI --require verifier, loads the hash-verified retained cli-control.php before WordPress. */
function seab_joint_verify_startup_guard( $file, $expected_path, $expected_hash ) {
    if ( ! is_string( $file ) || $file !== $expected_path || ! is_file( $file ) || ! is_readable( $file )
        || is_link( $file ) || hash_file( 'sha256', $file ) !== $expected_hash ) {
        throw new RuntimeException( 'STOP before bootstrap: retained CLI guard omitted/unreadable/mismatched' );
    }
    return true;
}
if ( ! defined( 'SEAB_STARTUP_LIBRARY_ONLY' ) ) {
    if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) { throw new RuntimeException( 'CLI startup only' ); }
    $guard = '/home/alma.siteengine.space/.se-joint-acceptance/joint-consent-ads-20261031-01/cli-control.php';
    seab_joint_verify_startup_guard( $guard, $guard, 'f3702f4570a67ccdfb0973e3d3b0e4bc9411bf5b5fb33550e569382c562dde8f' );
    if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON !== true ) { throw new RuntimeException( 'STOP before bootstrap: conflicting cron flag' ); }
    require_once $guard;
    WP_CLI::add_hook( 'before_wp_load', static function() {
        if ( ! defined( 'DISABLE_WP_CRON' ) || DISABLE_WP_CRON !== true
            || has_filter( 'pre_http_request' ) === false || has_filter( 'pre_wp_mail' ) === false ) {
            throw new RuntimeException( 'STOP before bootstrap: retained guard not effective' );
        }
        define( 'SEAB_JOINT_PREBOOT_GUARD_VERIFIED', true );
    } );
}
