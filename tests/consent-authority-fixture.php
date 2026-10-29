<?php
/** Actual pinned authority, loaded as a read-only external test input, never vendored. */
if ( ! defined( 'SEAB_TEST_NO_AUTHORITY' ) ) {
    $authority = getenv( 'SEAB_CONSENT_AUTHORITY' );
    $deps = array(
        $authority => '6d49bfe9006fc108d198dea7309b6dae88e123466d2e0767a2aa74391e4488c9',
        dirname( (string) $authority ) . '/class-siteengine-consent-continuity.php' => '660c83315e50ae6f159ddcb3f97f28b1f960c726bb5c9be62a0cdccd2507fe3d',
    );
    foreach ( $deps as $path => $hash ) {
        if ( ! $path || ! is_file( $path ) || hash_file( 'sha256', $path ) !== $hash ) {
            throw new RuntimeException( 'Provide exact Consent rc.2 1c0a420 authority/continuity via SEAB_CONSENT_AUTHORITY.' );
        }
        require_once $path;
    }
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) { define( 'DAY_IN_SECONDS', 86400 ); }
if ( ! function_exists( 'wp_salt' ) ) {
    function wp_salt( $scheme = 'auth' ) { return $GLOBALS['seab_test_signing_key'] ?? 'SYNTHETIC-ONLY-CONSENT-KEY'; }
}
function seab_fixture_receipt( $aud = 'granted', $personalization = 'denied' ) {
    $c = array( 'necessary' => true, 'analytics' => false,
        'advertising' => 'granted' === $aud, 'personalization' => 'granted' === $personalization );
    return array( 'id' => 7, 'receipt_id' => 'sc1_' . substr( hash( 'sha256', 'synthetic-existing-event' ), 0, 60 ),
        'method' => 'explicit_choice', 'policy_version' => '2026-05-08-v1', 'banner_version' => 'fixture-banner',
        'consent_json' => wp_json_encode( $c ), 'google_consent_json' => wp_json_encode( SiteEngine_Consent_Authority::google( $c ) ),
        'page_url' => 'https://example.test/form', 'referrer_url' => '', 'ip_hash' => null, 'user_agent_hash' => null,
        'created_at' => '2026-01-01 00:00:00', 'server_created_at' => '2026-01-01 00:00:00' );
}
function seab_fixture_stamp( array $row, $form_id = 3 ) {
    return SiteEngine_Consent_Authority::seal_stamp( array( 'contract_version' => 1, 'state' => 'verified',
        'receipt_id' => $row['receipt_id'], 'receipt_row_id' => (int) $row['id'],
        'receipt_digest' => SiteEngine_Consent_Authority::receipt_digest( $row ),
        'method' => $row['method'], 'policy_version' => $row['policy_version'], 'banner_version' => $row['banner_version'],
        'created_at' => $row['server_created_at'], 'captured_at' => $row['server_created_at'],
        'consent' => json_decode( $row['consent_json'], true ),
        'google_consent' => json_decode( $row['google_consent_json'], true ) ) + seab_fixture_continuity( $row ), $form_id );
}

/** Independent synthetic chains for historical tests; real authority integrity algorithms. */
function seab_fixture_continuity( array $row ) {
    $link = array( 'receipt_row_id' => (int) $row['id'], 'receipt_id' => $row['receipt_id'],
        'chain_id' => hash( 'sha256', $row['receipt_id'] . ':' . $row['id'] ), 'revision' => 1,
        'intent_hash' => hash( 'sha256', 'synthetic' ), 'receipt_digest' => SiteEngine_Consent_Authority::receipt_digest( $row ) );
    $head = array( 'chain_id' => $link['chain_id'], 'revision' => 1, 'expires_at' => time() + 86400,
        'consent_json' => $row['consent_json'], 'policy_version' => $row['policy_version'], 'banner_version' => $row['banner_version'] );
    $c = json_decode( $row['consent_json'], true );
    foreach ( array( 'analytics', 'advertising', 'personalization' ) as $k ) { $head[$k . '_barrier'] = $c[$k] ? 0 : 1; }
    $mac = new ReflectionMethod( 'SiteEngine_Consent_Continuity', 'mac' ); $mac->setAccessible( true );
    $link['integrity'] = $mac->invoke( null, $link ); $head['integrity'] = $mac->invoke( null, $head );
    $GLOBALS['seab_fixture_links'][(int) $row['id']] = $link;
    $GLOBALS['seab_fixture_heads'][$head['chain_id']] = $head;
    return SiteEngine_Consent_Continuity::extension( $link );
}
function seab_fixture_current_row( $sql ) {
    if ( preg_match( '/se_consent_events.*receipt_row_id = (\d+)/', $sql, $m ) ) { return $GLOBALS['seab_fixture_links'][(int) $m[1]] ?? null; }
    if ( preg_match( "/se_consent_chains.*chain_id = '([^']+)'/", $sql, $m ) ) { return $GLOBALS['seab_fixture_heads'][$m[1]] ?? null; }
    return null;
}
