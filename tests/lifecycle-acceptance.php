<?php
/**
 * Dependency-free lifecycle automation acceptance for 0.5.2.
 * Run: php tests/lifecycle-acceptance.php
 */
define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );

global $seab_actions;
$seab_actions = array();
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
    global $seab_actions;
    $seab_actions[] = array( $hook, $callback, $priority, $accepted_args );
}
function absint( $v ) { return abs( (int) $v ); }
function current_time( $format ) {
    if ( 'mysql' === $format ) { return '2026-10-23 09:30:00'; }
    if ( 'Y-m-d' === $format ) { return '2026-10-23'; }
    return '';
}
function wp_list_pluck( $list, $field ) {
    $out = array();
    foreach ( $list as $row ) { if ( isset( $row[ $field ] ) ) { $out[] = $row[ $field ]; } }
    return $out;
}

final class SiteEngine_Ads_Bridge_Config {
    private static $instance;
    private $data = array(
        'fluentcrm.lifecycle_automation.enabled' => true,
        'fluentcrm.lifecycle_automation.unqualified_reason_field' => 'customer_rating',
        'fluentcrm.lifecycle_automation.unqualified_at_field' => 'unqualified_at',
        'fluentcrm.lifecycle_automation.stale_new_days' => 3,
        'fluentcrm.tags.lifecycle.new' => 'New',
        'fluentcrm.tags.lifecycle.qualified' => 'Qualified',
        'fluentcrm.tags.lifecycle.unqualified' => 'Unqualified',
        'fluentcrm.tags.lifecycle.won' => 'Won',
        'fluent_forms.form_id' => 3,
        'exports.qualified_leads.date_field' => 'qualified_at',
        'exports.won_jobs.date_field' => 'closed_date',
        'exports.won_jobs.value_field' => 'invoiced_amount',
    );
    public static function instance() { if ( ! self::$instance ) { self::$instance = new self(); } return self::$instance; }
    public static function lifecycle_roles() { return array( 'new', 'qualified', 'unqualified', 'won' ); }
    public function get( $key, $default = '' ) { return array_key_exists( $key, $this->data ) ? $this->data[ $key ] : $default; }
}

class SEAB_Lifecycle_WPDB {
    public $prefix = 'wp_';
    private $tag_ids = array( 'New' => 1, 'Qualified' => 2, 'Unqualified' => 3, 'Won' => 4 );
    public function prepare( $query, ...$args ) {
        foreach ( $args as $arg ) {
            $rep = is_numeric( $arg ) ? (string) $arg : "'" . str_replace( "'", "''", (string) $arg ) . "'";
            $query = preg_replace( '/%[ds]/', $rep, $query, 1 );
        }
        return $query;
    }
    public function get_var( $sql ) {
        if ( false !== strpos( $sql, 'SHOW TABLES LIKE' ) ) { return 'wp_fc_tags'; }
        if ( preg_match( "/WHERE title = '([^']+)'/", $sql, $m ) && isset( $this->tag_ids[ $m[1] ] ) ) { return $this->tag_ids[ $m[1] ]; }
        return null;
    }
    public function get_results( $sql, $format = null ) { return array(); }
}
$GLOBALS['wpdb'] = new SEAB_Lifecycle_WPDB();

class SEAB_Test_Subscriber {
    public $id = 13;
    public $tags = array();
    public $attached = array();
    public $detached = array();
    public $custom = array();
    public function attachTags( $ids ) { $this->attached = array_merge( $this->attached, (array) $ids ); }
    public function detachTags( $ids ) { $this->detached = array_merge( $this->detached, (array) $ids ); }
    public function custom_fields() { return $this->custom; }
    public function syncCustomFieldValues( $values, $delete_other = true ) {
        foreach ( $values as $key => $value ) {
            if ( '' === $value ) {
                if ( $delete_other ) { unset( $this->custom[ $key ] ); }
            } else {
                $this->custom[ $key ] = $value;
            }
        }
    }
}

require dirname( __DIR__ ) . '/includes/class-siteengine-ads-bridge-lifecycle.php';

$passes = 0; $fails = 0;
$check = function( $ok, $msg ) use ( &$passes, &$fails ) {
    if ( $ok ) { $passes++; echo "PASS: {$msg}\n"; }
    else { $fails++; echo "FAIL: {$msg}\n"; }
};

SiteEngine_Ads_Bridge_Lifecycle::init();
$hooks = array_column( $GLOBALS['seab_actions'], 0 );
$check( in_array( 'fluent_crm/contact_added_by_fluentform', $hooks, true ), 'new-contact Fluent Forms lifecycle hook is registered' );
$check( in_array( 'fluent_crm/contact_added_to_tags', $hooks, true ), 'lifecycle tag hook is registered' );
$check( in_array( 'fluent_crm/contact_custom_data_updated', $hooks, true ), 'post-custom-field reconciliation hook is registered' );
$check( array( 'Wrong Area', 'Wrong Service', 'Wrong Product', 'Wrong Price', 'Spam / Junk', 'Other' ) === SiteEngine_Ads_Bridge_Lifecycle::unqualified_reason_values(), 'canonical Unqualified Reason values are locked' );

$new = new SEAB_Test_Subscriber();
SiteEngine_Ads_Bridge_Lifecycle::on_contact_added_by_fluentform( $new, null, (object) array( 'id' => 3 ), null );
$check( array( 1 ) === $new->attached, 'configured master form seeds the New lifecycle tag by ID' );

$qualified = new SEAB_Test_Subscriber();
SiteEngine_Ads_Bridge_Lifecycle::on_contact_added_to_tags( $qualified, array( 2 ) );
$check( '2026-10-23 09:30:00' === $qualified->custom['qualified_at'], 'Qualified automatically stamps qualified_at' );
$check( in_array( 1, $qualified->detached, true ) && in_array( 3, $qualified->detached, true ) && in_array( 4, $qualified->detached, true ), 'Qualified removes competing lifecycle tags' );

$unqualified = new SEAB_Test_Subscriber();
$unqualified->custom = array( 'qualified_at' => '2026-10-22 14:00:00' );
SiteEngine_Ads_Bridge_Lifecycle::on_contact_added_to_tags( $unqualified, array( 3 ) );
$check( '2026-10-23 09:30:00' === $unqualified->custom['unqualified_at'], 'Unqualified automatically stamps unqualified_at' );
$check( '' === ( $unqualified->custom['qualified_at'] ?? '' ), 'Unqualified clears a stale Qualified milestone before export' );
$check( ! isset( $unqualified->custom['customer_rating'] ), 'plugin does not invent an Unqualified Reason' );

$won = new SEAB_Test_Subscriber();
SiteEngine_Ads_Bridge_Lifecycle::on_contact_added_to_tags( $won, array( 4 ) );
$check( '2026-10-23 09:30:00' === $won->custom['qualified_at'], 'direct New-to-Won automatically preserves the Qualified milestone' );
$check( '2026-10-23' === $won->custom['closed_date'], 'Won automatically stamps closed_date when blank' );
$check( ! isset( $won->custom['invoiced_amount'] ), 'plugin never invents invoice revenue' );

$won_existing = new SEAB_Test_Subscriber();
$won_existing->custom = array( 'qualified_at' => '2026-10-20 12:00:00', 'closed_date' => '2026-10-22', 'invoiced_amount' => '4500' );
SiteEngine_Ads_Bridge_Lifecycle::on_contact_added_to_tags( $won_existing, array( 4 ) );
$check( '2026-10-20 12:00:00' === $won_existing->custom['qualified_at'] && '2026-10-22' === $won_existing->custom['closed_date'], 'existing milestone dates are never overwritten' );
$check( '4500' === $won_existing->custom['invoiced_amount'], 'existing invoice amount is untouched' );

$won_sticky = new SEAB_Test_Subscriber();
$won_sticky->tags = array( (object) array( 'id' => 4 ) );
SiteEngine_Ads_Bridge_Lifecycle::on_contact_added_to_tags( $won_sticky, array( 2 ) );
$check( in_array( 2, $won_sticky->detached, true ) && ! in_array( 4, $won_sticky->detached, true ), 'Won remains terminal when a lower-stage tag is accidentally added' );


$won_save_order = new SEAB_Test_Subscriber();
$won_save_order->tags = array( (object) array( 'id' => 4 ) );
$won_save_order->custom = array( 'qualified_at' => '2026-10-23 09:30:00', 'closed_date' => '' );
SiteEngine_Ads_Bridge_Lifecycle::on_contact_custom_data_updated( array( 'closed_date' => '' ), $won_save_order, array( 'closed_date' => '' ) );
$check( '2026-10-23' === $won_save_order->custom['closed_date'], 'post-save reconciliation restores Won closed_date after a blank custom-field overwrite' );

$qualified_save_order = new SEAB_Test_Subscriber();
$qualified_save_order->tags = array( (object) array( 'id' => 2 ) );
$qualified_save_order->custom = array( 'qualified_at' => '' );
SiteEngine_Ads_Bridge_Lifecycle::on_contact_custom_data_updated( array( 'qualified_at' => '' ), $qualified_save_order, array( 'qualified_at' => '' ) );
$check( '2026-10-23 09:30:00' === $qualified_save_order->custom['qualified_at'], 'post-save reconciliation restores Qualified timestamp after a blank custom-field overwrite' );

echo "\nSUMMARY: {$passes} PASS, {$fails} FAIL\n";
exit( $fails ? 1 : 0 );
