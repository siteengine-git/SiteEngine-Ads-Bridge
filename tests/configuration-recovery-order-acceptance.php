<?php
/** Regression for the installed two-step counterexample; deterministic SQL only. */
define('SEAB_INQUIRY_TEST_INCLUDE',true);
ob_start();require __DIR__.'/inquiry-acceptance.php';$initial=ob_get_clean();
if($fails){echo $initial;exit(1);}$passes=0;$fails=0;
function order_fixture(){
    seab_test_reset_coordination_request();$GLOBALS['wpdb']=new SEAB_Inquiry_Test_WPDB();$GLOBALS['seab_options']['seab_inquiry_schema_version']='2';
    return SiteEngine_Ads_Bridge_Coordination::capability()+['operation_id'=>'two_step_recovery','plan_hash'=>str_repeat('a',64),'steps'=>['one'=>['kind'=>'create','intent_hash'=>str_repeat('b',64)],'two'=>['kind'=>'create','intent_hash'=>str_repeat('c',64)],'three'=>['kind'=>'create','intent_hash'=>str_repeat('d',64)]]];
}
function order_read($id){return ['state'=>'verified','hash'=>hash('sha256',$id)];}
$req=order_fixture();$writes=0;
$r=SiteEngine_Ads_Bridge_Coordination::batch($req,static function($batch)use(&$writes){
    $batch->step('one',static function()use(&$writes){$writes++;},static fn()=>order_read('one'));
    $batch->step('two',static function()use(&$writes){$writes++;throw new RuntimeException('lost response');},static fn()=>order_read('two'));
});
$saved=$GLOBALS['wpdb']->coord_row;$j=json_decode($saved['journal'],true);
check_inq($r['status']==='blocked' && $saved['state']==='inflight' && isset($j['receipts']['one']) && $j['inflight']==='two' && $writes===2,'preserved completed receipt and later uncertain intent');
seab_test_reset_coordination_request();$GLOBALS['wpdb']->coord_session++;
$r=SiteEngine_Ads_Bridge_Coordination::batch($req,static function($batch)use(&$writes){$batch->step('three',static function()use(&$writes){$writes++;},static fn()=>order_read('three'));});
check_inq($r['reason_code']==='coordination_inflight_requires_readback' && $writes===2 && $GLOBALS['wpdb']->coord_row===$saved,'uncompleted other step cannot bypass the uncertain step');
$r=SiteEngine_Ads_Bridge_Coordination::batch($req,static function($batch)use(&$writes){$batch->step('one',static function()use(&$writes){$writes++;},static fn()=>order_read('drift'));});
check_inq($r['reason_code']==='coordination_readback_drift' && $writes===2 && $GLOBALS['wpdb']->coord_row===$saved,'completed receipt drift blocks with pending journal unchanged');
$r=SiteEngine_Ads_Bridge_Coordination::batch($req,static function($batch)use(&$writes){
    $batch->step('one',static function()use(&$writes){$writes++;},static fn()=>order_read('one'));
    $batch->step('two',static function()use(&$writes){$writes++;},static fn()=>['state'=>'unknown']);
});
check_inq($r['reason_code']==='coordination_uncertain_save' && $writes===2 && $GLOBALS['wpdb']->coord_row===$saved,'earlier verified read does not permit retry of unknown create');
$r=SiteEngine_Ads_Bridge_Coordination::batch($req,static function($batch)use(&$writes){
    foreach(['one','two','three']as$id)$batch->step($id,static function()use(&$writes){$writes++;},static fn()=>order_read($id));
    $batch->finish(static fn()=>hash('sha256','final'));
});
check_inq($r['status']==='complete' && $writes===3 && $GLOBALS['wpdb']->coord_row['state']==='complete','ordered exact recovery verifies one and two without resave, creates only new third step');
$r=SiteEngine_Ads_Bridge_Coordination::batch($req,static function($batch)use(&$writes){
    foreach(['one','two','three']as$id)$batch->step($id,static function()use(&$writes){$writes++;},static fn()=>order_read($id));
    $batch->finish(static fn()=>hash('sha256','final'));
});
check_inq($r['status']==='complete' && $writes===3,'completed ordered replay never duplicates effects');
echo "SUMMARY: {$passes} PASS, {$fails} FAIL; SQL double, not native evidence\n";exit($fails?1:0);
