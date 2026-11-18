<?php
require_once __DIR__ . "/joint-target.php";
/** Local preparation only. Installed observer: SELECTs/pure builders, no fixtures/transport/cleanup. */
function seab_joint_ledger( $json ) {
    if ( ! is_string( $json ) || strlen( $json ) > 32768 ) { throw new RuntimeException( 'ledger byte limit' ); }
    $m = json_decode( $json, true );
    if ( ! is_array( $m ) || ( $m['assignment'] ?? '' ) !== 'ALMA-JOINT-CONSENT-ADS-20261031'
        || ! preg_match( '/^jct_[a-f0-9]{32}$/D', $m['marker'] ?? '' )
        || ( $m['site'] ?? '' ) !== 'https://alma.siteengine.space/'
        || ! preg_match( '/^[A-Za-z0-9_]{1,48}$/D', $m['prefix'] ?? '' )
        || ! is_int( $m['form_id'] ?? null ) || $m['form_id'] < 1
        || ! in_array( $m['phase'] ?? '', array( 'J1', 'J2', 'J3', 'J4', 'J5', 'J6' ), true )
        || ! is_array( $m['cases'] ?? null ) || count( $m['cases'] ) < 1 || count( $m['cases'] ) > 8 ) {
        throw new RuntimeException( 'ledger identity/shape invalid' );
    }
    $ids = array();
    foreach ( $m['cases'] as $case ) {
        if ( ! is_array( $case ) || ! is_int( $case['entry_id'] ?? null ) || $case['entry_id'] < 1
            || ! is_int( $case['contact_id'] ?? null ) || $case['contact_id'] < 1
            || isset( $ids[$case['entry_id']] )
            || ! preg_match( '/^[a-f0-9]{64}$/D', $case['original_stamp_sha256'] ?? '' )
            || ! array_key_exists( 'inquiry_id', $case )
            || ( null !== $case['inquiry_id'] && ! preg_match( '/^[a-f0-9-]{36}$/D', $case['inquiry_id'] ) ) ) {
            throw new RuntimeException( 'owned ID/evidence invalid' );
        }
        foreach ( array( 'current', 'browser', 'qualified', 'converted', 'dispatch_preflight' ) as $key ) {
            if ( ! is_bool( $case['expect'][$key] ?? null ) ) { throw new RuntimeException( 'explicit expected verdict required' ); }
        }
        $ids[$case['entry_id']] = true;
    }
    return $m;
}
function seab_joint_method( $object, $method, $args ) {
    $r = new ReflectionMethod( $object, $method ); $r->setAccessible( true ); return $r->invokeArgs( $object, $args );
}
function seab_joint_observe( array $m ) {
    if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_CLI' ) || ! WP_CLI
        || ! defined( 'SEAB_VERSION' ) || SEAB_VERSION !== '0.6.3.1-rc.1'
        || ! class_exists( 'SiteEngine_Ads_Bridge_CLI' ) || ! class_exists( 'SiteEngine_Consent_Authority' ) ) {
        throw new RuntimeException( 'requires separately authorized installed WP-CLI context' );
    }
    global $wpdb;
    if ( ! is_array( $m['target_context'] ?? null ) ) { throw new RuntimeException( 'independently verified target context required' ); }
    seab_joint_target( $m['target_context'] );
    if ( $wpdb->prefix !== $m['prefix'] ) { throw new RuntimeException( 'prefix mismatch' ); }
    // Stock primary/autocommit is a release prerequisite; fail rather than holding head locks across this run.
    foreach ( array( 'SELECT @@read_only' => '0', 'SELECT @@autocommit' => '1' ) as $sql => $expected ) {
        if ( (string) $wpdb->get_var( $sql ) !== $expected || $wpdb->last_error ) { throw new RuntimeException( 'primary/autocommit prerequisite failed' ); }
    }
    $config = SiteEngine_Ads_Bridge_Config::instance();
    if ( (int) $config->get( 'fluent_forms.form_id', 0 ) !== $m['form_id'] ) { throw new RuntimeException( 'master form mismatch' ); }
    if ( hash_file( 'sha256', SEAB_PLUGIN_DIR . 'CANDIDATE-MANIFEST.json' ) !== '33bde105e09bda298b15e52564888ec3fe82563901f18ce5c07561a086cb8ac7' ) {
        throw new RuntimeException( 'installed manifest hash mismatch' );
    }
    $package = json_decode( file_get_contents( SEAB_PLUGIN_DIR . 'CANDIDATE-MANIFEST.json' ), true );
    if ( ! is_array( $package ) || ( $package['source_commit'] ?? '' ) !== '2846305d19d550997295f85e58c7312b736925bd' ) { throw new RuntimeException( 'package provenance absent' ); }
    foreach ( $package['payload'] as $file ) {
        if ( ! preg_match( '/^[A-Za-z0-9_\/.\-]+$/D', $file['path'] ) || strpos( $file['path'], '..' ) !== false
            || hash_file( 'sha256', SEAB_PLUGIN_DIR . $file['path'] ) !== $file['sha256'] ) { throw new RuntimeException( 'installed Ads payload mismatch' ); }
    }
    foreach ( array( 'SiteEngine_Consent_Authority' => '6d49bfe9006fc108d198dea7309b6dae88e123466d2e0767a2aa74391e4488c9',
        'SiteEngine_Consent_Continuity' => '660c83315e50ae6f159ddcb3f97f28b1f960c726bb5c9be62a0cdccd2507fe3d' ) as $class => $hash ) {
        if ( hash_file( 'sha256', ( new ReflectionClass( $class ) )->getFileName() ) !== $hash ) { throw new RuntimeException( 'installed Consent payload mismatch' ); }
    }
    $cli = new SiteEngine_Ads_Bridge_CLI(); $checks = 0; $started = microtime( true );
    foreach ( $m['cases'] as $case ) {
        if ( microtime( true ) - $started > 10 ) { throw new RuntimeException( 'observation runtime exhausted; incomplete' ); }
        $table = $wpdb->prefix . 'fluentform_submissions';
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $case['entry_id'] ), ARRAY_A );
        $response = is_array( $row ) ? json_decode( $row['response'], true ) : null;
        if ( $wpdb->last_error || ! is_array( $response ) || (int) $row['form_id'] !== $m['form_id']
            || ( $response['se_joint_marker'] ?? null ) !== $m['marker']
            || ! array_key_exists( '_se_consent_server', $response )
            || hash( 'sha256', wp_json_encode( $response['_se_consent_server'] ) ) !== $case['original_stamp_sha256'] ) {
            throw new RuntimeException( 'fixture marker/form/original evidence mismatch' );
        }
        $contact = \FluentCrm\App\Models\Subscriber::find( $case['contact_id'] );
        if ( ! $contact || $contact->email !== $m['marker'] . '@example.invalid' ) { throw new RuntimeException( 'fixture contact provenance mismatch' ); }
        $inquiry = null;
        if ( null !== $case['inquiry_id'] ) {
            $inquiry = SiteEngine_Ads_Bridge_Inquiries::get( $case['inquiry_id'] );
            if ( ! is_array( $inquiry ) || (int) $inquiry['contact_id'] !== $case['contact_id']
                || (int) $inquiry['form_id'] !== $m['form_id'] || (int) $inquiry['submission_id'] !== $case['entry_id'] ) {
                throw new RuntimeException( 'owned inquiry binding mismatch' );
            }
        }
        $proof = SiteEngine_Ads_Bridge_Consent::current( $case['entry_id'], $m['form_id'], $table, $wpdb, (string) $config->get( 'consent.required_policy_version', '' ) );
        $browser = SiteEngine_Ads_Bridge_Runtime::inject_confirmation_payload( array(), (object) array( 'id' => $m['form_id'] ), array(), $case['entry_id'], $response );
        $qualified = seab_joint_method( $cli, 'build_qualified_lead_import', array( $contact, $config, '12:00:00', $inquiry ) );
        $converted = seab_joint_method( $cli, 'build_google_ads_import', array( $contact, $config, '12:00:00', $inquiry ) );
        $context = array( 'cid' => $case['contact_id'], 'txn' => 'ff:' . $m['form_id'] . ':' . $case['entry_id'], 'sink' => 'google_ads_import_converted_lead' );
        $preflight = seab_joint_method( $cli, 'advertising_dispatch_error', array( $converted['values'], $context ) );
        $actual = array( 'current' => $proof['allowed'], 'browser' => isset( $browser['siteengine_ads_bridge'] ),
            'qualified' => (bool) $qualified['exportable'], 'converted' => (bool) $converted['exportable'], 'dispatch_preflight' => $preflight === '' );
        foreach ( $actual as $key => $value ) {
            if ( $value !== $case['expect'][$key] ) { throw new RuntimeException( 'assertion failed at owned entry ' . $case['entry_id'] . ' gate ' . $key ); }
            $checks++;
        }
    }
    return array( 'assignment' => $m['assignment'], 'phase' => $m['phase'], 'checks' => $checks,
        'status' => 'OBSERVATIONS_MATCH', 'fixtures_created' => 0, 'transport_calls' => 0,
        'limit' => 'read-only observer only; not actual form/browser/network/cleanup acceptance' );
}
if ( ! defined( 'SEAB_JOINT_LIBRARY_ONLY' ) ) {
    try {
        $input = isset( $args ) ? $args : array_slice( $argv, 1 );
        if ( count( $input ) !== 3 || ! in_array( $input[0], array( 'validate', 'observe' ), true )
            || ! preg_match( '/^[a-f0-9]{64}$/D', $input[2] ) ) { throw new RuntimeException( 'mode, private-ledger path and independently pinned SHA256 required' ); }
        $bytes = file_get_contents( $input[1] );
        if ( hash( 'sha256', $bytes ) !== $input[2] ) { throw new RuntimeException( 'private ledger provenance mismatch' ); }
        $ledger = seab_joint_ledger( $bytes );
        echo json_encode( $input[0] === 'validate' ? array( 'status' => 'LEDGER_SHAPE_VALID_ONLY' ) : seab_joint_observe( $ledger ) ) . "\n";
    } catch ( Throwable $error ) {
        fwrite( STDERR, 'STOP: ' . $error->getMessage() . "\n" ); exit( 1 );
    }
}
