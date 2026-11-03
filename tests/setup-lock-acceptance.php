<?php
/** Actual destination lock admission; deterministic SQL boundary inherited. */
define('SEAB_INQUIRY_TEST_INCLUDE',true);ob_start();require __DIR__.'/inquiry-acceptance.php';$prior=ob_get_clean();
if($fails){echo $prior;exit(1);}$passes=0;$fails=0;
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


guard_case();$held=SiteEngine_Ads_Bridge_Coordination::enter_setup();check_inq($held['ok'],'ready storage admits administrator setup under destination lock');check_inq(SiteEngine_Ads_Bridge_Coordination::release(),'administrator setup releases verified ownership');
foreach(['prepared','activating']as$state){guard_case();$GLOBALS['wpdb']->rows=[['state'=>$state,'contract_version'=>'1']];$r=SiteEngine_Ads_Bridge_Coordination::enter_setup(true);check_inq(!$r['ok']&&$r['reason']==='coordination_pending_inquiry',$state.' blocks storage and settings controls');}
guard_case();$GLOBALS['wpdb']->coord_missing=true;$r=SiteEngine_Ads_Bridge_Coordination::enter_setup(true);check_inq($r['ok'],'absent guard admits explicit storage initialization');SiteEngine_Ads_Bridge_Coordination::release();
guard_case();$GLOBALS['wpdb']->coord_missing=true;$r=SiteEngine_Ads_Bridge_Coordination::enter_setup();check_inq(!$r['ok'],'ordinary setup admission cannot imply absent guard is ready');
guard_case();$GLOBALS['wpdb']->coord_busy=true;$r=SiteEngine_Ads_Bridge_Coordination::enter_setup(true);check_inq(!$r['ok']&&$r['reason']==='coordination_busy','another destination lock owner prevents initialization');
guard_case();$GLOBALS['wpdb']->missing_recovery_columns=true;$r=SiteEngine_Ads_Bridge_Coordination::enter_setup(true);check_inq(!$r['ok'],'partial existing inquiry schemas fail closed');
guard_case();$r=SiteEngine_Ads_Bridge_Coordination::batch(batch_request(),function($b){$b->step('field.one',function(){throw new RuntimeException('interrupted');},'verified');});fresh_guard_session();$r=SiteEngine_Ads_Bridge_Coordination::enter_setup(true);check_inq(!$r['ok']&&$r['reason']==='coordination_configuration_recovery_required','unfinished configuration journal prevents setup mutation');
echo "SUMMARY: $passes PASS, $fails FAIL; actual setup lock, SQL double\n";exit($fails?1:0);
