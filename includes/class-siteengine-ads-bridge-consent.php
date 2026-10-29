<?php
/** Read-only historical Consent contract-v1 evidence. Never resolves current revocation. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SiteEngine_Ads_Bridge_Consent {
    /** Fresh current-primary decision; never retain a positive permission between calls. */
    public static function current( $entry_id, $form_id, $table, $db, $required_policy ) {
        $out = self::resolve( $entry_id, $form_id, $table, $db );
        $out['allowed'] = false;
        $out['current_google'] = array();
        $out['revocation_check'] = 'blocked';
        if ( ! $out['verified'] ) { return $out; }
        try {
            // Unsupported routing/cache drop-ins cannot establish primary evidence here.
            if ( defined( 'WP_CONTENT_DIR' ) && is_file( WP_CONTENT_DIR . '/db.php' ) ) {
                throw new RuntimeException( 'database routing drop-in is not qualified' );
            }
            if ( (string) $db->get_var( 'SELECT @@read_only' ) !== '0' || ! empty( $db->last_error ) ) {
                throw new RuntimeException( 'writable primary database unavailable' );
            }
            if ( ! is_callable( array( 'SiteEngine_Consent_Authority', 'current_eligibility' ) ) ) {
                throw new RuntimeException( 'current eligibility API missing' );
            }
            global $wpdb;
            if ( $db !== $wpdb ) { throw new RuntimeException( 'authority database context mismatch' ); }
            $r = SiteEngine_Consent_Authority::current_eligibility( $out['stamp'], (int) $form_id, (string) $required_policy );
            if ( ! is_array( $r ) || ( $r['contract_version'] ?? null ) !== 2
                || ( $r['available'] ?? null ) !== true || ( $r['allowed'] ?? null ) !== true
                || ( $r['historical_verified'] ?? null ) !== true || ( $r['reason'] ?? null ) !== 'eligible'
                || ! is_string( $r['checked_at'] ?? null )
                || ! preg_match( '/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\d(?:Z|\+00:00)$/D', $r['checked_at'] )
                || ! is_int( $r['event_revision'] ?? null ) || $r['event_revision'] < 1
                || ! is_int( $r['current_revision'] ?? null ) || $r['current_revision'] < $r['event_revision']
                || $r['event_revision'] !== ( $out['stamp']['event_revision'] ?? null )
                || ( $r['consent']['advertising'] ?? null ) !== true
                || ( $r['google_consent']['ad_user_data'] ?? null ) !== 'granted'
                || ! in_array( $r['google_consent']['ad_personalization'] ?? null, array( 'granted', 'denied' ), true ) ) {
                throw new RuntimeException( 'current eligibility denied, unavailable or malformed' );
            }
            foreach ( array( 'necessary', 'analytics', 'advertising', 'personalization' ) as $key ) {
                if ( ! is_bool( $r['consent'][$key] ?? null ) ) { throw new RuntimeException( 'current category malformed' ); }
            }
            if ( $r['consent']['necessary'] !== true
                || ! is_callable( array( 'SiteEngine_Consent_Authority', 'google' ) )
                || $r['google_consent'] !== SiteEngine_Consent_Authority::google( $r['consent'] ) ) {
                throw new RuntimeException( 'current effective flags inconsistent' );
            }
            $out['allowed'] = true;
            $out['revocation_check'] = 'eligible';
            $out['current_google'] = $r['google_consent'];
        } catch ( Throwable $error ) {
            $out['block_reason'] = 'consent blocked: ' . $error->getMessage();
        }
        return $out;
    }
    const RECEIPT_COLUMNS = array(
        'id', 'receipt_id', 'method', 'policy_version', 'banner_version',
        'consent_json', 'google_consent_json', 'page_url', 'referrer_url',
        'ip_hash', 'user_agent_hash', 'created_at', 'server_created_at',
    );

    /** Canonical persisted submission only; request/CRM/entry-detail claims are not authority. */
    public static function resolve( $entry_id, $form_id, $table, $db ) {
        $out = array( 'verified' => false, 'stamp_present' => null, 'receipt_id' => '',
            'receipt_row_id' => '', 'receipt_id_source' => 'none', 'row' => null,
            'block_reason' => '', 'revocation_check' => 'unavailable' );
        try {
            if ( $entry_id < 1 || $form_id < 1 || ! is_string( $table )
                || ! preg_match( '/^[A-Za-z0-9_]+$/D', $table ) || ! is_object( $db ) ) {
                throw new RuntimeException( 'canonical submission/form binding unavailable' );
            }
            $columns = $db->get_col( "SHOW COLUMNS FROM `{$table}`" );
            if ( ! empty( $db->last_error ) || ! is_array( $columns )
                || array_diff( array( 'id', 'form_id', 'response' ), $columns ) ) {
                throw new RuntimeException( 'canonical submission schema/read unavailable' );
            }
            $submission = $db->get_row( $db->prepare(
                "SELECT id, form_id, response FROM `{$table}` WHERE id = %d", $entry_id ), ARRAY_A );
            if ( ! empty( $db->last_error ) || ! is_array( $submission )
                || (string) ( $submission['id'] ?? '' ) !== (string) $entry_id
                || (string) ( $submission['form_id'] ?? '' ) !== (string) $form_id
                || ! is_string( $submission['response'] ?? null ) ) {
                throw new RuntimeException( 'canonical submission missing or form mismatch' );
            }
            $response = json_decode( $submission['response'], true );
            if ( ! is_array( $response ) || JSON_ERROR_NONE !== json_last_error() ) {
                throw new RuntimeException( 'canonical response malformed' );
            }
            $out['stamp_present'] = array_key_exists( '_se_consent_server', $response );
            if ( ! $out['stamp_present'] ) {
                throw new RuntimeException( 'unsigned legacy consent: no authoritative stored stamp; flat/details fallback disabled' );
            }
            $out['receipt_id_source'] = 'response._se_consent_server';
            $stamp = $response['_se_consent_server'];
            if ( ! is_array( $stamp ) || ( $stamp['contract_version'] ?? null ) !== 1
                || ( $stamp['state'] ?? null ) !== 'verified'
                || ( $stamp['form_id'] ?? null ) !== $form_id
                || ! is_int( $stamp['receipt_row_id'] ?? null ) || $stamp['receipt_row_id'] < 1
                || ! is_string( $stamp['receipt_id'] ?? null )
                || ! preg_match( '/^sc1_[a-f0-9]{60}$/D', $stamp['receipt_id'] )
                || ! is_string( $stamp['receipt_digest'] ?? null )
                || ! preg_match( '/^[a-f0-9]{64}$/D', $stamp['receipt_digest'] ) ) {
                throw new RuntimeException( 'present consent stamp negative, malformed or unsupported; fallback forbidden' );
            }
            foreach ( array( 'method', 'policy_version', 'banner_version', 'created_at', 'captured_at' ) as $field ) {
                if ( ! is_string( $stamp[ $field ] ?? null ) ) {
                    throw new RuntimeException( 'stored consent stamp structure invalid' );
                }
            }
            $categories = $stamp['consent'] ?? null;
            foreach ( array( 'necessary', 'analytics', 'advertising', 'personalization' ) as $key ) {
                if ( ! is_array( $categories ) || ! is_bool( $categories[ $key ] ?? null ) ) {
                    throw new RuntimeException( 'stored consent categories invalid' );
                }
            }
            if ( true !== $categories['necessary'] || ! is_array( $stamp['google_consent'] ?? null ) ) {
                throw new RuntimeException( 'stored consent values invalid' );
            }
            foreach ( array( 'ad_storage', 'analytics_storage', 'ad_user_data', 'ad_personalization',
                'personalization_storage', 'functionality_storage', 'security_storage' ) as $key ) {
                if ( ! in_array( $stamp['google_consent'][ $key ] ?? null, array( 'granted', 'denied' ), true ) ) {
                    throw new RuntimeException( 'stored Google consent structure invalid' );
                }
            }
            if ( ! is_callable( array( 'SiteEngine_Consent_Authority', 'verify_stamp' ) )
                || ! is_callable( array( 'SiteEngine_Consent_Authority', 'receipt_digest' ) )
                || true !== SiteEngine_Consent_Authority::verify_stamp( $stamp, $form_id ) ) {
                throw new RuntimeException( 'Consent authority API/signature unavailable or invalid' );
            }
            $out['receipt_id'] = $stamp['receipt_id'];
            $out['receipt_row_id'] = (string) $stamp['receipt_row_id'];
            $rtable = $db->prefix . 'se_consent_receipts';
            if ( ! preg_match( '/^[A-Za-z0-9_]+$/D', $rtable ) ) {
                throw new RuntimeException( 'invalid receipts table' );
            }
            $columns = $db->get_col( "SHOW COLUMNS FROM `{$rtable}`" );
            if ( ! empty( $db->last_error ) || ! is_array( $columns ) || array_diff( self::RECEIPT_COLUMNS, $columns ) ) {
                throw new RuntimeException( 'digest-bound receipt columns missing/read failed' );
            }
            // OR detects either reused receipt ID or duplicate/mismatched row identity.
            // A second matching identity is already ambiguous; no newest-row selection.
            $select = implode( ', ', self::RECEIPT_COLUMNS );
            $rows = $db->get_results( $db->prepare(
                "SELECT {$select} FROM `{$rtable}` WHERE receipt_id = %s OR id = %d LIMIT 2",
                $stamp['receipt_id'], $stamp['receipt_row_id'] ), ARRAY_A );
            if ( ! empty( $db->last_error ) || ! is_array( $rows ) || count( $rows ) !== 1 || ! is_array( $rows[0] ) ) {
                throw new RuntimeException( 'exact consent identity missing, ambiguous or read failed' );
            }
            $row = $rows[0];
            foreach ( self::RECEIPT_COLUMNS as $column ) {
                if ( ! array_key_exists( $column, $row ) || ( ! is_scalar( $row[ $column ] ) && null !== $row[ $column ] ) ) {
                    throw new RuntimeException( 'incomplete digest-bound receipt evidence' );
                }
            }
            if ( (string) $row['id'] !== (string) $stamp['receipt_row_id']
                || $row['receipt_id'] !== $stamp['receipt_id']
                || ! hash_equals( $stamp['receipt_digest'], SiteEngine_Consent_Authority::receipt_digest( $row ) ) ) {
                throw new RuntimeException( 'exact consent row identity/digest mismatch' );
            }
            foreach ( array( 'method', 'policy_version', 'banner_version' ) as $key ) {
                if ( $stamp[ $key ] !== $row[ $key ] ) {
                    throw new RuntimeException( 'stamp and receipt metadata mismatch' );
                }
            }
            $row_categories = json_decode( (string) $row['consent_json'], true );
            $row_google = json_decode( (string) $row['google_consent_json'], true );
            $stamp_google = $stamp['google_consent'];
            if ( ! is_array( $row_categories ) || ! is_array( $row_google ) ) {
                throw new RuntimeException( 'receipt choice structure invalid' );
            }
            ksort( $categories ); ksort( $row_categories );
            ksort( $stamp_google ); ksort( $row_google );
            if ( $stamp['created_at'] !== $row['server_created_at']
                || $categories !== $row_categories || $stamp_google !== $row_google ) {
                throw new RuntimeException( 'stamp and receipt choice mismatch' );
            }
            // Historical signature/row validation is NOT browser-ACK resolution.
            // No today-based ACK expiry, visitor inference, latest-choice lookup or writes.
            $out['row'] = $row;
            $out['stamp'] = $stamp;
            $out['verified'] = true;
        } catch ( Throwable $error ) {
            $out['block_reason'] = 'consent blocked: ' . $error->getMessage();
        }
        return $out;
    }
}
