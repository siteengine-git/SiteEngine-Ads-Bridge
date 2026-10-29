<?php
/** Real rc.2 capture/read algorithms; synthetic SQL boundary and transport only. */
define( 'SEAB_TEST_LIBRARY_ONLY', true );
$fault = $argv[1] ?? '';
if ( $fault ) { define( 'SEAB_TEST_NO_AUTHORITY', true ); }
require __DIR__ . '/static-acceptance.php';
set_error_handler( static function( $n, $s, $f, $l ) { throw new ErrorException( $s, 0, $n, $f, $l ); } );
require dirname( __DIR__ ) . '/includes/class-siteengine-ads-bridge-runtime.php';
function home_url( $path = '' ) { return 'https://example.test' . $path; }
function sanitize_text_field( $v ) { return strip_tags( $v ); }
function esc_url_raw( $v ) { return $v; }
class WP_Error { public function __construct( ...$args ) { throw new RuntimeException( json_encode( $args ) ); } }
$external = dirname( getenv( 'SEAB_CONSENT_AUTHORITY' ) );
$adapter = dirname( $external ) . '/tests/fixtures/continuity-db.php';
if ( hash_file( 'sha256', $adapter ) !== 'fcdd6bf76e683b57aabaaeb1c6b385b7f35a7a9207fb5e851766b6925c8bf5a7' ) { throw new RuntimeException( 'wrong pinned SQL fixture' ); }
require $adapter;
if ( $fault ) {
    // Fault injection only. Historical verify/digest remain the actual hash-checked code.
    $bytes = file_get_contents( getenv( 'SEAB_CONSENT_AUTHORITY' ) );
    if ( hash( 'sha256', $bytes ) !== '6d49bfe9006fc108d198dea7309b6dae88e123466d2e0767a2aa74391e4488c9' ) { throw new RuntimeException( 'wrong authority' ); }
    eval( substr( str_replace( 'final class SiteEngine_Consent_Authority', 'final class SEAB_Pinned_Authority', $bytes ), 5 ) );
    class SEAB_Actual_Historical_Verifier {
        public static function verify_stamp( $s, $f ) { return SEAB_Pinned_Authority::verify_stamp( $s, $f ); }
        public static function receipt_digest( $r ) { return SEAB_Pinned_Authority::receipt_digest( $r ); }
        public static function google( $c ) { return SEAB_Pinned_Authority::google( $c ); }
    }
    if ( $fault === 'missing' ) { class SiteEngine_Consent_Authority extends SEAB_Actual_Historical_Verifier {} }
    else { class SiteEngine_Consent_Authority extends SEAB_Actual_Historical_Verifier {
        public static function current_eligibility( ...$args ) {
            global $fault;
            if ( $fault === 'exception' ) { throw new RuntimeException( 'injected' ); }
            $good = array( 'contract_version' => 2, 'available' => true, 'allowed' => true,
                'historical_verified' => true, 'reason' => 'eligible', 'checked_at' => gmdate( 'c' ),
                'event_revision' => 1, 'current_revision' => 1,
                'consent' => array( 'necessary' => true, 'analytics' => false, 'advertising' => true, 'personalization' => false ),
                'google_consent' => SEAB_Pinned_Authority::google( array( 'analytics' => false, 'advertising' => true, 'personalization' => false ) ) );
            switch ( $fault ) {
                case 'null': return null;
                case 'version': $good['contract_version'] = '2'; break;
                case 'available': $good['available'] = 1; break;
                case 'allowed': $good['allowed'] = 'true'; break;
                case 'flags': unset( $good['google_consent'] ); break;
                case 'missing-metadata': unset( $good['historical_verified'] ); break;
                case 'revision': $good['current_revision'] = 0; break;
            }
            return $good;
        }
    } }
}
class DispatchDB extends Receipt_Fixture_DB {
    public function esc_like( $s ) { return addcslashes( $s, '_%\\' ); }
    public $prefix = 'wp_', $responses = array(), $head_reads = 0, $readonly = '0';
    public function get_col( $sql ) {
        if ( is_array( $sql ) ) { $sql = $sql[0]; }
        if ( strpos( $sql, 'SHOW TABLES' ) !== false ) { return array( 'wp_fluentform_submissions' ); }
        return strpos( $sql, 'se_consent_receipts' ) !== false ? SiteEngine_Ads_Bridge_Consent::RECEIPT_COLUMNS : array( 'id', 'form_id', 'response' );
    }
    public function get_var( $q ) {
        if ( $q === 'SELECT @@read_only' ) { return $this->readonly; }
        if ( strpos( $q[0], 'SHOW TABLES' ) !== false ) { return 'wp_fluentform_submissions'; }
        return parent::get_var( $q );
    }
    public function get_row( $q, $format ) {
        if ( strpos( $q[0], 'fluentform_submissions' ) !== false ) {
            $id = (int) $q[1][0];
            if ( ! isset( $this->responses[$id] ) ) { return null; }
            return array( 'id' => $id, 'form_id' => 3, 'response' => $this->responses[$id] );
        }
        if ( strpos( $q[0], 'LOCK IN SHARE MODE' ) !== false ) { $this->head_reads++; }
        return parent::get_row( $q, $format );
    }
}
$wpdb = $GLOBALS['wpdb'] = new DispatchDB();
$cli = new SiteEngine_Ads_Bridge_CLI(); $config = SiteEngine_Ads_Bridge_Config::instance();
function payload_for( $stamp, $entry = 24 ) {
    global $wpdb;
    $wpdb->responses[$entry] = json_encode( array( '_se_consent_server' => $stamp, 'se_consent_receipt_id' => 'conflicting-grant' ) );
    $values = array_fill( 0, 21, '' ); $values[1] = 'ff:3:' . $entry; $values[16] = 'Granted'; $values[17] = 'Denied';
    return array( 'cid' => 13, 'txn' => $values[1], 'sink' => 'google_ads_import_converted_lead', 'values' => $values );
}
function dispatch( $item ) {
    global $cli;
    return invoke_private( $cli, 'google_sheets_append_row', array( 'synthetic-sheet', 'Google_Ads_Import', $item['values'], 'SYNTHETIC-TOKEN', 'RAW', $item ) );
}
function browser( $entry = 24 ) {
    return isset( SiteEngine_Ads_Bridge_Runtime::inject_confirmation_payload( array(), (object) array( 'id' => 3 ), array(), $entry, array() )['siteengine_ads_bridge'] );
}
if ( $fault ) {
    $state = json_decode( base64_decode( $argv[2] ), true );
    foreach ( array( 'rows', 'links', 'heads', 'responses' ) as $k ) { $wpdb->$k = $state[$k]; }
    if ( ! SiteEngine_Ads_Bridge_Consent::resolve( 24, 3, 'wp_fluentform_submissions', $wpdb )['verified'] ) {
        throw new RuntimeException( 'fault case lacks valid real historical proof' );
    }
    check( ! dispatch( $state['item'] )['ok'] && ! browser() && count( $GLOBALS['seab_http_requests'] ) === 0, 'injected malformed/exception result blocks both boundaries: ' . $fault );
    echo "RESULT: {$passes} PASS / {$fails} FAIL\n"; exit( $fails ? 1 : 0 );
}
$authority = new SiteEngine_Consent_Authority( array( 'cookie_days' => 390, 'policy_version' => '2026-05-08-v1', 'banner_version' => 'fixture-banner' ) );
function choice( $grant, $previous = null, $personal = false ) {
    global $authority;
    static $id = 0;
    $ack = $authority->save( array( 'event_id' => sprintf( '12345678-1234-4123-8123-%012d', ++$id ),
        'continuity_key' => hash( 'sha256', 'synthetic-browser' ), 'continuity_token' => $previous['continuity']['token'] ?? '',
        'policy_version' => '2026-05-08-v1', 'banner_version' => 'fixture-banner',
        'consent' => array( 'analytics' => false, 'advertising' => $grant, 'personalization' => $personal ) ), '', '' );
    return array( $ack, SiteEngine_Consent_Authority::seal_stamp( $authority->resolve( $ack ), 3 ) );
}
list( $a, $stamp ) = choice( true ); $prepared = payload_for( $stamp );
check( browser(), 'original grant releases browser payload using real rc.2 authority' );
check( dispatch( $prepared )['ok'] && count( $GLOBALS['seab_http_requests'] ) === 1, 'original grant reaches synthetic transport once' );
$state = array( 'rows' => $wpdb->rows, 'links' => $wpdb->links, 'heads' => $wpdb->heads, 'responses' => $wpdb->responses, 'item' => $prepared );
$original = $wpdb->rows; $body = $prepared['values'];
list( $w, $negative ) = choice( false, $a );
check( ! dispatch( $prepared )['ok'] && count( $GLOBALS['seab_http_requests'] ) === 1, 'prepare then committed withdrawal blocks dispatch without transport' );
check( ! browser(), 'withdrawal suppresses payload from canonical historical grant' );
check( $wpdb->rows[1] === $original[1] && $prepared['values'] === $body, 'original evidence and retry body remain unchanged' );
check( ! dispatch( $prepared )['ok'], 'queued retry of old body after withdrawal denied' );
list( $g, $new_stamp ) = choice( true, $w ); $new = payload_for( $new_stamp, 25 );
check( ! dispatch( $prepared )['ok'] && dispatch( $new )['ok'] && browser( 25 ), 're-grant allows newly captured submission only; old submission stays withdrawn' );
$before = count( $GLOBALS['seab_http_requests'] );
check( dispatch( $new )['ok'], 'first batch item dispatches while currently eligible' );
list( $w2 ) = choice( false, $g );
check( ! dispatch( $new )['ok'] && count( $GLOBALS['seab_http_requests'] ) === $before + 1, 'withdrawal between batch items blocks second append and queued retry' );
list( $g2, $s2 ) = choice( true, $w2 ); $latest = payload_for( $s2, 26 );
$reads = $wpdb->head_reads; dispatch( $latest ); dispatch( $latest );
check( $wpdb->head_reads === $reads + 2, 'each physical append uses a new current locking read; no positive cache' );
$wpdb->fail_select = true; $before = count( $GLOBALS['seab_http_requests'] );
check( ! dispatch( $latest )['ok'] && ! browser( 26 ) && count( $GLOBALS['seab_http_requests'] ) === $before, 'failed primary reads block both boundaries before transport' ); $wpdb->fail_select = false;
$wpdb->readonly = '1'; check( ! dispatch( $latest )['ok'] && ! browser( 26 ), 'read-only database context blocks advertising' ); $wpdb->readonly = '0';
\FluentCrm\App\Models\Subscriber::$current_dnc = true;
check( ! dispatch( $latest )['ok'], 'DNC changed after preparation blocks actual append' ); \FluentCrm\App\Models\Subscriber::$current_dnc = false;
$p = new ReflectionProperty( $config, 'config' ); $p->setAccessible( true ); $original_config = $p->getValue( $config );
$changed = $original_config; $changed['consent']['required_policy_version'] = 'different'; $p->setValue( $config, $changed );
check( ! dispatch( $latest )['ok'] && ! browser( 26 ), 'changed policy blocks actual dispatch and browser' ); $p->setValue( $config, $original_config );
$bad = $latest; $bad['txn'] = 'request-only'; check( ! dispatch( $bad )['ok'], 'noncanonical dispatch context denied' );
$bad = $latest; $bad['values'][17] = 'Granted'; check( ! dispatch( $bad )['ok'], 'personalization denial cannot be upgraded by prepared body' );
list( $p_ack, $p_stamp ) = choice( true, $g2, true ); $personal = payload_for( $p_stamp, 27 ); $personal['values'][17] = 'Granted';
check( dispatch( $personal )['ok'], 'effective personalization grant remains independently represented' );
list( $mixed ) = choice( true, $p_ack, false );
check( ! dispatch( $personal )['ok'], 'personalization-only withdrawal blocks stale granted retry body' );
$personal['values'][17] = 'Denied';
check( dispatch( $personal )['ok'], 'advertising still allowed with separately denied effective personalization' );
$subscriber = new \FluentCrm\App\Models\Subscriber( false, 'Won' ); $custom = $subscriber->custom_fields();
$custom['submission_id'] = '27'; $custom['invoiced_amount'] = '125'; set_subscriber_custom( $subscriber, $custom );
foreach ( array( 'build_google_ads_import', 'build_qualified_lead_import' ) as $method ) {
    $built = invoke_private( $cli, $method, array( $subscriber, $config, '12:00:00' ) );
    check( $built['exportable'] && $built['values'][17] === 'Denied', $method . ' uses real effective flags while retaining other gates' );
}
payload_for( $negative, 26 ); check( ! dispatch( $latest )['ok'] && ! browser( 26 ), 'negative stamp veto survives conflicting flat grant' );
$all = array( 'values' => array_fill( 0, 94, '' ), 'sink' => 'all_leads_sheet' );
check( dispatch( $all )['ok'], 'internal 94-column All_Leads transport remains independent of advertising permission' );
foreach ( array( 'missing', 'null', 'version', 'available', 'allowed', 'flags', 'exception', 'missing-metadata', 'revision' ) as $mode ) {
    $pipes = array(); $process = proc_open( array( PHP_BINARY, '-n', 'tests/consent-dispatch-acceptance.php', $mode, base64_encode( json_encode( $state ) ) ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
    if ( ! is_resource( $process ) ) { check( false, 'fault child created' ); continue; }
    fclose( $pipes[0] ); $out = stream_get_contents( $pipes[1] ); $err = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] ); $code = proc_close( $process );
    check( $code === 0 && $err === '' && strpos( $out, '1 PASS / 0 FAIL' ) !== false, 'isolated fault-response rejection with real historical verifier: ' . $mode );
}
echo "RESULT: {$passes} PASS / {$fails} FAIL (real rc.2; simulated SQL and transport, no native/WP claim)\n";
exit( $fails ? 1 : 0 );
