<?php
// Isolated settings-mode resolver acceptance.
define( 'ABSPATH', __DIR__ . '/' );
define( 'SEAB_CONFIG_IS_EXTERNAL', false );
define( 'SEAB_CONFIG_FILE', __DIR__ . '/does-not-exist.php' );
$GLOBALS['seab_test_options'] = array(
    'seab_settings_v2' => array(
        'schema_version' => 2,
        'client' => array(
            'slug' => 'practice',
            'name' => 'Old Name',
            'short_name' => 'Practice',
            'timezone' => 'UTC',
            'currency' => 'EUR',
            'google_ads_customer_id' => '000-000-0000',
        ),
        'fluent_forms' => array( 'form_id' => 3, 'fields' => array() ),
        'fluentcrm' => array(
            'lists' => array( 'customers' => 'Customers', 'future_customers' => 'Future Customers', 'do_not_contact' => 'DNC' ),
            'tags' => array( 'lifecycle' => array(
                'new'=>'New','qualified'=>'Qualified','unqualified'=>'Unqualified','won'=>'Won',
            ) ),
            'lifecycle_automation' => array(
                'enabled'=>true,'unqualified_reason_field'=>'customer_rating','unqualified_at_field'=>'unqualified_at','stale_new_days'=>3,
            ),
        ),
        'exports' => array(
            'all_leads'=>array('enabled'=>false,'sheet_tab'=>'All_Leads'),
            'won_jobs'=>array('enabled'=>false,'sheet_tab'=>'Google_Ads_Import','conversion_action'=>'Won','value_field'=>'invoiced_amount','date_field'=>'closed_date'),
            'lost_leads'=>array('enabled'=>false),
        ),
        'google_sheets'=>array('enabled'=>false,'spreadsheet_id'=>'','credentials_path'=>'','value_input_option'=>'RAW'),
        'consent'=>array('required_policy_version'=>'2026-05-08-v1','require_ad_user_data'=>true),
        'diagnostics'=>array('test_profile_guards'=>false),
    ),
);
function get_option( $k, $d = false ) { return array_key_exists($k, $GLOBALS['seab_test_options']) ? $GLOBALS['seab_test_options'][$k] : $d; }
function update_option(){ return true; }
function delete_option(){ return true; }
function sanitize_key($v){ return preg_replace('/[^a-z0-9_\-]/','', strtolower((string)$v)); }
function sanitize_text_field($v){ return trim((string)$v); }
function absint($v){ return abs((int)$v); }
function siteengine_get( $key, $default='' ) {
    $map = array(
        'business_name'=>'SiteEngine Test Co',
        'timezone'=>'America/Chicago',
        'currency'=>'USD',
        'google_ads_customer_id'=>'987-654-3210',
    );
    return isset($map[$key]) ? $map[$key] : $default;
}
require dirname(__DIR__) . '/includes/class-siteengine-ads-bridge-settings.php';
require dirname(__DIR__) . '/includes/class-siteengine-ads-bridge-config.php';
$c = SiteEngine_Ads_Bridge_Config::instance();
$tests = array(
    array($c->configuration_source()==='settings','settings option is the active authority'),
    array($c->get('client.slug')==='practice','plugin-owned client slug comes from Ads Bridge settings'),
    array($c->get('client.name')==='SiteEngine Test Co','Business Name is overlaid from Base'),
    array($c->get('client.timezone')==='America/Chicago','Timezone is overlaid from Base'),
    array($c->get('client.currency')==='USD','Currency is overlaid from Base'),
    array($c->get('client.google_ads_customer_id')==='987-654-3210','Google Ads CID is overlaid from Base'),
    array($c->provenance('client.name')==='SiteEngine Base','Base provenance is reported'),
    array($c->provenance('fluent_forms.form_id')==='Ads Bridge settings','plugin-owned settings provenance is reported'),
    array($c->get('fluent_forms.form_id')===3,'form ID remains plugin-owned'),
    array(empty($c->validate()),'complete settings-mode config validates'),
);
$p=0;$f=0;foreach($tests as $t){if($t[0]){$p++;echo "PASS: {$t[1]}\n";}else{$f++;echo "FAIL: {$t[1]}\n";}}
echo "\nSUMMARY: {$p} PASS, {$f} FAIL\n"; exit($f?1:0);
