<?php
namespace SiteEngine\AdsBridge\Import;
use RuntimeException;
use Throwable;

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
    private static function record(string $path):?array {
        if (!file_exists($path)&&!is_link($path)) return null;
        if (is_link($path)||!is_file($path)||!is_readable($path)||filesize($path)>1048576) throw new RuntimeException('Saved ownership record is unreadable, linked or exceeds 1 MiB.');
        $v=json_decode(file_get_contents($path),true,128,JSON_THROW_ON_ERROR);
        if (!is_array($v)) throw new RuntimeException('Saved ownership record is malformed.');
        return $v;
    }
    public static function shared():array {
        $out=['kind'=>'none','message'=>'No predecessor executor directory is present.'];$f=null;
        try {
            $dir=\SiteEngine_Ads_Bridge_Setup::executor_directory();if($dir===null)return $out;
            if(!is_dir($dir)||is_link($dir)||realpath($dir)!==$dir)throw new RuntimeException('Original private executor directory is unavailable or ambiguous.');
            $job=self::record($dir.'/deployment-job.json');$state=self::record($dir.'/admin-state.json');
            $out=['kind'=>'idle','job_id'=>self::id($job['job_id']??null),'state'=>self::id($job['state']??null),'import_state'=>self::id($state['status']??null),'inflight'=>!empty($job['inflight'])||!empty($state['operation']),'message'=>'No unfinished predecessor job observed.'];
            $path=$dir.'/executor.lock';
            if(is_link($path))throw new RuntimeException('Executor lock is linked.');
            if(is_file($path)&&!\SiteEngine_Ads_Bridge_Setup::executor_owned()){
                $f=fopen($path,'rb');if(!$f)throw new RuntimeException('Executor lock cannot be inspected.');
                if(!flock($f,LOCK_EX|LOCK_NB)){$out['kind']='busy';$out['message']='A live executor holds the shared lock. Do not resume or replace its work.';return $out;}
            }elseif(!is_file($path)&&($job||$state))throw new RuntimeException('Original executor lock is missing; writer cessation is unverified.');
            if($job&&(($job['format']??null)!=='siteengine.crm.deployment-job/1'||!in_array($job['state']??null,['active','complete'],true)))throw new RuntimeException('Deployment job format/state is not recognized.');
            if(($job&&(($job['state']??null)!=='complete'||$out['inflight']))||($state&&($state['mode']??null)==='import'&&($state['status']??null)!=='complete')){
                $out['kind']='retained';$out['message']='Retained/reserved predecessor operation; no executor holds the lock at this observation. This is not proof the job is complete or safe to replace. Use its original CRM saved-session recovery.';
            }
            return $out;
        }catch(Throwable $e){return ['kind'=>'unknown','message'=>$e->getMessage().' Original job, request, profile, checkpoints and native returned IDs are required before recovery.'];}
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
        if($s['resume'])echo '<p><a href="'.esc_url(admin_url('admin.php?page=siteengine-ads-bridge&tab=crm-import')).'">Open saved Ads import</a></p>';
        if(!empty($s['predecessor'])||!in_array($s['shared']['kind'],['none','idle'],true)){
            if(class_exists('\\SiteEngine\\CRM\\AdminConfiguration'))echo '<p><a href="'.esc_url(admin_url('admin.php?page=siteengine-crm-configuration')).'">Inspect original CRM saved session</a> â€” use only its exact supported recovery, not a new import.</p>';
            else echo '<p>Original CRM recovery tooling is unavailable in this request. Obtain the pinned predecessor recovery package and original private job/request/checkpoint records through its owner. Do not install a replacement operation or clear storage.</p>';
        }
        echo '</div>';
    }
}
