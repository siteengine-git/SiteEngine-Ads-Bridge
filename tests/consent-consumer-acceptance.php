<?php
/** Isolated consumer integration: real pinned authority, synthetic WP/storage only. */
define( 'SEAB_TEST_LIBRARY_ONLY', true );
$missing_api = ( $argv[1] ?? '' ) === '--missing-api';
if ( $missing_api ) { define( 'SEAB_TEST_NO_AUTHORITY', true ); }
require __DIR__ . '/static-acceptance.php';
set_error_handler( static function( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );
require dirname( __DIR__ ) . '/includes/class-siteengine-ads-bridge-runtime.php';
require dirname( __DIR__ ) . '/includes/class-siteengine-ads-bridge-inquiries.php';
function sanitize_text_field( $v ) { return strip_tags( $v ); }
function esc_url_raw( $v ) { return $v; }
class WP_Error { public function __construct( ...$args ) { throw new RuntimeException( json_encode( $args ) ); } }

class ConsentConsumerDB extends SEAB_Test_WPDB {
    public $rows = array();
    public $responses = array();
    public $insert_id = 0;
    public $writes = 0;
    public $details_reads = 0;
    public $missing_column = '';
    public $read_failure = false;
    public $queries = array();
    public function get_col( $sql ) {
        if ( strpos( $sql, 'SHOW COLUMNS' ) !== false && strpos( $sql, 'fluentform_submissions' ) !== false ) { return array( 'id', 'form_id', 'response' ); }
        if ( strpos( $sql, 'SHOW COLUMNS' ) !== false && strpos( $sql, 'se_consent_receipts' ) !== false ) {
            return array_values( array_diff( SiteEngine_Ads_Bridge_Consent::RECEIPT_COLUMNS, array( $this->missing_column ) ) );
        }
        return parent::get_col( $sql );
    }
    public function get_var( $sql ) {
        if ( strpos( $sql, 'SHOW TABLES LIKE' ) !== false && strpos( $sql, 'fluentform_submissions' ) !== false ) { return 'wp_fluentform_submissions'; }
        if ( strpos( $sql, 'GET_LOCK(' ) !== false || strpos( $sql, 'RELEASE_LOCK(' ) !== false ) { return '1'; }
        if ( strpos( $sql, 'fluentform_entry_details' ) !== false ) { $this->details_reads++; }
        return parent::get_var( $sql );
    }
    public function get_results( $sql, $format = null ) {
        $this->queries[] = $sql;
        if ( strpos( $sql, 'se_consent_receipts' ) === false ) { return array(); }
        if ( $this->read_failure ) { $this->last_error = 'synthetic unavailable'; return null; }
        preg_match( "/receipt_id = '([^']+)'/", $sql, $rid );
        preg_match( '/OR id = (\d+)/', $sql, $id );
        return array_slice( array_values( array_filter( $this->rows, static function( $r ) use ( $rid, $id ) {
            return ( isset( $rid[1] ) && ( $r['receipt_id'] ?? '' ) === $rid[1] )
                || ( isset( $id[1] ) && (string) ( $r['id'] ?? '' ) === $id[1] );
        } ) ), 0, 2 );
    }
    public function get_row( $sql, $format = null ) {
        if ( preg_match( '/FROM `?wp_se_consent_receipts`? WHERE id = (\d+)/', $sql, $m ) ) {
            foreach ( $this->rows as $row ) { if ( (int) $row['id'] === (int) $m[1] ) { return $row; } }
            return null;
        }
        if ( strpos( $sql, 'fluentform_submissions' ) !== false && preg_match( '/WHERE id = (\d+)/', $sql, $m ) ) {
            if ( ! isset( $this->responses[(int) $m[1]] ) ) { return null; }
            $row = defined( 'SEAB_TEST_NO_AUTHORITY' ) ? array() : parent::submission_row();
            $row['id'] = (int) $m[1];
            $row['form_id'] = $this->submission_forms[$row['id']] ?? 0;
            $row['response'] = $this->responses[$row['id']];
            return $row;
        }
        return parent::get_row( $sql, $format );
    }
    public function insert( $table, $data, $formats = null ) {
        if ( $table !== 'wp_se_consent_receipts' ) { throw new RuntimeException( 'unexpected fixture write' ); }
        $data['id'] = ++$this->insert_id;
        $this->rows[] = $data;
        $this->writes++;
        return 1;
    }
    public function query( $sql ) { throw new RuntimeException( 'consumer attempted write' ); }
}
$wpdb = $GLOBALS['wpdb'] = new ConsentConsumerDB();
$cli = new SiteEngine_Ads_Bridge_CLI();
$config = SiteEngine_Ads_Bridge_Config::instance();
$binding = array( 'deterministic' => true, 'entry_id' => '24', 'form_id' => '3', 'submissions_table' => 'wp_fluentform_submissions' );
function consumer_result() {
    global $cli, $binding, $config;
    $r = invoke_private( $cli, 'resolve_consent_provenance', array( $binding, $config ) );
    $r['exportable'] = $r['decision'] === 'exportable';
    return $r;
}
function put_stamp( $stamp, $entry = 24 ) {
    global $wpdb;
    $response = json_decode( $wpdb->submission_row()['response'], true );
    $response['_se_consent_server'] = $stamp;
    $response['se_consent_receipt_id'] = 'flat-grant-claim';
    $wpdb->responses[$entry] = json_encode( $response );
}
if ( $missing_api ) {
    $data = json_decode( base64_decode( $argv[2] ), true );
    $wpdb->rows = $data['rows'];
    $wpdb->responses[24] = $data['response'];
    $r = consumer_result();
    check( ! $r['exportable'] && strpos( $r['block_reason'], 'API/signature' ) !== false, 'missing real authority fails closed' );
    echo "RESULT: {$passes} PASS / {$fails} FAIL\n";
    exit( $fails ? 1 : 0 );
}
$authority = new SiteEngine_Consent_Authority( array( 'cookie_days' => 180, 'policy_version' => '2026-05-08-v1', 'banner_version' => 'fixture-banner' ) );
function capture_choice( $advertising, $personalization = false ) {
    global $authority;
    static $event = 0;
    $ack = $authority->save( array( 'event_id' => sprintf( '12345678-1234-4123-8123-%012d', ++$event ),
        'policy_version' => '2026-05-08-v1', 'banner_version' => 'fixture-banner', 'method' => 'explicit_choice',
        'page_url' => 'https://example.test/form', 'referrer_url' => '',
        'consent' => array( 'analytics' => false, 'advertising' => $advertising, 'personalization' => $personalization ) ), '192.0.2.1', 'Synthetic UA' );
    $stamp = $authority->resolve( $ack );
    if ( $stamp['state'] !== 'verified' ) { throw new RuntimeException( 'real authority fixture generation failed' ); }
    return SiteEngine_Consent_Authority::seal_stamp( $stamp, 3 );
}
$grant = capture_choice( true );
$reject = capture_choice( false );
$personal = capture_choice( true, true );
$saved_rows = $wpdb->rows;
$writes = $wpdb->writes;
put_stamp( $grant );
check( consumer_result()['exportable'], 'actual save/resolve/seal grant accepted; later rejection does not rebind history' );
check( consumer_result()['ad_personalization'] === 'denied', 'personalization denial independent of ad_user_data grant' );
put_stamp( $reject );
check( ! consumer_result()['exportable'], 'actual rejection blocks even with later grant and flat/detail grant claims' );
put_stamp( $personal );
check( consumer_result()['exportable'] && consumer_result()['ad_personalization'] === 'granted', 'personalization grant preserved independently' );

foreach ( array( 'missing', 'pending', 'failed', 'tampered', 'unsigned_legacy', 'unverified', 'expired', 'ambiguous' ) as $state ) {
    put_stamp( SiteEngine_Consent_Authority::seal_stamp( $authority->negative( $state ), 3 ) );
    check( ! consumer_result()['exportable'], "signed negative {$state} forbids fallback" );
}
foreach ( array( null, false, 'bad', array(), array( 'receipt_id' => $grant['receipt_id'] ) ) as $i => $bad ) {
    put_stamp( $bad );
    check( ! consumer_result()['exportable'], "present malformed/null stamp {$i} forbids fallback" );
}
foreach ( array( 'contract_version' => 2, 'state' => 'pending', 'receipt_row_id' => '1', 'receipt_digest' => 'bad',
    'receipt_id' => 'legacy-id', 'consent' => array(), 'google_consent' => array(), 'method' => null ) as $key => $value ) {
    $bad = $grant; $bad[$key] = $value;
    put_stamp( SiteEngine_Consent_Authority::seal_stamp( $bad, 3 ) );
    check( ! consumer_result()['exportable'], "signed malformed/unsupported {$key} blocks" );
}
$bad = $grant; $bad['signature'][0] = $bad['signature'][0] === 'a' ? 'b' : 'a'; put_stamp( $bad );
check( ! consumer_result()['exportable'], 'altered signature blocks' );
$bad = $grant; unset( $bad['signature'] ); put_stamp( $bad );
check( ! consumer_result()['exportable'], 'missing signature blocks' );
put_stamp( SiteEngine_Consent_Authority::seal_stamp( $grant, 4 ) );
check( ! consumer_result()['exportable'], 'signed stamp from another form blocks' );
put_stamp( $grant ); $wpdb->submission_forms[24] = 4;
check( ! consumer_result()['exportable'], 'canonical row form mismatch blocks' );
$wpdb->submission_forms[24] = 3;
$GLOBALS['seab_test_signing_key'] = 'SYNTHETIC-ROTATED-KEY';
check( ! consumer_result()['exportable'], 'changed signing key blocks retained proof' );
unset( $GLOBALS['seab_test_signing_key'] );

foreach ( SiteEngine_Ads_Bridge_Consent::RECEIPT_COLUMNS as $column ) {
    $wpdb->rows = $saved_rows;
    $wpdb->rows[0][$column] = $column === 'id' ? 999 : 'altered';
    check( ! consumer_result()['exportable'], "digest/exact identity detects altered {$column}" );
    $wpdb->rows = $saved_rows; unset( $wpdb->rows[0][$column] );
    check( ! consumer_result()['exportable'], "incomplete row {$column} blocks" );
    $wpdb->rows = $saved_rows; $wpdb->missing_column = $column;
    check( ! consumer_result()['exportable'], "incomplete schema {$column} blocks" );
    $wpdb->missing_column = '';
}
$wpdb->rows = array(); check( ! consumer_result()['exportable'], 'missing exact receipt blocks' );
$wpdb->rows = $saved_rows; $duplicate = $saved_rows[0]; $duplicate['id'] = 900; $wpdb->rows[] = $duplicate;
check( ! consumer_result()['exportable'], 'reused receipt ID ambiguous; no newest-row selection' );
$wpdb->rows = $saved_rows; $duplicate = $saved_rows[0]; $duplicate['receipt_id'] = $reject['receipt_id']; $wpdb->rows[] = $duplicate;
check( ! consumer_result()['exportable'], 'duplicate row identity with different receipt blocks' );
$wpdb->rows = $saved_rows; $wpdb->read_failure = true;
check( ! consumer_result()['exportable'], 'failed receipt read blocks' );
$wpdb->read_failure = false; $wpdb->last_error = '';
$wpdb->responses[24] = json_encode( array( 'se_consent_receipt_id' => $grant['receipt_id'] ) );
check( ! consumer_result()['exportable'], 'absent stamp unsigned legacy flat claim blocks' );
$wpdb->responses[24] = '{'; check( ! consumer_result()['exportable'], 'malformed canonical JSON blocks' );
put_stamp( $grant );
$expired = $saved_rows[0]; $expired['created_at'] = $expired['server_created_at'] = '2000-01-01 00:00:00';
$wpdb->rows[0] = $expired; put_stamp( seab_fixture_stamp( $expired ) );
$expired_ack = invoke_private( $authority, 'acknowledgement', array( $expired, '12345678-1234-4123-8123-000000000001' ) );
check( $authority->resolve( $expired_ack )['state'] === 'expired', 'real authority browser ACK expires while historical stamp remains independently valid' );
check( consumer_result()['exportable'], 'historical signed receipt never uses browser ACK expiry/today' );
$proof = SiteEngine_Ads_Bridge_Consent::resolve( 24, 3, 'wp_fluentform_submissions', $wpdb );
check( $proof['revocation_check'] === 'unavailable', 'current revocation explicitly unresolved; no inferred latest choice' );
$wpdb->rows = $saved_rows; put_stamp( $grant );
$property = new ReflectionProperty( $config, 'config' ); $property->setAccessible( true ); $original = $property->getValue( $config );
foreach ( array( '', 'different-policy' ) as $policy ) {
    $changed = $original; $changed['consent']['required_policy_version'] = $policy; $property->setValue( $config, $changed );
    check( ! consumer_result()['exportable'], 'empty/mismatched required policy blocks' );
    $confirmation = SiteEngine_Ads_Bridge_Runtime::inject_confirmation_payload( array(), (object) array( 'id' => 3 ), array(), 24, array() );
    check( ! isset( $confirmation['siteengine_ads_bridge'] ), 'browser required policy gate agrees' );
}
$property->setValue( $config, $original );
$browser_data = array( 'email' => 'person@example.test', 'phone' => '8505550123', 'se_consent_receipt_id' => $grant['receipt_id'] );
check( isset( SiteEngine_Ads_Bridge_Runtime::inject_confirmation_payload( array(), (object) array( 'id' => 3 ), array(), 24, $browser_data )['siteengine_ads_bridge'] ), 'browser uses persisted signed grant' );
foreach ( array( $reject, null, $authority->negative( 'unverified' ) ) as $stamp ) {
    put_stamp( $stamp );
    check( ! isset( SiteEngine_Ads_Bridge_Runtime::inject_confirmation_payload( array(), (object) array( 'id' => 3 ), array(), 24, $browser_data )['siteengine_ads_bridge'] ), 'browser rejects negative/null evidence despite submitted grant claim' );
}
$all = invoke_private( $cli, 'build_all_leads_row', array( $wpdb->get_row( 'SELECT * FROM `wp_fluentform_submissions` WHERE id = 24' ), 'wp_fluentform_submissions', $config ) );
check( $all['exportable'] && count( $all['values'] ) === 94, 'blocked advertising does not suppress internal All_Leads reporting' );
put_stamp( $grant );
foreach ( array( false, true ) as $dnc ) {
    $subscriber = new \FluentCrm\App\Models\Subscriber( $dnc, 'Won' );
    $custom = $subscriber->custom_fields(); $custom['invoiced_amount'] = '125.00'; set_subscriber_custom( $subscriber, $custom );
    foreach ( array( 'build_google_ads_import', 'build_qualified_lead_import' ) as $method ) {
        $r = invoke_private( $cli, $method, array( $subscriber, $config, '12:00:00' ) );
        check( $r['exportable'] === ! $dnc, "{$method} retains current DNC gate=" . (int) $dnc );
    }
}
put_stamp( $reject, 25 );
foreach ( array( 'active', 'archived' ) as $state ) {
    $inquiry = array( 'id' => 77, 'state' => $state, 'form_id' => 3, 'submission_id' => 24, 'transaction_id' => 'ff:3:24',
        'qualified_at' => '2026-07-22 15:30:00', 'closed_date' => '2026-07-26', 'invoiced_amount' => '125.00', 'lifecycle' => 'won' );
    $bound = SiteEngine_Ads_Bridge_Inquiries::resolver_for_inquiry( $inquiry );
    $r = invoke_private( $cli, 'resolve_consent_provenance', array( $bound, $config ) );
    check( $bound['entry_id'] === '24' && $r['decision'] === 'exportable' && $r['receipt_id'] === $grant['receipt_id'], "{$state} inquiry keeps older canonical grant, not newer rejection" );
    $subscriber = new \FluentCrm\App\Models\Subscriber( false, 'Won' );
    $custom = $subscriber->custom_fields(); $custom['submission_id'] = '25'; set_subscriber_custom( $subscriber, $custom );
    foreach ( array( 'build_google_ads_import', 'build_qualified_lead_import' ) as $method ) {
        $built = invoke_private( $cli, $method, array( $subscriber, $config, '12:00:00', $inquiry ) );
        check( $built['exportable'] && $built['order_id'] === 'ff:3:24', "{$method} {$state} ignores newer mutable CRM submission" );
    }
    $inquiry['submission_id'] = 25; $inquiry['transaction_id'] = 'ff:3:25';
    $r = invoke_private( $cli, 'resolve_consent_provenance', array( SiteEngine_Ads_Bridge_Inquiries::resolver_for_inquiry( $inquiry ), $config ) );
    check( $r['decision'] === 'blocked', "{$state} inquiry bound rejection stays denied" );
}
check( $wpdb->details_reads === 0, 'consumer never reads entry-detail fallback' );
check( $wpdb->writes === $writes && $wpdb->rows === $saved_rows, 'consumer performs no historical repair/backfill or receipt mutation' );
check( count( invoke_private( $cli, 'data_manager_headers', array( $config, 'won_jobs', '' ) ) ) === 21, 'Google import contract remains 21 columns' );
check( $GLOBALS['seab_http_requests'] === array(), 'no transport invocation in focused tests' );
// Missing API is a separate PHP process: never substitute a successful verifier.
put_stamp( $grant );
$payload = base64_encode( json_encode( array( 'rows' => $wpdb->rows, 'response' => $wpdb->responses[24] ) ) );
$pipes = array();
$child = proc_open( array( PHP_BINARY, '-n', 'tests/consent-consumer-acceptance.php', '--missing-api', $payload ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
if ( ! is_resource( $child ) ) { check( false, 'missing API subprocess starts' ); }
else { fclose( $pipes[0] ); $stdout = stream_get_contents( $pipes[1] ); $stderr = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] ); $exit = proc_close( $child );
    if ( $exit !== 0 || $stderr !== '' ) { echo "Missing API subprocess diagnostic: {$stdout}{$stderr}\n"; }
    check( $exit === 0 && strpos( $stdout, '1 PASS / 0 FAIL' ) !== false && $stderr === '', 'missing API subprocess explicitly passes its blocked assertion' ); }
echo "RESULT: {$passes} PASS / {$fails} FAIL (synthetic storage; actual pinned authority; no native SQL/WP claim)\n";
exit( $fails ? 1 : 0 );
