<?php
/** Harness-only offline doubles. No candidate suites, WordPress, browser or database execution. */
namespace FluentCrm\App\Models { class Subscriber { public static $rows = array(); public static function find( $id ) { return self::$rows[$id] ?? null; } } }
namespace {
define( 'JX_LIBRARY_ONLY', true ); require dirname( __DIR__ ) . '/tools/acceptance/joint-ops.php';
function get_option( $key ) { return array( 'fixture-only-plugin' ); }
$count = 0;
function check_x( $v, $reason ) { global $count; if ( ! $v ) { throw new RuntimeException( $reason ); } $count++; }
function refuses_x( callable $f, $reason ) { try { $f(); } catch ( Throwable $e ) { check_x( true, $reason ); return; } throw new RuntimeException( 'did not refuse: ' . $reason ); }
class X_DB {
    public $prefix = 'fixture_', $last_error = '', $writes = 0, $foreign = 0, $fk = array();
    public $row = array( 'id' => 1, 'form_id' => 3, 'response' => '{"_se_consent_server":{"real":"untouched"}}', 'form_fields' => 'original' );
    public function prepare( $sql, ...$args ) { foreach ( $args as $a ) { $sql = preg_replace( '/%[ds]/', (string) $a, $sql, 1 ); } return $sql; }
    public function esc_like( $s ) { return $s; }
    public function get_row( $q, $f = null ) { return $this->row; }
    public function get_results( $q, $f = null ) { return strpos( $q, 'KEY_COLUMN_USAGE' ) !== false ? $this->fk : array(); }
    public function get_col( $q ) { return array(); }
    public function get_var( $q ) { return strpos( $q, 'DATABASE()' ) !== false ? 'fixture_db' : (string) $this->foreign; }
    public function update( ...$a ) { $this->writes++; return 1; }
    public function delete( ...$a ) { $this->writes++; return 1; }
}
$original = array( '_se_consent_server' => array( 'signature' => 'genuine-unchanged-in-storage', 'contract_version' => 1 ), 'business' => 'preserved' );
foreach ( array( 'pending', 'failed', 'null', 'malformed', 'unknown', 'unsigned', 'negative' ) as $mode ) {
    $copy = jx_fault_response( $original, $mode, 'conflicting-grant', array( 'state' => 'verified', 'consent' => array( 'advertising' => false ) ) );
    check_x( $original['_se_consent_server']['contract_version'] === 1 && $copy['business'] === 'preserved', 'fault copy preserves genuine input/business fields' );
    check_x( $copy['se_consent_receipt_id'] === 'conflicting-grant', 'conflicting flat grant present in fault' );
    check_x( $mode === 'unsigned' ? ! array_key_exists( '_se_consent_server', $copy ) : array_key_exists( '_se_consent_server', $copy ), 'presence versus unsigned distinguished' );
}
refuses_x( function() use ( $original ) { jx_fault_response( $original, 'invented', 'x' ); }, 'unknown fault' );
$db = new X_DB(); $view = new JX_Read_View( $db ); $view->entry = 1; $view->response = '{"overlay":true}';
check_x( $view->get_row( 'SELECT * FROM fixture_fluentform_submissions WHERE id=1' )['response'] === $view->response, 'only request view changed' );
check_x( $db->row['response'] !== $view->response && $db->writes === 0, 'backing row and writes unchanged' );
$view->unavailable = true;
check_x( $view->get_results( 'SELECT * FROM fixture_se_consent_receipts' ) === null && $view->last_error !== '', 'unavailable read is unresolved not empty success' );
refuses_x( function() use ( $view ) { $view->query( 'DELETE FROM fixture' ); }, 'view query refuses mutation' );
refuses_x( function() use ( $view ) { $view->get_row( 'UPDATE fixture SET x=1' ); }, 'read facade refuses disguised writes' );
class X_Config { private $config = array( 'original' => true ); public function value() { return $this->config; } }
$config = new X_Config(); $GLOBALS['wpdb'] = $db; $db->last_error = 'original';
refuses_x( function() use ( $config ) { jx_scope( $config, function() use ( $config ) {
    $GLOBALS['wpdb'] = new X_DB(); $p = new ReflectionProperty( $config, 'config' ); $p->setAccessible( true ); $p->setValue( $config, array( 'fault' => true ) ); throw new RuntimeException( 'injected observation failure' );
} ); }, 'scope propagates observation failure' );
check_x( $GLOBALS['wpdb'] === $db && $db->last_error === 'original' && $config->value() === array( 'original' => true ), 'failure restores database/config/diagnostic state' );
$temp = sys_get_temp_dir() . '/joint-harness-' . bin2hex( random_bytes( 8 ) ); mkdir( $temp, 0700 );
$GLOBALS['jx_path'] = $temp . '/journal.json';
jx_save( $GLOBALS['jx_path'], array( 'pending' => array( 'operation' => 'adopt', 'event_id' => 'fixed-before-write' ) ) );
check_x( jx_json( $GLOBALS['jx_path'] )['pending']['event_id'] === 'fixed-before-write', 'durable pending identity retained before mutation' );
file_put_contents( $GLOBALS['jx_path'] . '.next', 'interrupted' );
set_error_handler( function() { throw new RuntimeException( 'file collision' ); } );
refuses_x( function() { jx_save( $GLOBALS['jx_path'], array() ); }, 'interrupted .next refuses overwrite' ); restore_error_handler();
check_x( jx_json( $GLOBALS['jx_path'] )['pending']['operation'] === 'adopt', 'earlier receipt survives secondary persistence failure' ); unlink( $GLOBALS['jx_path'] . '.next' );
$a = array( 'tables' => array( 'forms' => 'fixture_forms', 'submissions' => 'fixture_submissions', 'contacts' => 'fixture_contacts', 'details' => 'fixture_details', 'meta' => 'fixture_meta', 'pivot' => 'fixture_pivot', 'inquiries' => 'fixture_inquiries' ),
    'form_id' => 3, 'marker' => 'fixture_marker', 'logical_references' => array( array( 'table' => 'fixture_foreign_jobs', 'column' => 'subscriber_id', 'kind' => 'contact' ) ), 'reference_map_reviewed' => true );
$j = array( 'contacts' => array( 'A' => 9 ), 'entries' => array(), 'inquiries' => array(), 'audit' => array(), 'baseline' => array( 'form_fields' => 'original', 'active_plugins_sha256' => hash( 'sha256', serialize( get_option( '' ) ) ) ) );
\FluentCrm\App\Models\Subscriber::$rows[9] = (object) array( 'email' => 'fixture_marker.a@example.invalid' );
$db->last_error = ''; $db->foreign = 1;
refuses_x( function() use ( $a, &$j ) { jx_close( $a, $j ); }, 'foreign logical reference preserves resources' );
check_x( $db->writes === 0, 'foreign reference blocked before any destructive restoration/deletion' );
$db->fk = array( array( 'TABLE_SCHEMA' => 'foreign_db', 'TABLE_NAME' => 'fixture_foreign_jobs', 'COLUMN_NAME' => 'subscriber_id' ) );
refuses_x( function() use ( $a, &$j ) { jx_close( $a, $j ); }, 'foreign database reference stops cleanup' );
check_x( $db->writes === 0, 'cross database failure leaves form/resources intact' );
$stages = array( 'results' => array(), 'stages' => array( 'J2' => 'NOT RUN' ) );
refuses_x( function() use ( &$stages ) { jx_stage( 'J2', array(), $stages ); }, 'missing stage evidence refuses acceptance' );
foreach ( array( array( 'rejection', 'B', '-' ), array( 'fault', 'C', 'negative' ) ) as $r ) { $stages['results'][] = array( 'operation' => $r[0], 'label' => $r[1], 'argument' => $r[2], 'status' => 'PASS' ); }
foreach ( array( 'pending', 'failed', 'null', 'malformed', 'unknown', 'unsigned' ) as $mode ) { $stages['results'][] = array( 'operation' => 'fault', 'label' => 'A', 'argument' => $mode, 'status' => 'PASS' ); }
check_x( jx_stage( 'J2', array(), $stages )['status'] === 'PASS', 'complete simulated stage verdict positive control' );
check_x( hash_file( 'sha256', dirname( __DIR__ ) . '/tools/acceptance/joint-owned-observer.php' ) === 'bff8339e0a3faff075069f84e1f62e1e64eab4afb9b616d81bb8a851f6470f48', 'frozen observer unchanged' );
unlink( $GLOBALS['jx_path'] ); rmdir( $temp );
echo "PASS {$count} harness-only assertions; installed operations NOT RUN\n";
}
