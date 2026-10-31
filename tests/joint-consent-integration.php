<?php
/** New integration wiring only: real pinned Consent/Ads evidence algorithms, synthetic SQL/consumer boundaries. */
namespace FluentCrm\App\Models {
    class Subscriber {
        public static $rows = array(); public $id, $email, $lists = array(), $entry;
        public static function find( $id ) { return self::$rows[$id] ?? null; }
        public function custom_fields() { return array( 'submission_id' => $this->entry ); }
    }
}
namespace {
define( 'ABSPATH', '/home/alma.siteengine.space/public_html/' ); define( 'WP_CLI', true ); define( 'ARRAY_A', 'ARRAY_A' );
define( 'DAY_IN_SECONDS', 86400 ); define( 'SE_CONSENT_POLICY_VERSION', '2026-05-08-v1' ); define( 'SE_CONSENT_VERSION', '0.6.1-rc.2' );
define( 'JX_LIBRARY_ONLY', true );
function home_url( $p = '' ) { return 'https://alma.siteengine.space' . $p; }
function wp_json_encode( $v ) { return json_encode( $v, JSON_UNESCAPED_SLASHES ); }
function wp_salt( $s ) { return 'SYNTHETIC-INTEGRATION-ONLY-' . $s; }
function sanitize_text_field( $v ) { return trim( strip_tags( $v ) ); }
function esc_url_raw( $v ) { return $v; }
class WP_Error { public function __construct( ...$args ) {} }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
$external = $argv[1] ?? '';
foreach ( array( 'includes/class-siteengine-consent-authority.php' => '6d49bfe9006fc108d198dea7309b6dae88e123466d2e0767a2aa74391e4488c9',
    'includes/class-siteengine-consent-continuity.php' => '660c83315e50ae6f159ddcb3f97f28b1f960c726bb5c9be62a0cdccd2507fe3d',
    'tests/fixtures/continuity-db.php' => 'fcdd6bf76e683b57aabaaeb1c6b385b7f35a7a9207fb5e851766b6925c8bf5a7' ) as $file => $sha ) {
    if ( ! is_file( $external . '/' . $file ) || hash_file( 'sha256', $external . '/' . $file ) !== $sha ) { throw new \RuntimeException( 'exact retained Consent test dependency required' ); }
    require $external . '/' . $file;
}
require dirname( __DIR__ ) . '/includes/class-siteengine-ads-bridge-consent.php';
require dirname( __DIR__ ) . '/tools/acceptance/joint-ops.php';
class SiteEngine_Ads_Bridge_Config {
    private $config = array( 'consent.required_policy_version' => SE_CONSENT_POLICY_VERSION ); private static $instance;
    public static function instance() { return self::$instance ?? ( self::$instance = new self() ); }
    public function get( $k, $fallback = null ) { return $this->config[$k] ?? $fallback; }
}
class SiteEngine_Ads_Bridge_Inquiries { public static $rows = array(); public static function get( $id ) { return self::$rows[$id] ?? null; } }
function integration_current( $entry ) { global $wpdb; return SiteEngine_Ads_Bridge_Consent::current( $entry, 3, 'fixture_fluentform_submissions', $wpdb, SE_CONSENT_POLICY_VERSION ); }
class SiteEngine_Ads_Bridge_Runtime {
    public static function inject_confirmation_payload( $r, $form, $c, $entry, $data ) { return integration_current( $entry )['allowed'] ? array( 'siteengine_ads_bridge' => array() ) : array(); }
}
// These consumer/HTTP boundaries are doubles; no repeated full runtime-suite claim.
$filters = array();
function add_filter( $name, $fn, ...$unused ) { $GLOBALS['filters'][$name][] = $fn; }
function remove_filter( $name, $fn, ...$unused ) { foreach ( $GLOBALS['filters'][$name] as $k => $v ) { if ( $v === $fn ) { unset( $GLOBALS['filters'][$name][$k] ); } } }
class SiteEngine_Ads_Bridge_CLI {
    private function row( $s, $inquiry ) {
        $entry = $inquiry['submission_id'] ?? $s->entry; $r = integration_current( $entry ); $values = array_fill( 0, 21, '' );
        $values[1] = 'ff:3:' . $entry; $values[16] = 'Granted'; $values[17] = 'Granted';
        return array( 'exportable' => $r['allowed'], 'values' => $values );
    }
    private function build_qualified_lead_import( $s, $c, $time, $inquiry ) { return $this->row( $s, $inquiry ); }
    private function build_google_ads_import( $s, $c, $time, $inquiry ) { return $this->row( $s, $inquiry ); }
    private function build_all_leads_row( ...$unused ) { return array( 'exportable' => true, 'values' => array_fill( 0, 94, '' ) ); }
    private function google_sheets_append_row( $sheet, $tab, $values, $token, $vio, $context ) {
        $entry = (int) substr( $context['txn'], 5 ); if ( ! integration_current( $entry )['allowed'] ) { return array( 'ok' => false ); }
        foreach ( $GLOBALS['filters']['pre_http_request'] ?? array() as $fn ) { $fn( null, array(), 'https://sheets.googleapis.com/fixture:append?synthetic' ); }
        return array( 'ok' => true );
    }
}
class Integration_DB {
    public $prefix = 'fixture_', $last_error = '', $entries = array(), $seed;
    public function __construct( $seed ) { $this->seed = $seed; }
    public function prepare( $q, ...$args ) { foreach ( $args as $v ) { $q = preg_replace_callback( '/%[sd]/', static function( $m ) use ( $v ) { return $m[0] === '%d' ? (string) (int) $v : "'" . str_replace( "'", "''", (string) $v ) . "'"; }, $q, 1 ); } return $q; }
    public function get_row( $q, $format = null ) {
        $this->last_error = ''; if ( strpos( $q, 'se_consent_chains' ) !== false ) { return $this->seed->heads[array_key_first( $this->seed->heads )]; }
        preg_match( '/(?:receipt_row_id|id)\s*=\s*(\d+)/', $q, $m ); $id = (int) ( $m[1] ?? 0 );
        if ( strpos( $q, 'fluentform_submissions' ) !== false ) { return $this->entries[$id] ?? null; }
        if ( strpos( $q, 'se_consent_events' ) !== false ) { return $this->seed->links[$id] ?? null; }
        if ( strpos( $q, 'se_consent_receipts' ) !== false ) { return $this->seed->rows[$id] ?? null; }
        throw new \RuntimeException( 'unexpected synthetic read' );
    }
    public function get_results( $q, $format = null ) {
        $this->last_error = ''; preg_match( "/receipt_id\s*=\s*'([^']+)'/", $q, $m ); preg_match( '/OR id\s*=\s*(\d+)/', $q, $n );
        return array_values( array_filter( $this->seed->rows, static function( $r ) use ( $m, $n ) { return $r['receipt_id'] === ( $m[1] ?? '' ) || $r['id'] === (int) ( $n[1] ?? 0 ); } ) );
    }
    public function get_col( $q ) { return strpos( $q, 'receipts' ) !== false ? SiteEngine_Ads_Bridge_Consent::RECEIPT_COLUMNS : array( 'id', 'form_id', 'response' ); }
    public function get_var( $q ) { return $q === 'SELECT @@autocommit' ? '1' : '0'; }
}
set_error_handler( static function( $n, $s ) { throw new \ErrorException( $s, 0, $n ); } );
$wpdb = new Receipt_Fixture_DB(); $authority = new SiteEngine_Consent_Authority( array( 'cookie_days' => 390, 'policy_version' => SE_CONSENT_POLICY_VERSION, 'banner_version' => 'synthetic' ) );
$stamps = array(); $ack = null;
foreach ( array( 'A' => true, 'B' => false, 'C' => true ) as $label => $grant ) {
    $ack = $authority->save( array( 'event_id' => sprintf( '12345678-1234-4123-8123-%012d', count( $stamps ) + 1 ), 'continuity_key' => hash( 'sha256', 'one-synthetic-chain' ), 'continuity_token' => $ack['continuity']['token'] ?? '',
        'policy_version' => SE_CONSENT_POLICY_VERSION, 'banner_version' => 'synthetic', 'consent' => array( 'analytics' => false, 'advertising' => $grant, 'personalization' => $label === 'C' ) ), '', '' );
    if ( is_wp_error( $ack ) ) { throw new \RuntimeException( 'synthetic seed failed' ); } $stamps[$label] = SiteEngine_Consent_Authority::seal_stamp( $authority->resolve( $ack ), 3 );
}
$db = new Integration_DB( $wpdb ); $wpdb = $db;
$a = array( 'assignment' => 'ALMA-JOINT-CONSENT-ADS-20261031', 'site' => home_url( '/' ), 'prefix' => 'fixture_', 'form_id' => 3, 'marker' => 'jct_' . str_repeat( 'a', 32 ), '_sha256' => str_repeat( 'e', 64 ), 'run_dir' => '/home/alma.siteengine.space/.se-joint-acceptance/joint-consent-ads-20261031-01',
    'tables' => array( 'submissions' => 'fixture_fluentform_submissions', 'receipts' => 'fixture_se_consent_receipts', 'events' => 'fixture_se_consent_events', 'chains' => 'fixture_se_consent_chains' ) );
$j = array( 'assignment' => $a['assignment'], 'marker' => $a['marker'], 'allocation_sha256' => $a['_sha256'], 'entries' => array(), 'audit' => array(), 'contacts' => array( 'A' => 501, 'C' => 502 ), 'inquiries' => array(), 'chain_id' => $stamps['A']['chain_id'], 'pending' => array( 'operation' => 'fault', 'pins' => 'synthetic-independent' ) );
foreach ( $stamps as $label => $stamp ) {
    $id = 1000 + $stamp['receipt_row_id']; $response = wp_json_encode( array( 'se_joint_marker' => $a['marker'], 'se_joint_case' => $label, '_se_consent_server' => $stamp ) );
    $db->entries[$id] = array( 'id' => $id, 'form_id' => 3, 'response' => $response ); $j['entries'][$label] = array( 'id' => $id, 'contact' => $label === 'C' ? 'C' : 'A', 'response_sha256' => hash( 'sha256', $response ) );
    if ( $label !== 'B' ) { $j['inquiries'][$label] = 'fixture-' . $label; SiteEngine_Ads_Bridge_Inquiries::$rows['fixture-' . $label] = array( 'submission_id' => $id, 'contact_id' => $j['contacts'][$label], 'transaction_id' => 'ff:3:' . $id ); }
}
foreach ( $j['contacts'] as $label => $id ) { $s = new \FluentCrm\App\Models\Subscriber(); $s->id = $id; $s->email = $a['marker'] . '.' . strtolower( $label ) . '@example.invalid'; $s->entry = $j['entries'][$label]['id']; \FluentCrm\App\Models\Subscriber::$rows[$id] = $s; }
foreach ( $db->seed->rows as $id => $row ) { $j['audit'][(string) $id] = array( 'receipt_id' => $row['receipt_id'], 'sha256' => hash( 'sha256', wp_json_encode( $row ) ), 'link_sha256' => hash( 'sha256', wp_json_encode( $db->seed->links[$id] ) ) ); }
$j['head'] = array( 'revision' => 3, 'sha256' => hash( 'sha256', wp_json_encode( $db->seed->heads[$j['chain_id']] ) ) );
$temp = sys_get_temp_dir() . '/joint-integration-' . bin2hex( random_bytes( 8 ) ); mkdir( $temp, 0700 ); $GLOBALS['jx_path'] = $temp . '/journal.json';
$lock = fopen( $temp . '/operations.lock', 'x+' ); flock( $lock, LOCK_EX ); $GLOBALS['jx_operation_lock'] = $lock; jx_save( $GLOBALS['jx_path'], $j );
$count = 0; function integration_check( $ok, $message ) { global $count; if ( ! $ok ) { throw new \RuntimeException( $message ); } $count++; }
scj_context( $a ); $positive = jx_probe( $a, $j, 'C' ); jx_expect( $positive, true );
$j['prepared']['C'] = array( 'values' => $positive['values'], 'context' => $positive['context'] );
integration_check( jx_append( $j['prepared']['C'] )['synthetic_calls'] === 1, 'positive synthetic transport control' );
$j['missing_restore_required'] = true; $restored = jx_execute( 'restore', 'C', $a, $j );
integration_check( empty( $j['missing_restore_required'] ) && $restored['normal_current_readback'] === 'eligible', 'normal-request restoration handler positive readback; missing-plugin launch itself NOT RUN' );
foreach ( array( 'null', 'malformed', 'unknown', 'unsigned', 'tampered', 'pending', 'failed', 'unavailable-head', 'failed-current-read' ) as $mode ) {
    $a['_argument'] = $mode; $r = jx_execute( 'fault', 'C', $a, $j );
    integration_check( $r['restoration'] === 'VERIFIED' && $r['projection_hits'] > 0 && $r['result']['actual_consumer_browser_builders_dispatch_blocked'], 'actual integrated callback success and restoration: ' . $mode );
    integration_check( $wpdb === $db && scj_inspect( $a, $j, 'C' )['current']['allowed'], 'fresh positive original-state readback' );
}
\FluentCrm\App\Models\Subscriber::$rows[501]->entry = $j['entries']['B']['id']; $j['pointer_rebound'] = true;
jx_execute( 'rejection', 'B', $a, $j ); $a['_argument'] = 'conflicting-flat';
integration_check( jx_execute( 'fault', 'B', $a, $j )['restoration'] === 'VERIFIED', 'real negative B callbacks preserve no-fallback veto' );
try { jx_consent_fault( $a, $j, 'C', 'failed-current-read', static function() { throw new \RuntimeException( 'owned callback failure' ); } ); throw new \LogicException( 'must fail' ); }
catch ( JX_Stop $e ) { integration_check( $j['fault_failure']['primary'] === 'CALLBACK_FAILED' && $j['fault_failure']['restoration'] === 'VERIFIED', 'throwing callback retains separate verified restoration' ); }
integration_check( ! empty( jx_json( $GLOBALS['jx_path'] )['pending'] ) && scj_inspect( $a, $j, 'C' )['current']['allowed'], 'pending receipt survives callback exception and original-state readback' );
$head = $db->seed->heads[$j['chain_id']];
try { jx_consent_fault( $a, $j, 'C', 'failed-current-read', static function() use ( $db, $j ) { $db->seed->heads[$j['chain_id']]['revision']++; } ); throw new \LogicException( 'must fail' ); }
catch ( JX_Stop $e ) { integration_check( $j['fault_failure']['restoration'] === 'UNKNOWN_OR_FAILED' && ! empty( jx_json( $GLOBALS['jx_path'] )['pending'] ), 'failed final readback retains pending and never claims restoration' ); }
$db->seed->heads[$j['chain_id']] = $head; // Synthetic PHP object only; not a database operation.
integration_check( scj_inspect( $a, $j, 'A' )['current']['reason'] === 'withdrawn' && scj_inspect( $a, $j, 'B' )['current']['reason'] === 'denied' && scj_inspect( $a, $j, 'C' )['current']['reason'] === 'eligible', 'one chain three revisions, immutable old-event barriers' );
flock( $lock, LOCK_UN ); fclose( $lock ); unlink( $temp . '/operations.lock' ); unlink( $GLOBALS['jx_path'] ); rmdir( $temp );
echo "PASS {$count} integrated callback assertions; real Consent/Ads evidence, synthetic SQL and consumer/HTTP boundaries; installed NOT RUN\n";
}
