<?php
/** Actual recovery/reporting and setup code; SQL/inspection replies are doubles. */
define('ABSPATH',__DIR__.'/'); define('ARRAY_A','ARRAY_A');
$directory=null;$options=[];$n=0;
function se_crm_deployment_directory(){return $GLOBALS['directory'];}
function maybe_unserialize($v){return unserialize($v);}
function current_user_can($v){return true;}function get_current_user_id(){return 1;}
function seab_configuration_capability(){return ['destination'=>'fixture'];}
class SiteEngine_Ads_Bridge_Coordination {
    static $reason='',$guard=null,$complete=true,$released=0,$migrations=0;
    static function table(){return 'fixture_guard';}static function capability(){return [];}
    static function enter_setup($v){return ['ok'=>self::$reason==='','reason'=>self::$reason];}
    static function release(){self::$released++;return true;}
    static function inspect($v){return ['complete'=>self::$complete,'state'=>self::$guard?'present':'none','reason_code'=>self::$reason?:'complete_empty'];}
    static function migrate($apply){self::$migrations++;return ['status'=>'complete'];}
}
class SiteEngine_Ads_Bridge_Inquiries {static function table_exists(){return true;}static function schema_ready(){return true;}}
$wpdb=new class {
    public $prefix='fixture_',$options='options',$last_error='';
    function prepare($s,...$v){return str_contains($s,'option_value')?$v[0]:$s;}
    function get_var($s){return str_contains($s,'information_schema')?'1':($GLOBALS['options'][$s]??null);}
    function get_results($s,$format){return SiteEngine_Ads_Bridge_Coordination::$guard?[SiteEngine_Ads_Bridge_Coordination::$guard]:[];}
};
foreach(['common','engine','recovery']as$f)require dirname(__DIR__).'/includes/crm-import/'.$f.'.php';
require dirname(__DIR__).'/includes/class-siteengine-ads-bridge-setup.php';
use SiteEngine\AdsBridge\Import\{Recovery,Engine,JSON};
function ok($v,$why){global $n;if(!$v)throw new LogicException($why);$n++;echo "PASS $why\n";}
function refusal($f,$needle){try{$f();}catch(RuntimeException $e){ok(str_contains($e->getMessage(),$needle),'Refuses with exact blocker: '.$needle);return;}throw new LogicException('Expected refusal');}
$j=['id'=>str_repeat('a',32),'source_pin'=>Recovery::PREDECESSOR,'upload_sha256'=>str_repeat('b',64),'mappings'=>['form'=>1],'actions'=>[['key'=>'template.test']],'status'=>'uncertain','cursor'=>1,'actor'=>1,'destination'=>'fixture'];
$j['plan_hash']=JSON::hash([$j['upload_sha256'],$j['mappings'],$j['actions'],$j['source_pin']]);
ok(Recovery::compatible($j),'Exact reviewed predecessor retains original source and plan pins');
$bad=$j;$bad['actions'][0]['key']='tampered';ok(!Recovery::compatible($bad),'Altered predecessor plan refuses');
$bad=$j;$bad['source_pin']=str_repeat('0',64);ok(!Recovery::compatible($bad),'Unknown importer source refuses');
$owner=new ReflectionMethod(Engine::class,'owner');$owner->invoke(null,$j);ok(true,'Actual supervisor admission accepts exact reviewed predecessor');
refusal(fn()=>$owner->invoke(null,$bad),'not a reviewed recovery predecessor');
$options['seab_crm_import_current_v1']=serialize($j['id']);$options['seab_crm_import_job_'.$j['id']]=serialize($j);
$g=['state'=>'inflight','journal'=>json_encode(['operation_id'=>'ads-import.'.$j['id'],'plan_hash'=>$j['plan_hash'],'inflight'=>'group.1','receipts'=>['group.0'=>'hash'],'manifest'=>['group.0'=>[],'group.1'=>[]]])];
SiteEngine_Ads_Bridge_Coordination::$guard=$g;
$s=Recovery::inspect();ok($s['resume']&&$s['guard']['completed_steps']===1&&!$s['can_initialize'],'Matching unfinished import routes to Resume; initialization unavailable');
SiteEngine_Ads_Bridge_Coordination::$reason='coordination_configuration_recovery_required';
refusal(fn()=>SiteEngine_Ads_Bridge_Setup::initialize_storage(),'Resume its saved operation before preparing storage');
ok(SiteEngine_Ads_Bridge_Coordination::$migrations===0,'Recovery blocker never runs a schema migration');
ok(!str_contains(SiteEngine_Ads_Bridge_Setup::blocker_message('coordination_configuration_recovery_required'),'Existing incomplete schemas'),'No unrelated schema explanation appended');
$options=[];$g['journal']=json_encode(['operation_id'=>'crm-predecessor.operation','plan_hash'=>str_repeat('c',64),'inflight'=>'step.1','receipts'=>[],'manifest'=>['step.1'=>[]]]);SiteEngine_Ads_Bridge_Coordination::$guard=$g;
$s=Recovery::inspect();ok(!$s['resume']&&!$s['can_initialize']&&$s['predecessor']&&str_contains($s['message'],'original CRM/other owner'),'Predecessor journal requires original recovery, never a new import/reset');
SiteEngine_Ads_Bridge_Coordination::$complete=false;SiteEngine_Ads_Bridge_Coordination::$reason='coordination_schema_unsupported';$s=Recovery::inspect();ok($s['kind']==='schema'&&!isset($s['guard'])&&str_contains($s['message'],'physical storage'),'Unsupported physical schema is distinct and raw IDs not claimed verified');
SiteEngine_Ads_Bridge_Coordination::$reason='coordination_busy';$s=Recovery::inspect();ok($s['kind']==='busy'&&!$s['resume'],'Busy connection is not reported as a schema fault');
SiteEngine_Ads_Bridge_Coordination::$reason='coordination_read_failed';$s=Recovery::inspect();ok($s['kind']==='unknown'&&!$s['can_initialize']&&str_contains($s['message'],'incomplete'),'Unavailable evidence is unknown, never empty/Ready');
SiteEngine_Ads_Bridge_Coordination::$complete=true;SiteEngine_Ads_Bridge_Coordination::$reason='';SiteEngine_Ads_Bridge_Coordination::$guard=null;$options=['seab_crm_import_current_v1'=>serialize('bad'),'seab_crm_import_job_bad'=>serialize('malformed')];$s=Recovery::inspect();ok($s['kind']==='unknown'&&!$s['can_initialize']&&!$s['resume'],'Malformed saved Ads journal cannot inherit a Ready/init result');$options=[];
$directory=sys_get_temp_dir().'/seab-retained-'.bin2hex(random_bytes(6));mkdir($directory);$directory=realpath($directory);touch($directory.'/executor.lock');
try{
    file_put_contents($directory.'/deployment-job.json',json_encode(['format'=>'siteengine.crm.deployment-job/1','job_id'=>'crm.fixture','state'=>'active','inflight'=>null]));
    file_put_contents($directory.'/admin-state.json',json_encode(['mode'=>'import','status'=>'running','job_id'=>'crm.fixture','operation'=>['request'=>'retained']]));
    $before=hash_file('sha256',$directory.'/admin-state.json');$s=Recovery::shared();ok($s['kind']==='retained'&&!$s['inflight']&&$s['job_id']==='crm.fixture','Free lock plus active durable job means retained reservation, not completion');
    refusal(fn()=>SiteEngine_Ads_Bridge_Setup::with_configuration(fn()=>throw new LogicException('must not execute')),'Retained/reserved deployment');
    $f=fopen($directory.'/executor.lock','rb');flock($f,LOCK_EX|LOCK_NB);$s=Recovery::shared();ok($s['kind']==='busy','Actual held file lock identifies live executor');flock($f,LOCK_UN);fclose($f);
    ok(hash_file('sha256',$directory.'/admin-state.json')===$before,'Inspection and refusal preserve original predecessor journal');
    unlink($directory.'/executor.lock');refusal(fn()=>SiteEngine_Ads_Bridge_Setup::with_configuration(fn()=>null),'executor.lock [missing_lock]');ok(!file_exists($directory.'/executor.lock'),'Missing predecessor lock is not silently recreated');touch($directory.'/executor.lock');
    file_put_contents($directory.'/deployment-job.json','malformed');$s=Recovery::shared();ok($s['kind']==='unknown'&&str_contains($s['message'],'deployment-job.json [malformed_content]'),'Malformed ownership requires exact missing evidence, never automatic takeover');
}finally{foreach(glob($directory.'/*')as$p)unlink($p);rmdir($directory);$directory=null;}
echo json_encode(['assertions'=>$n,'status'=>'PASS','SQL_evidence'=>'simulated; actual PHP/file-lock/reporting code'])."\n";
