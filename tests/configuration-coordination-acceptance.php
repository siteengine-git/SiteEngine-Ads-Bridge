<?php
/** Deterministic failure/interleaving tests. No WordPress or real vendor writes. */
define('SEAB_INQUIRY_TEST_INCLUDE',true);
ob_start(); require __DIR__.'/inquiry-acceptance.php'; $bootstrap=ob_get_clean();
if ($fails) { echo $bootstrap; exit(1); }
$passes=0; $fails=0;

function guard_case() {
    seab_test_reset_coordination_request();
    $GLOBALS['wpdb']=new SEAB_Inquiry_Test_WPDB();
    $GLOBALS['seab_options']['seab_inquiry_schema_version']='2';
    foreach (array('connection_unusable'=>false,'held_locks'=>array(),'reconciling'=>false) as $name=>$value) {
        $p=new ReflectionProperty('SiteEngine_Ads_Bridge_Inquiries',$name); $p->setAccessible(true); $p->setValue(null,$value);
    }
    return SiteEngine_Ads_Bridge_Coordination::capability();
}
function batch_request($kind='create',$id='operation_one') {
    return SiteEngine_Ads_Bridge_Coordination::capability()+array('operation_id'=>$id,'plan_hash'=>str_repeat('a',64),
        'steps'=>array('field.one'=>array('kind'=>$kind,'intent_hash'=>str_repeat('b',64))));
}
function verified() { return array('state'=>'verified','hash'=>str_repeat('c',64)); }
function finish_guard($batch) { $batch->finish(static function(){ return str_repeat('d',64); }); }
function inspect_guard() { return SiteEngine_Ads_Bridge_Coordination::inspect(SiteEngine_Ads_Bridge_Coordination::capability()); }
function fresh_guard_session() {
    seab_test_reset_coordination_request(); $GLOBALS['wpdb']->coord_session++; $GLOBALS['wpdb']->locks=array();
}

$cap=guard_case(); $r=inspect_guard();
check_inq($r['complete'] && $r['state']==='none' && !$r['write_permission'],'complete empty inspection is an observation, never write permission');
foreach (array('prepared','activating') as $state) {
    guard_case(); $GLOBALS['wpdb']->rows=array(array('state'=>$state,'contract_version'=>'1'));
    $r=inspect_guard(); check_inq($r['complete'] && $r['state']==='present',$state.' is covered destination-wide');
    $called=false; $r=SiteEngine_Ads_Bridge_Coordination::batch(batch_request(),function()use(&$called){$called=true;});
    check_inq(!$called && $r['reason_code']==='coordination_pending_inquiry',$state.' prevents entry to vendor batch');
}
foreach (array('unexpected',null) as $state) {
    guard_case(); $GLOBALS['wpdb']->rows=array(array('state'=>$state,'contract_version'=>'1'));
    $r=inspect_guard(); check_inq(!$r['complete'] && $r['state']==='unknown','unexpected/null persisted inquiry state is unknown');
}
foreach (array('coord_missing','coord_missing_inquiry','coord_bad_column') as $flag) {
    guard_case(); $GLOBALS['wpdb']->$flag=true; $r=inspect_guard();
    check_inq(!$r['complete'] && $r['state']==='unknown',$flag.' physical schema is not an empty destination');
}
guard_case(); $GLOBALS['wpdb']->storage_engine='MyISAM';
check_inq(!inspect_guard()['complete'],'non-InnoDB inquiry storage is unsupported');
guard_case(); $GLOBALS['seab_options']['seab_inquiry_schema_version']='3';
check_inq(!inspect_guard()['complete'],'unsupported persisted inquiry schema option is unknown');
foreach (array('information_schema.TABLES','SHOW COLUMNS','SHOW INDEX','option_value','GROUP BY state') as $query) {
    guard_case(); $GLOBALS['wpdb']->coord_fail=$query; $r=inspect_guard();
    check_inq(!$r['complete'] && $r['state']==='unknown' && strpos(json_encode($r),'secret')===false,'failed/timed-out '.$query.' is captured immediately and redacted');
    check_inq($GLOBALS['wpdb']->last_error==='', 'later successful release cannot mask failed '.$query.' in the result');
}
foreach (array('contract_version','destination','scope','inquiry_schema','coordination_schema') as $key) {
    $request=guard_case(); $request[$key]='wrong'; $r=SiteEngine_Ads_Bridge_Coordination::inspect($request);
    check_inq(!$r['complete'] && $r['reason_code']==='coordination_identity_mismatch' && !$GLOBALS['wpdb']->coord_calls,$key.' mismatch is rejected before SQL');
}
guard_case(); $GLOBALS['wpdb']->coord_busy=true; $called=false;
$r=SiteEngine_Ads_Bridge_Coordination::batch(batch_request(),function()use(&$called){$called=true;});
check_inq(!$called && $r['reason_code']==='coordination_busy','independent owner busy prevents configuration write entry');
guard_case(); $GLOBALS['wpdb']->coord_pin_supported=false;
check_inq(inspect_guard()['reason_code']==='coordination_driver_unsupported' && !$GLOBALS['wpdb']->coord_calls,'unsupported reconnect suppression fails closed before database reads');
guard_case(); $held=SiteEngine_Ads_Bridge_Coordination::enter_reconciliation(); $called=false;
$r=SiteEngine_Ads_Bridge_Coordination::batch(batch_request(),function()use(&$called){$called=true;});
check_inq($held['ok'] && !$called && $r['reason_code']==='coordination_reentrant','reconciliation owns protection: configuration cannot enter');
SiteEngine_Ads_Bridge_Coordination::release();

guard_case(); $writes=0; $blocked=array(); $pin=false; $readback_protected=false;
$r=SiteEngine_Ads_Bridge_Coordination::batch(batch_request(),function($batch)use(&$writes,&$blocked,&$pin,&$readback_protected){
    $id=uuid_for(700);
    $adopt=array('contract_version'=>'1','event_id'=>'evt_adopt','site_instance_id'=>'test','contact_id'=>700,'inquiry_id'=>$id,'head_version'=>1);
    $prepare=array('contract_version'=>'1','event_id'=>'evt_prepare','site_instance_id'=>'test','contact_id'=>700,'previous_inquiry_id'=>null,'proposed_inquiry_id'=>$id,'expected_head_version'=>0,'request_id'=>'req','source'=>array('type'=>'fluent_form','form_id'=>3,'entry_id'=>37));
    $activation=array('contract_version'=>'1','event_id'=>'evt_activate','site_instance_id'=>'test','contact_id'=>700,'previous_inquiry_id'=>null,'proposed_inquiry_id'=>$id,'head_version'=>1,'reconciliation_id'=>'abr_test');
    foreach (array('adopt_current_inquiry'=>$adopt,'prepare_reconciliation'=>$prepare,'commit_activation'=>$activation) as $method=>$payload) $blocked[]=SiteEngine_Ads_Bridge_Inquiries::$method($payload)['status']==='retry';
    $blocked[]=!SiteEngine_Ads_Bridge_Inquiries::sync_active_from_subscriber((object)array('id'=>700));
    $batch->step('field.one',function()use(&$writes,&$pin){$writes++;$pin=$GLOBALS['wpdb']->coord_pinned;},function()use(&$readback_protected){
        $readback_protected=!SiteEngine_Ads_Bridge_Coordination::enter_reconciliation()['ok']; return verified();
    });
    finish_guard($batch);
});
check_inq($r['status']==='complete' && $writes===1 && !in_array(false,$blocked,true),'configuration protection blocks adoption, prepare, activation/resume and active sync mutations');
check_inq($pin && $readback_protected && !$GLOBALS['wpdb']->coord_pinned,'session remains pinned through inspection/write/readback/checkpoint and restores on confirmed release');
check_inq(inspect_guard()['state']==='none','completed reservation permits later reconciliation');

guard_case(); $writes=0; $request=batch_request();
$r=SiteEngine_Ads_Bridge_Coordination::batch($request,function($batch)use(&$writes){
    $batch->step('field.one',function()use(&$writes){$writes++;throw new RuntimeException('lost save response secret');},'verified');
});
$saved=$GLOBALS['wpdb']->coord_row;
check_inq($r['status']==='blocked' && $saved['state']==='inflight' && $writes===1,'interrupted vendor save retains durable exact inflight intent');
fresh_guard_session();
check_inq(inspect_guard()['state']==='present' && !SiteEngine_Ads_Bridge_Coordination::enter_reconciliation()['ok'],'process/session disappearance does not clear configuration recovery fence');
$wrong=$request; $wrong['operation_id']='rotated'; $called=false;
$r=SiteEngine_Ads_Bridge_Coordination::batch($wrong,function()use(&$called){$called=true;});
check_inq(!$called && $r['status']==='blocked' && $GLOBALS['wpdb']->coord_row===$saved,'another operation cannot bypass interrupted intent');
$r=SiteEngine_Ads_Bridge_Coordination::batch($request,function($batch)use(&$writes){$batch->step('field.one',function()use(&$writes){$writes++;},static function(){return array('state'=>'before');});});
check_inq($r['reason_code']==='coordination_uncertain_save' && $writes===1,'unknown create is never duplicated, even with unchanged-before observation');
$r=SiteEngine_Ads_Bridge_Coordination::batch($request,function($batch)use(&$writes){$batch->step('field.one',function()use(&$writes){$writes++;},'verified');finish_guard($batch);});
check_inq($r['status']==='complete' && $writes===1,'exact recovery reads back proven intended outcome and checkpoints without resubmission');
$r=SiteEngine_Ads_Bridge_Coordination::batch($request,function($batch)use(&$writes){$batch->step('field.one',function()use(&$writes){$writes++;},'verified');finish_guard($batch);});
check_inq($r['status']==='complete' && $writes===1,'completed exact retry verifies readback without another create');
$changed=$request; $changed['plan_hash']=str_repeat('e',64);
check_inq(SiteEngine_Ads_Bridge_Coordination::batch($changed,function(){})['reason_code']==='coordination_operation_conflict','same operation ID cannot change its plan');
$r=SiteEngine_Ads_Bridge_Coordination::batch(batch_request('reuse','operation_two'),function($batch){$batch->step('field.one',function(){},'verified');finish_guard($batch);});
check_inq($r['status']==='complete' && SiteEngine_Ads_Bridge_Coordination::batch($request,function(){})['reason_code']==='coordination_operation_retired','retired completed operation cannot become a new create after a later batch');

guard_case(); $writes=0; $request=batch_request('update');
SiteEngine_Ads_Bridge_Coordination::batch($request,function($batch){$batch->step('field.one',function(){throw new RuntimeException('crash');},'verified');});
fresh_guard_session(); $reads=0;
$r=SiteEngine_Ads_Bridge_Coordination::batch($request,function($batch)use(&$writes,&$reads){
    $batch->step('field.one',function()use(&$writes){$writes++;},function()use(&$reads){return ++$reads===1?array('state'=>'before'):verified();});finish_guard($batch);
});
check_inq($r['status']==='complete' && $writes===1 && $reads===2,'exact update recovery can retry only after proving unchanged-before and then verifying readback');

guard_case(); $writes=0;
$r=SiteEngine_Ads_Bridge_Coordination::batch(batch_request(),function($batch)use(&$writes){
    $GLOBALS['wpdb']->coord_session++;
    $batch->step('field.one',function()use(&$writes){$writes++;},'verified');
});
check_inq($r['status']==='blocked' && $writes===0 && $GLOBALS['wpdb']->coord_row['state']==='reserved','stale/reconnected owner cannot cross a mutation boundary');
check_inq(inspect_guard()['reason_code']==='coordination_request_poisoned','connection loss poisons the rest of the PHP request');
guard_case(); $r=SiteEngine_Ads_Bridge_Coordination::batch(batch_request(),function($batch){
    $batch->step('field.one',function(){$GLOBALS['wpdb']->coord_session++;},'verified');
});
check_inq($r['status']==='blocked' && $GLOBALS['wpdb']->coord_row['state']==='inflight','connection loss during in-flight save preserves uncertain intent');
guard_case(); $r=SiteEngine_Ads_Bridge_Coordination::batch(batch_request(),function($batch){
    $batch->step('field.one',function(){},'verified');finish_guard($batch);$GLOBALS['wpdb']->coord_fail_release=true;
});
check_inq($r['reason_code']==='coordination_release_failed' && !inspect_guard()['complete'],'release failure withholds success and poisons request');
guard_case(); $GLOBALS['wpdb']->coord_transaction=1;
check_inq(!inspect_guard()['complete'],'caller transaction prevents a reservation that could roll back');
guard_case(); $GLOBALS['wpdb']->coord_durable=2;
check_inq(!inspect_guard()['complete'],'non-durable journal flush configuration is unsupported without changing the server');
guard_case(); SiteEngine_Ads_Bridge_Coordination::batch(batch_request(),function(){});
check_inq(inspect_guard()['state']==='present' && !SiteEngine_Ads_Bridge_Coordination::enter_reconciliation()['ok'],'reserved batch before any vendor save remains a durable pending gate');
guard_case(); $GLOBALS['wpdb']->coord_fail='GET_LOCK'; $r=inspect_guard();
check_inq(!$r['complete'] && inspect_guard()['reason_code']==='coordination_request_poisoned','unknown lock-acquisition outcome discards session rather than leaking/reentering a possible lock');
guard_case(); $GLOBALS['wpdb']->lose_acquire_response=true;
$r=SiteEngine_Ads_Bridge_Inquiries::adopt_current_inquiry(array('contract_version'=>'1','event_id'=>'evt_unknown_lock','site_instance_id'=>'test','contact_id'=>700,'inquiry_id'=>uuid_for(700),'head_version'=>1));
check_inq($r['status']==='retry' && $r['reason_code']==='contact_lock_unavailable' && !$GLOBALS['wpdb']->locks && inspect_guard()['reason_code']==='coordination_request_poisoned','granted contact lock with lost response is discarded and cannot leak into a later transition');
guard_case(); $handle=null;
SiteEngine_Ads_Bridge_Coordination::batch(batch_request(),function($batch)use(&$handle){$handle=$batch;});
$stale=false; try {$handle->step('field.one',function(){},'verified');}catch(Throwable $e){$stale=true;}
check_inq($stale,'request-local handle cannot authorize mutation after its lock scope ends');
guard_case(); $saved_driver=$GLOBALS['wpdb']; $writes=0;
$r=SiteEngine_Ads_Bridge_Coordination::batch(batch_request(),function($batch)use(&$writes){
    $GLOBALS['wpdb']=new SEAB_Inquiry_Test_WPDB();
    $batch->step('field.one',function()use(&$writes){$writes++;},'verified');
});
check_inq($r['status']==='blocked' && $writes===0 && !$saved_driver->locks,'wpdb replacement cannot retain ownership; both involved sessions are discarded');
guard_case(); SiteEngine_Ads_Bridge_Coordination::batch(batch_request(),function(){});
$GLOBALS['wpdb']->coord_row['state']='alien';
check_inq(!inspect_guard()['complete'],'unexpected persisted coordination state cannot be reported empty');
guard_case(); $r=SiteEngine_Ads_Bridge_Coordination::batch(batch_request(),function(){throw new RuntimeException('coordination_customer_secret');});
check_inq($r['reason_code']==='coordination_operation_failed','reason allowlist does not echo arbitrary callback exception text');

echo "\nSUMMARY: {$passes} PASS, {$fails} FAIL\n";
exit($fails?1:0);
