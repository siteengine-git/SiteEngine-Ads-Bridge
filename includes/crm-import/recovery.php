<?php
namespace SiteEngine\AdsBridge\Import;
use RuntimeException;
use Throwable;

final class OwnershipRecordFailure extends RuntimeException {
    public function __construct(public string $record,public string $condition,string $detail) {
        parent::__construct('Ownership record '.$record.' ['.$condition.']: '.$detail.'.');
    }
}

/** Read-only routing. A status snapshot never grants write/recovery permission. */
final class Recovery {
    // c1e8e6d: reviewed predecessor. Native actions, group identities and readback
    // semantics are unchanged. Keep its ORIGINAL source/plan/operation pins.
    const PREDECESSOR='983ee5760bcff511122f6f6eb488061782dffc95bf86583fcf12a656a357c263';
    public static function compatible(array $j):bool {
        if (($j['source_pin']??null)===Engine::sourcePin()) return true;
        return ($j['source_pin']??null)===self::PREDECESSOR
            && isset($j['plan_hash'],$j['upload_sha256'],$j['mappings'],$j['actions'])
            && hash_equals($j['plan_hash'],JSON::hash([$j['upload_sha256'],$j['mappings'],$j['actions'],$j['source_pin']]));
    }
    private static function id($v):?string {return is_string($v)&&preg_match('/^[A-Za-z0-9_.:-]{1,80}$/D',$v)?$v:null;}
    // Producer history grows with phases and has no 1 MiB writer restriction.
    // Bound raw input to16 MiB; validate at producer depth512, then project only
    // ownership fields (4 KiB scalar ceiling). Never decode historical arrays.
    const MAX_RECORD_BYTES=16777216;
    const RECORD_DEPTH=512;
    const INSPECTION_SECONDS=3;
    private static function ownershipProjection(string $raw,string $name):array {
        $until=hrtime(true)+self::INSPECTION_SECONDS*1000000000;
        if(!json_validate($raw,self::RECORD_DEPTH))self::recordFailure($name,'malformed_content','file is not valid JSON at the supported producer depth; contents are not displayed');
        $length=strlen($raw);$i=strspn($raw," \t\r\n");
        if(($raw[$i]??'')!=='{')self::recordFailure($name,'malformed_content','JSON must contain an ownership object');
        $out=[];$wanted=['format','job_id','state','inflight','safe_checkpoint','status','operation'];$i++;
        while($i<$length){
            $i+=strspn($raw," \t\r\n,",$i);if(($raw[$i]??'')==='}')break;
            $start=$i++;while($i<$length){if($raw[$i]==='\\'){$i+=2;continue;}if($raw[$i++]==='"')break;}
            $key=$i-$start<=128?json_decode(substr($raw,$start,$i-$start),true,8,JSON_THROW_ON_ERROR):null;
            $i+=strspn($raw," \t\r\n",$i);$i++;$i+=strspn($raw," \t\r\n",$i);$start=$i;$depth=0;$quoted=false;
            for(;$i<$length;$i++){
                if(($i&65535)===0&&hrtime(true)>$until)self::recordFailure($name,'inspection_budget_exceeded','ownership scan exceeded3 seconds; completion remains unknown; retain original records');
                $c=$raw[$i];if($quoted){if($c==='\\'){$i++;continue;}if($c==='"')$quoted=false;continue;}
                if($c==='"'){$quoted=true;continue;}if($c==='{'||$c==='['){$depth++;continue;}
                if($depth===0&&($c===','||$c==='}'))break;if($c==='}'||$c===']')$depth--;
            }
            if(in_array($key,$wanted,true)){
                if(array_key_exists($key,$out))self::recordFailure($name,'malformed_content','duplicate ownership field '.$key.' is ambiguous');
                $size=$i-$start;while($size>0&&str_contains(" \t\r\n",$raw[$start+$size-1]))$size--;
                if(in_array($key,['inflight','operation'],true))$out[$key]=($size===4&&substr_compare($raw,'null',$start,4)===0)?null:true;
                else{if($size>4096)self::recordFailure($name,'malformed_content','ownership scalar '.$key.' exceeds4 KiB');$out[$key]=json_decode(substr($raw,$start,$size),true,8,JSON_THROW_ON_ERROR);}
            }
        }
        if(hrtime(true)>$until)self::recordFailure($name,'inspection_budget_exceeded','ownership inspection exceeded3 seconds; completion remains unknown');
        return $out;
    }
    private static function recordFailure(string $record,string $condition,string $detail):void {
        throw new OwnershipRecordFailure($record,$condition,$detail);
    }
    public static function directoryEvidence(string $dir):void {
        if(is_link($dir))self::recordFailure('executor directory','linked_parent','directory is linked; its original identity is unverified');
        if(!is_dir($dir))self::recordFailure('executor directory','unavailable_parent','directory is absent or cannot be traversed from its parent');
        if(!is_readable($dir))self::recordFailure('executor directory','unreadable_parent','directory cannot be listed by this PHP execution identity');
        if(DIRECTORY_SEPARATOR!=='\\'&&!is_executable($dir))self::recordFailure('executor directory','untraversable_parent','directory cannot be traversed by this PHP execution identity');
        if(realpath($dir)!==$dir)self::recordFailure('executor directory','parent_identity','resolved directory differs from its recorded identity');
    }
    private static function record(string $path):?array {
        $name=basename($path);self::directoryEvidence(dirname($path));clearstatcache(true,$path);
        if(is_link($path))self::recordFailure($name,'linked_file','file is linked; its original ownership identity is unverified');
        $stat=@lstat($path);if($stat===false)return null;
        if(!is_file($path))self::recordFailure($name,'not_regular_file','entry is not a regular file');
        if(!is_readable($path))self::recordFailure($name,'unreadable_file','file cannot be read by this PHP execution identity');
        $size=$stat['size'];if($size>self::MAX_RECORD_BYTES)self::recordFailure($name,'inspection_budget_exceeded','file has '.$size.' bytes; no completion conclusion; bounded ownership read permits at most '.self::MAX_RECORD_BYTES.' bytes');
        $f=@fopen($path,'rb');if(!$f)self::recordFailure($name,'unreadable_file','file could not be opened for reading');
        try{$opened=fstat($f);$raw=stream_get_contents($f,self::MAX_RECORD_BYTES+1);if($raw===false)self::recordFailure($name,'unreadable_file','bounded read failed');if(strlen($raw)>self::MAX_RECORD_BYTES)self::recordFailure($name,'inspection_budget_exceeded','file grew beyond the16 MiB bounded ownership read; no completion conclusion');if($opened['dev']!==$stat['dev']||$opened['ino']!==$stat['ino']||strlen($raw)!==$size)self::recordFailure($name,'changed_during_read','file identity or length changed during inspection');}
        finally{fclose($f);}
        return self::ownershipProjection($raw,$name);
    }
    /** The only optional creation is an explicitly empty first-use directory. */
    public static function acquireExecutor(string $dir,bool $allowEmptyCreate=false) {
        self::directoryEvidence($dir);$path=$dir.'/executor.lock';clearstatcache(true,$path);
        if(is_link($path))self::recordFailure('executor.lock','linked_file','original executor lock is linked');
        if(!file_exists($path)){
            $it=new \FilesystemIterator($dir,\FilesystemIterator::SKIP_DOTS);
            if(!$allowEmptyCreate||$it->valid())self::recordFailure('executor.lock','missing_lock','original executor lock is missing; establish writer cessation with its owner; it will not be recreated over retained records');
            $f=@fopen($path,'x+b');
        }else{
            if(!is_file($path)||!is_readable($path))self::recordFailure('executor.lock','unreadable_file','original lock is not a readable regular file');
            $f=@fopen($path,'rb');
        }
        if(!$f)self::recordFailure('executor.lock','unreadable_file','original lock could not be opened');
        if(!flock($f,LOCK_EX|LOCK_NB,$busy)){fclose($f);if($busy)throw new OwnershipRecordFailure('executor.lock','busy','a live executor holds the lock; no settings callback was run');self::recordFailure('executor.lock','lock_failed','exclusive ownership could not be established');}
        return $f;
    }
    public static function shared():array {
        $out=['kind'=>'none','message'=>'No predecessor executor directory is present.'];$f=null;
        try {
            $dir=\SiteEngine_Ads_Bridge_Setup::executor_directory();if($dir===null)return $out;
            self::directoryEvidence($dir);
            if(!\SiteEngine_Ads_Bridge_Setup::executor_owned())$f=self::acquireExecutor($dir);
            // Read authoritative deployment state only AFTER exclusive ownership.
            $job=self::record($dir.'/deployment-job.json');
            $out=['kind'=>'unknown','job_id'=>self::id($job['job_id']??null),'state'=>self::id($job['state']??null),'historical_admin_payload_read'=>false];
            if($job!==null){
                if(($job['format']??null)!=='siteengine.crm.deployment-job/1'||$out['job_id']===null||!in_array($job['state']??null,['active','complete'],true)||!array_key_exists('inflight',$job))self::recordFailure('deployment-job.json','malformed_content','recognized format,job identity,state and explicit inflight evidence are required');
                $out['inflight']=$job['inflight']!==null;
                if($job['state']==='complete'&&$job['inflight']===null){
                    if(!is_string($job['safe_checkpoint']??null)||trim($job['safe_checkpoint'])==='')self::recordFailure('deployment-job.json','missing_completion_evidence','completed deployment is missing its durable safe_checkpoint');
                    $out['kind']='idle';$out['message']='Deployment '.$out['job_id'].' is complete, with explicit inflight:null and a durable checkpoint, under exclusive executor ownership. Historical admin-state.json is not needed or read for ordinary Ads settings.';return $out;
                }
                $out['kind']='retained';$out['record']='deployment-job.json';$out['condition']='retained_operation';$out['message']='Ownership record deployment-job.json [retained_operation]: Retained/reserved deployment '.$out['job_id'].'; state '.$out['state'].'; inflight '.($out['inflight']?'present':'none').'. A free executor lock does not complete it. Resume its original saved recovery before changing settings.';return $out;
            }
            // With no authoritative deployment record, never infer completion from
            // an administrator payload, age, missing plugin or an available lock.
            $state=self::record($dir.'/admin-state.json');$out['historical_admin_payload_read']=$state!==null;
            if($state!==null){$out['import_state']=self::id($state['status']??null);$out['inflight']=!empty($state['operation']);}
            self::recordFailure('deployment-job.json','missing_completion_evidence','authoritative deployment record is missing; original job/checkpoint and executor ownership evidence are required. Historical administrator state cannot authorize replacement');
        }catch(OwnershipRecordFailure $e){$out['kind']=$e->condition==='busy'?'busy':'unknown';$out['record']=$e->record;$out['condition']=$e->condition;$out['message']=$e->getMessage().' Preserve records; no settings callback or journal reset is authorized by this inspection.';return $out;}
        catch(Throwable $e){$out['kind']='unknown';$out['message']='Ownership inspection unavailable; original executor location and valid bounded deployment-job.json are required. No record contents are displayed.';return $out;}
        finally{if(is_resource($f)){flock($f,LOCK_UN);fclose($f);}}
    }
    /** Actual supported inspection validates physical storage and the guard journal. */
    public static function inspect():array {
        $out=['kind'=>'unknown','reason'=>'inspection_unavailable','message'=>'Storage/operation inspection is unavailable. No writes are authorized.','can_initialize'=>false,'resume'=>false,'shared'=>self::shared()];
        try {
            global $wpdb;$t=\SiteEngine_Ads_Bridge_Coordination::table();
            $exists=$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$t));
            if($wpdb->last_error!==''||!in_array((string)$exists,['0','1'],true))throw new RuntimeException('Guard table presence could not be established.');
            $inquiry=\SiteEngine_Ads_Bridge_Inquiries::table_exists();
            if((string)$exists==='0'||!$inquiry){
                $held=\SiteEngine_Ads_Bridge_Coordination::enter_setup(true);$reason=$held['reason'];
                if($held['ok']&&!\SiteEngine_Ads_Bridge_Coordination::release())$reason='coordination_release_failed';
                $out['reason']=$reason?:'storage_absent';$out['kind']=$reason?self::kind($reason):'absent';$out['can_initialize']=$reason==='';
            }else{
                $before=self::guard($t);$check=\SiteEngine_Ads_Bridge_Coordination::inspect(\SiteEngine_Ads_Bridge_Coordination::capability());$after=self::guard($t);
                if($before!==$after)throw new RuntimeException('Guard changed during inspection. Refresh status after its owner reaches a safe checkpoint.');
                $out['reason']=$check['reason_code'];$out['kind']=$check['complete']?($check['state']==='none'?'ready':'unfinished'):self::kind($check['reason_code']);
                if($check['complete']&&$after!==null){
                    $j=json_decode($after['journal'],true,128,JSON_THROW_ON_ERROR);
                    $out['guard']=['operation_id'=>$j['operation_id'],'state'=>$after['state'],'plan_hash'=>$j['plan_hash'],'inflight'=>$j['inflight'],'completed_steps'=>count($j['receipts']),'total_steps'=>count($j['manifest'])];
                    if($after['state']!=='complete')$out['reason']='coordination_configuration_recovery_required';
                    elseif($check['state']!=='none')$out['reason']='coordination_pending_inquiry';
                }
            }
            $own=Store::job();$g=$out['guard']??null;
            if($own&&in_array($own['status'],['running','uncertain'],true)){
                $out['saved_import']=['id'=>$own['id'],'state'=>$own['status'],'cursor'=>$own['cursor'],'total'=>count($own['actions']),'actor'=>$own['actor']];
                $out['resume']=$g&&$g['operation_id']==='ads-import.'.$own['id']&&$g['plan_hash']===$own['plan_hash']&&self::compatible($own);
                if((!$g||$g['state']==='complete')&&$out['kind']==='ready')$out['resume']=self::compatible($own); // stopped before first guard reservation
                if(!$out['resume'])$out['missing']='Exact matching guard operation/plan and reviewed importer source are required. Preserve the original upload, action IDs, receipts and pending intent.';
            }elseif($out['kind']==='unfinished'&&isset($out['guard'])&&$out['guard']['state']!=='complete'){
                $out['predecessor']=true;$out['missing']='No matching Ads saved import. Recover the original CRM/other owner operation with its exact request, source/profile pins, checkpoints and native IDs. Ads cannot translate or complete an unknown native write.';
            }
            if($out['shared']['kind']!=='none'&&$out['shared']['kind']!=='idle'){$out['can_initialize']=false;$out['resume']=false;}
            $out['message']=self::describe($out);return $out;
        }catch(Throwable $e){$out['kind']='unknown';$out['reason']='inspection_failed';$out['resume']=false;$out['can_initialize']=false;$out['message']=$e->getMessage().' Preserve all storage and journals; inspection is unknown, not empty.';return $out;}
    }
    private static function guard(string $t):?array {
        global $wpdb;if(!preg_match('/^[A-Za-z0-9_]+$/D',$t))throw new RuntimeException('Guard identifier invalid.');
        $r=$wpdb->get_results("SELECT state,journal FROM `{$t}` LIMIT 2",ARRAY_A);
        if($wpdb->last_error!==''||!is_array($r)||count($r)>1)throw new RuntimeException('Guard read is incomplete.');
        if(!$r)return null;if(!is_string($r[0]['journal'])||strlen($r[0]['journal'])>65536)throw new RuntimeException('Guard journal exceeds its bound.');return $r[0];
    }
    public static function kind(string $reason):string {
        if($reason==='coordination_busy')return 'busy';
        if($reason==='coordination_schema_unsupported')return 'schema';
        if(in_array($reason,['coordination_configuration_recovery_required','coordination_pending_inquiry'],true))return 'unfinished';
        return 'unknown';
    }
    private static function describe(array $s):string {
        if(!in_array($s['shared']['kind'],['none','idle'],true))return $s['shared']['message'].' Job '.($s['shared']['job_id']??'unknown').'; state '.($s['shared']['state']??'unknown').'. Initialization is not a recovery action.';
        if($s['kind']==='unknown')return 'Storage/ownership inspection is incomplete: '.$s['reason'].'. Preserve records and obtain physical-schema, original operation and writer evidence before recovery.';
        if($s['kind']==='absent')return 'Required storage is absent. Explicit initialization can create it after reviewing this site. Existing history is not reset.';
        if($s['kind']==='ready'&&!isset($s['saved_import']))return 'Physical storage and retained guard verified; no unfinished configuration/inquiry operation observed.';
        if($s['kind']==='schema')return 'Existing physical storage is incomplete or unsupported. Review Storage and the supported inquiry-admin migration preview before repair; no journal reset.';
        if($s['kind']==='busy')return 'A live connection currently holds the destination lock. Wait for its owner checkpoint; no takeover or initialization.';
        $g=$s['guard']??[];if(isset($s['saved_import'])&&($g['state']??null)==='complete'&&($g['operation_id']??null)!=='ads-import.'.$s['saved_import']['id'])$g=['operation_id'=>'ads-import.'.$s['saved_import']['id'],'state'=>$s['saved_import']['state']];$text='Unfinished operation '.($g['operation_id']??$s['saved_import']['id']??'unknown').'; state '.($g['state']??$s['saved_import']['state']??'unknown').'. ';
        if($s['resume'])return $text.'Open Import CRM Configuration and Resume saved import. Original identities and completed-step native readback are retained; initialization is not the remedy.';
        return $text.($s['missing']??'Recover the existing inquiry/configuration operation using its original owner and exact evidence.').' Initialization is not the remedy.';
    }
    public static function message(string $reason):string {
        $s=self::inspect();
        if(in_array($reason,['coordination_configuration_recovery_required','coordination_busy','coordination_schema_unsupported','coordination_pending_inquiry','shared_configuration_recovery_required','shared_executor_busy'],true))return $s['message'].' Reason: '.$reason;
        return 'Ads setup blocked: '.$reason.'. Inspection: '.$s['message'];
    }
    public static function render(array $s):void {
        echo '<div class="notice notice-'.($s['kind']==='ready'&&in_array($s['shared']['kind'],['idle','none'],true)?'info':'warning').' inline"><p>'.esc_html($s['message']).'</p>';
        if($s['resume'])echo '<p><a href="'.esc_url(admin_url('admin.php?page=siteengine-ads-bridge&tab=settings&section=diagnostics')).'">Inspect saved Ads operation in Diagnostics</a></p>';
        if(!empty($s['predecessor'])||!in_array($s['shared']['kind'],['none','idle'],true)){
            if(class_exists('\\SiteEngine\\CRM\\AdminConfiguration'))echo '<p><a href="'.esc_url(admin_url('admin.php?page=siteengine-crm-configuration')).'">Inspect original CRM saved session</a> â€” use only its exact supported recovery, not a new import.</p>';
            else echo '<p>Original CRM recovery tooling is unavailable in this request. Obtain the pinned predecessor recovery package and original private job/request/checkpoint records through its owner. Do not install a replacement operation or clear storage.</p>';
        }
        echo '</div>';
    }
}
