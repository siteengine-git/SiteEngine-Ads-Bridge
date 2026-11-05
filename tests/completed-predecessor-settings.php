<?php
/** Actual executor/record code and filesystem. No WordPress/SQL acceptance claim. */
define('ABSPATH',__DIR__.'/');
require dirname(__DIR__).'/includes/crm-import/recovery.php';
require dirname(__DIR__).'/includes/class-siteengine-ads-bridge-setup.php';
use SiteEngine\AdsBridge\Import\Recovery;
$fixture=sys_get_temp_dir().'/seab-owned-settings-'.bin2hex(random_bytes(6));mkdir($fixture,0700);$fixture=realpath($fixture);$directory=$fixture;
function se_crm_deployment_directory(){return $GLOBALS['directory'];}
$n=0;$skipped=[];$ran=0;
function ok($v,$why){global $n;if(!$v)throw new LogicException($why);$n++;echo "PASS $why\n";}
function save(){return SiteEngine_Ads_Bridge_Setup::with_executor(static function(){$GLOBALS['ran']++;return 'saved';});}
function blocked($record,$condition){$before=$GLOBALS['ran'];try{save();}catch(RuntimeException $e){ok(str_contains($e->getMessage(),$record.' ['.$condition.']'),'Exact record/condition: '.$record.'/'.$condition);ok($GLOBALS['ran']===$before,'Blocked ownership never calls settings callback');return;}throw new LogicException('Expected refusal');}
function job($state='complete',$inflight=null){return ['format'=>'siteengine.crm.deployment-job/1','job_id'=>str_repeat('b',32),'state'=>$state,'inflight'=>$inflight,'safe_checkpoint'=>'completed; native readback verified; owned maintenance restored'];}
function putjob($v){file_put_contents($GLOBALS['fixture'].'/deployment-job.json',json_encode($v));}
touch($fixture.'/executor.lock');
try {
    putjob(job());$payload=json_encode(['status'=>'held','operation'=>['old'=>'historical'],'padding'=>str_repeat('x',1048577)]);file_put_contents($fixture.'/admin-state.json',$payload);$hash=hash('sha256',$payload);
    ok(save()==='saved','Completed authoritative deployment permits ordinary save with >1MiB historical payload');
    $s=Recovery::shared();ok($s['kind']==='idle'&&!$s['historical_admin_payload_read'],'Historical administrator payload is not read for proven completion');
    ok(hash_file('sha256',$fixture.'/admin-state.json')===$hash,'Historical payload remains byte-identical');
    $large=job();$large['history']=[['result'=>str_repeat('x',1798466-strlen(json_encode($large))-28)]];
    $body=json_encode($large);$large['history'][0]['result'].=str_repeat(' ',1798466-strlen($body));$body=json_encode($large);ok(strlen($body)===1798466,'Exact reported deployment size1798466');
    foreach([false,true]as$bigAdmin){if(!$bigAdmin)file_put_contents($fixture.'/admin-state.json','{}');else file_put_contents($fixture.'/admin-state.json',$payload);file_put_contents($fixture.'/deployment-job.json',$body);$h=hash_file('sha256',$fixture.'/deployment-job.json');ok(save()==='saved','Large completed deployment permits save with historical payload '.($bigAdmin?'large':'small'));ok(hash_file('sha256',$fixture.'/deployment-job.json')===$h,'Original deployment bytes preserved');}
    $large['state']='active';file_put_contents($fixture.'/deployment-job.json',json_encode($large));blocked('deployment-job.json','retained_operation');
    $large['state']='complete';$large['inflight']=['request'=>'pending'];file_put_contents($fixture.'/deployment-job.json',json_encode($large));blocked('deployment-job.json','retained_operation');
    file_put_contents($fixture.'/deployment-job.json',substr($body,0,-1));blocked('deployment-job.json','malformed_content');
    file_put_contents($fixture.'/deployment-job.json',substr($body,0,-1).',"state":"complete"}');blocked('deployment-job.json','malformed_content');
    $x=job('active');putjob($x);$s=Recovery::shared();ok($s['kind']==='retained'&&!$s['historical_admin_payload_read'],'Active job remains retained without historical read');blocked('deployment-job.json','retained_operation');
    putjob(job('complete',['request'=>'uncertain']));blocked('deployment-job.json','retained_operation');
    $x=job();unset($x['inflight']);putjob($x);blocked('deployment-job.json','malformed_content');
    $x=job();unset($x['safe_checkpoint']);putjob($x);blocked('deployment-job.json','missing_completion_evidence');
    file_put_contents($fixture.'/deployment-job.json','not JSON private-content-must-not-appear');blocked('deployment-job.json','malformed_content');
    $s=Recovery::shared();ok(!str_contains($s['message'],'private-content-must-not-appear'),'No record body appears in diagnostics');
    file_put_contents($fixture.'/deployment-job.json',str_repeat('x',Recovery::MAX_RECORD_BYTES+1));blocked('deployment-job.json','inspection_budget_exceeded');
    unlink($fixture.'/deployment-job.json');blocked('deployment-job.json','missing_completion_evidence');
    file_put_contents($fixture.'/admin-state.json','malformed');blocked('admin-state.json','malformed_content');
    unlink($fixture.'/admin-state.json');blocked('deployment-job.json','missing_completion_evidence');
    putjob(job());unlink($fixture.'/executor.lock');blocked('executor.lock','missing_lock');ok(!file_exists($fixture.'/executor.lock'),'Original missing lock is never recreated over retained records');touch($fixture.'/executor.lock');
    $f=fopen($fixture.'/executor.lock','rb');flock($f,LOCK_EX|LOCK_NB);blocked('executor.lock','busy');flock($f,LOCK_UN);fclose($f);
    $directory=$fixture.'/absent';blocked('executor directory','unavailable_parent');$directory=$fixture;
    if(PHP_OS_FAMILY==='Linux'){
        chmod($fixture.'/deployment-job.json',0000);clearstatcache();ok(!is_readable($fixture.'/deployment-job.json'),'Actual Linux denied file read');blocked('deployment-job.json','unreadable_file');chmod($fixture.'/deployment-job.json',0600);
        chmod($fixture,0000);clearstatcache();ok(!is_readable($fixture),'Actual Linux denied parent listing');blocked('executor directory','unreadable_parent');chmod($fixture,0700);
        chmod($fixture,0400);clearstatcache();ok(is_readable($fixture)&&!is_executable($fixture),'Actual Linux denied parent traversal');blocked('executor directory','untraversable_parent');chmod($fixture,0700);
        rename($fixture.'/deployment-job.json',$fixture.'/job-target');symlink($fixture.'/job-target',$fixture.'/deployment-job.json');blocked('deployment-job.json','linked_file');unlink($fixture.'/deployment-job.json');rename($fixture.'/job-target',$fixture.'/deployment-job.json');
        unlink($fixture.'/deployment-job.json');file_put_contents($fixture.'/admin-state.json','historical');chmod($fixture.'/admin-state.json',0000);clearstatcache();ok(!is_readable($fixture.'/admin-state.json'),'Actual Linux denied legacy administrator read');blocked('admin-state.json','unreadable_file');chmod($fixture.'/admin-state.json',0600);
        putjob(job());chmod($fixture.'/admin-state.json',0000);clearstatcache();ok(save()==='saved','Completed deployment does not depend on unreadable unrelated historical payload');chmod($fixture.'/admin-state.json',0600);
    }else{$skipped[]='Linux file/parent permissions and symlinks: execute natively as unprivileged Alma owner';}
    echo json_encode(['status'=>'PASS','assertions'=>$n,'platform'=>PHP_OS_FAMILY,'skipped'=>$skipped,'SQL_or_WordPress_claim'=>false])."\n";
}finally{chmod($fixture,0700);foreach(glob($fixture.'/*')as$p){if(!is_link($p))chmod($p,0600);unlink($p);}rmdir($fixture);}
