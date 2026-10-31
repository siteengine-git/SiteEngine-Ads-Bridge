<?php
/** Test tooling target identity only. No URL filters, option writes or Consent scope changes. */
function seab_joint_target_compare( array $allocated, array $actual ) {
    $fixed = array( 'server_hostname' => 'local.siteengine.cloud', 'owner' => 'almas9110',
        'document_root' => '/home/alma.siteengine.space/public_html', 'prefix' => 'wp_',
        'stored_home' => 'http://alma.siteengine.space', 'stored_siteurl' => 'http://alma.siteengine.space',
        'effective_home' => 'http://alma.siteengine.space/', 'browser_origin' => 'https://alma.siteengine.space' );
    foreach ( $fixed as $key => $value ) {
        if ( ( $allocated[$key] ?? null ) !== $value ) { throw new RuntimeException( 'allocated target identity mismatch: ' . $key ); }
    }
    if ( ! is_int( $allocated['owner_uid'] ?? null ) || $allocated['owner_uid'] < 1
        || ! preg_match( '/^[a-f0-9]{64}$/D', $allocated['database_name_sha256'] ?? '' )
        || ( $allocated['database_server_hostname'] ?? null ) !== $allocated['server_hostname'] ) { throw new RuntimeException( 'private target binding incomplete' ); }
    foreach ( array_merge( array_keys( $fixed ), array( 'owner_uid', 'database_name_sha256', 'database_server_hostname' ) ) as $key ) {
        if ( ( $actual[$key] ?? null ) !== $allocated[$key] ) { throw new RuntimeException( 'actual target identity mismatch: ' . $key ); }
    }
    return true;
}
function seab_joint_target( array $target ) {
    global $wpdb;
    $uid = posix_geteuid(); $owner = posix_getpwuid( $uid );
    $db = $wpdb->get_row( 'SELECT DATABASE() AS name, @@hostname AS host', ARRAY_A );
    if ( ! is_array( $db ) || $wpdb->last_error || ! is_string( $db['name'] ?? null ) ) { throw new RuntimeException( 'private database identity unavailable' ); }
    return seab_joint_target_compare( $target, array( 'server_hostname' => gethostname(), 'owner' => $owner['name'] ?? null,
        'owner_uid' => $uid, 'document_root' => realpath( ABSPATH ), 'prefix' => $wpdb->prefix,
        'database_name_sha256' => hash( 'sha256', $db['name'] ), 'database_server_hostname' => $db['host'],
        'stored_home' => get_option( 'home' ), 'stored_siteurl' => get_option( 'siteurl' ),
        'effective_home' => home_url( '/' ), 'browser_origin' => $target['browser_origin'] ?? null ) );
}
