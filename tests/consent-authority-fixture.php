<?php
/** Actual pinned authority, loaded as a read-only external test input, never vendored. */
if ( ! defined( 'SEAB_TEST_NO_AUTHORITY' ) ) {
    $authority = getenv( 'SEAB_CONSENT_AUTHORITY' );
    if ( ! $authority || ! is_file( $authority ) || hash_file( 'sha256', $authority ) !==
        '7c3b419fcc2b33563dc0fde9f917ac44ad620b905bb7cd3a9ec4cfb35586fc54' ) {
        throw new RuntimeException( 'Provide exact Consent 37cb66d authority via SEAB_CONSENT_AUTHORITY; no verification stub permitted.' );
    }
    require_once $authority;
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
        'google_consent' => json_decode( $row['google_consent_json'], true ) ), $form_id );
}
