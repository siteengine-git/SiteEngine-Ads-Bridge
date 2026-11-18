<?php
/** Historical inquiry reasons with a dedicated CRM mapping; real row builder, synthetic DB. */
define('SEAB_TEST_LIBRARY_ONLY', true);
define('SEAB_TEST_NO_AUTHORITY', true); // This row-shape test makes no Consent acceptance claim.
require __DIR__ . '/static-acceptance.php';
class ReasonMappingDB extends SEAB_Test_WPDB {
    public function get_col($sql) {
        if (strpos($sql,'SHOW TABLES LIKE')!==false) return ['wp_fluentform_submissions','wp_fluentform_entry_details'];
        if (strpos($sql,'SHOW COLUMNS')!==false) return ['id','form_id','response','status','created_at','updated_at','source_url','user_id','browser','device','ip','submission_id','field_name','field_value'];
        return [];
    }
    public function get_results($sql,$format=null) { return []; }
    public function get_row($sql,$format=null) { return null; }
}
$GLOBALS['wpdb']=new ReasonMappingDB();
$base=SiteEngine_Ads_Bridge_Config::instance();
$config=new class($base) {
    private $base;
    public function __construct($base){$this->base=$base;}
    public function get($k,$d=null){return $k==='fluentcrm.lifecycle_automation.unqualified_reason_field'?'se_ads_unqualified_reason':$this->base->get($k,$d);}
};
$submission=['id'=>24,'form_id'=>3,'response'=>'{}','status'=>'pending','created_at'=>'2026-11-13 09:00:00','updated_at'=>'2026-11-13 09:00:00','source_url'=>'https://example.test/form','user_id'=>0,'browser'=>'Fixture','device'=>'desktop','ip'=>'192.0.2.1'];
$cli=new SiteEngine_Ads_Bridge_CLI();
$inquiry=['inquiry_id'=>'00000000-0000-4000-8000-000000000092','submission_id'=>24,'lifecycle'=>'unqualified','customer_rating'=>'Wrong Area'];
foreach(['Wrong Area','']as$reason){
    $inquiry['customer_rating']=$reason;
    $row=invoke_private($cli,'build_all_leads_row',[$submission,'wp_fluentform_submissions',$config,'Unqualified','2026-11-13 09:01:00',$inquiry]);
    $indexed=array_combine(array_column($row['spec'],'k'),array_column($row['spec'],'v'));
    check($indexed['customer_rating']===$reason,'historical inquiry reason wins with dedicated mapping, including blank');
    check(count($row['values'])===94,'existing All Leads column contract retained');
}
check($GLOBALS['seab_http_requests']===[],'row building makes no transport requests');
echo "SUMMARY: {$passes} PASS, {$fails} FAIL; actual row builder, synthetic DB; no Consent or live delivery claim\n";
exit($fails?1:0);
