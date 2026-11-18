<?php
/** Real installed WP/SQL acceptance. Explicit Climatrol DEV allocation only. */
if(!defined('WP_CLI')||!WP_CLI||home_url()!=='https://climatrol.siteengine.space'||realpath(ABSPATH)!=='/home/climatrol.siteengine.space/public_html'||!current_user_can('manage_options'))throw new RuntimeException('scoped_native_admin_required');
$phase=$args[0]??'';$dir='/home/climatrol.siteengine.space/.se-ads-pilot-gatea-20261118';
if(!is_dir($dir)||is_link($dir)||fileowner($dir)!==posix_geteuid())throw new RuntimeException('owned_private_run_required');
$file=$dir.'/fixture.json';$json=static fn($v)=>json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
$save=static function($v)use($file,$json){if(file_put_contents($file,$json($v),LOCK_EX)===false)throw new RuntimeException('fixture_journal_unconfirmed');chmod($file,0600);};
$j=is_file($file)?json_decode(file_get_contents($file),true,24,JSON_THROW_ON_ERROR):null;
$checks=[];$check=static function($ok,$label)use(&$checks){if(!$ok)throw new RuntimeException('FAIL:'.$label);$checks[]=$label;};
$http=0;$mail=0;add_filter('pre_http_request',static function()use(&$http){$http++;return new WP_Error('gate_a_no_network');},PHP_INT_MAX);add_filter('pre_wp_mail',static function()use(&$mail){$mail++;return false;},PHP_INT_MAX);
global $wpdb;
$capture=static function()use($wpdb){$out=[];foreach($wpdb->get_col('SHOW TABLES')as$t){if($t===$wpdb->options)continue;$out[$t]=$wpdb->get_results("CHECKSUM TABLE `$t` EXTENDED",ARRAY_A);}$options=$wpdb->get_results("SELECT option_name,option_value,autoload FROM {$wpdb->options} WHERE option_name='cron' OR option_name='active_plugins' OR option_name LIKE 'seab_%' OR option_name LIKE 'se_cb_%' OR option_name LIKE 'se_consent_%' OR option_name LIKE 'options_bd_%' ORDER BY option_name",ARRAY_A);return ['tables'=>$out,'options'=>$options];};
if($phase==='backup'){
 $check($j===null,'fresh_private_journal');$check(SEAB_VERSION==='0.9.5','accepted_installed_baseline');
 $j=['owner'=>'AB-GATEA-20261118','before'=>$capture(),'cases'=>[],'phase'=>'backed_up'];$save($j);echo $json(['status'=>'PASS','checks'=>$checks,'protected_tables'=>count($j['before']['tables'])]);return;
}
$check(($j['owner']??null)==='AB-GATEA-20261118','exact_owned_journal');
if($phase==='setup'){
 $check(SEAB_VERSION==='0.9.6-rc.1'&&$j['phase']==='backed_up','exact_successor_and_fresh_state');
 $migration=SiteEngine_Ads_Bridge_Outcomes::migrate(true);$check($migration['status']==='complete','supported_additive_evidence_migration');
 foreach(['success','duplicate','lost','crash']as$n=>$case){
  $marker='ab-gatea-20261118-'.$case;$email=$marker.'@example.invalid';$inq='inq_'.wp_generate_uuid4();$guid=wp_generate_uuid4();
  $check(!$wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}fc_subscribers WHERE email=%s",$email)),'fresh_owned_contact_'.$case);
  $check($wpdb->insert($wpdb->prefix.'fc_subscribers',['email'=>$email,'first_name'=>'Synthetic','last_name'=>'AB Gate A','status'=>'unsubscribed','created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')])===1,'native_contact_'.$case);$contact=(int)$wpdb->insert_id;
  $j['cases'][$case]=['contact'=>$contact,'email'=>$email,'inquiry'=>$inq,'guid'=>$guid,'marker'=>$marker];$save($j);
  $check($wpdb->insert($wpdb->prefix.'fluentform_submissions',['form_id'=>40,'response'=>$json(['email'=>$email,'first_name'=>'Synthetic','last_name'=>'AB Gate A','gate_a_marker'=>$marker]),'status'=>'unread','created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')])===1,'native_form40_storage_'.$case);$entry=(int)$wpdb->insert_id;$j['cases'][$case]['entry']=$entry;$save($j);
  $adopt=seab_inquiry_adopt_current(['contract_version'=>'1','event_id'=>$marker,'site_instance_id'=>'climatrol-dev-gatea-20261118','contact_id'=>$contact,'inquiry_id'=>$inq,'head_version'=>0,'source'=>['type'=>'fluent_form','reference'=>'form:40:entry:'.$entry,'form_id'=>40,'entry_id'=>$entry]]);
  $check(($adopt['status']??null)==='adopted','supported_AB_original_inquiry_'.$case);
 }
 $j['phase']='fixtures_ready';$save($j);echo $json(['status'=>'PASS','checks'=>$checks,'cases'=>array_map(static fn($c)=>['inquiry'=>$c['inquiry'],'guid'=>$c['guid'],'entry'=>$c['entry']],$j['cases']),'http'=>$http,'mail'=>$mail]);return;
}
require_once SEAB_PLUGIN_DIR.'includes/connectors/fieldedge/LeadPilotDispatch.php';
require_once SEAB_PLUGIN_DIR.'includes/connectors/fieldedge/TargetedRead.php';
use SiteEngine\AdsBridge\Connectors\FieldEdge\LeadPilotDispatch;
use SiteEngine\AdsBridge\Connectors\FieldEdge\LeadCreateDryRun;
use SiteEngine\AdsBridge\Connectors\FieldEdge\TargetedRead;
$api=SiteEngine_Ads_Bridge_Prospective::class;
if($phase==='run'){
 $check($j['phase']==='fixtures_ready','run_once_only');$j['phase']='run_started';$save($j);
 // Fixture implementation is intentionally not packaged or exposed over HTTP.
 require_once $dir.'/LeadFixture.php';
 foreach($j['cases']as$case=>&$c){
  $input=\SiteEngine\AdsBridge\Tests\FieldEdge\LeadFixture::input();$input['inquiry']['source']=['type'=>'fluent_form','form_id'=>40,'entry_id'=>$c['entry']];$input['inquiry']['inquiry_id']=$c['inquiry'];
  $dry=LeadCreateDryRun::prepare($input);$ctx=['tenant_id'=>'00000000-0000-4000-8000-000000000118','inquiry_id'=>$c['inquiry'],'source'=>$input['inquiry']['source']+['transaction_id'=>'ff:40:'.$c['entry']]];
  $c['envelope']=LeadPilotDispatch::envelope($dry,$ctx,$c['guid']);
  if($case==='duplicate'){$collision=json_decode($c['envelope'],true);$collision['guid']=$j['cases']['success']['guid'];$p=json_decode($collision['request']['body'],true);$p['trackingId']=$collision['guid'];$collision['request']['body']=$json($p);$collision['request']['body_sha256']=hash('sha256',$collision['request']['body']);$test=$api::lead_prepare($json($collision));$check(($test['reason_code']??null)==='prospective_guid_already_reserved','same_tenant_guid_unique_across_inquiries');}
  $r=$api::lead_prepare($c['envelope']);$check(($r['status']??null)==='prepared','reservation_'.$case);$c['intent']=$r['intent_id'];$c['digest']=$r['request_body_sha256'];$save($j);
  $check($api::lead_prepare($c['envelope'])['status']==='replayed','exact_reservation_replay_'.$case);
  $read=$api::lead_read($c['intent']);$check($read['reservation']['guid']===$c['guid']&&$read['reservation']['request']['body_sha256']===$c['digest'],'native_exact_reservation_'.$case);
 }
 unset($c);$a=$j['cases']['success'];$b=$j['cases']['duplicate'];
 $changed=json_decode($a['envelope'],true);$changed['guid']=wp_generate_uuid4();$p=json_decode($changed['request']['body'],true);$p['trackingId']=$changed['guid'];$changed['request']['body']=$json($p);$changed['request']['body_sha256']=hash('sha256',$changed['request']['body']);$check($api::lead_prepare($json($changed))['status']==='conflict','same_inquiry_new_guid_refused');
 $collision=json_decode($a['envelope'],true);$collision['inquiry_id']=$b['inquiry'];$collision['source']['entry_id']=$b['entry'];$collision['source']['transaction_id']='ff:40:'.$b['entry'];$check($api::lead_prepare($json($collision))['status']==='conflict','occupied_inquiry_collision_refused');
 $wrong=json_decode($a['envelope'],true);$wrong['source']['entry_id']=889;$check($api::lead_prepare($json($wrong))['status']==='held','historical_source_rebinding_refused');
 $user=get_current_user_id();wp_set_current_user(0);$check($api::lead_read($a['intent'])['reason_code']==='administrator_required','capability_rejection');wp_set_current_user($user);
 $check($api::lead_claim($a['intent'],str_repeat('0',64))['status']==='conflict','digest_mismatch_no_claim');
 $check(LeadPilotDispatch::send($a['intent'],$a['digest'])['status']==='held','no_default_transport');
 foreach(['success','duplicate','lost']as$case){$c=$j['cases'][$case];$calls=0;$result=LeadPilotDispatch::send($c['intent'],$c['digest'],static function($method,$url,$body,$tenant)use(&$calls,$case,$c,$check,$json,$wpdb){
   $check($wpdb->ready===false,'database_closed_before_transport_'.$case);$calls++;$check($method==='POST'&&$url==='https://api.fieldedge.com/open-api/v1/leads'&&hash('sha256',$body)===$c['digest']&&json_decode($body,true)['trackingId']===$c['guid'],'exact_frozen_request_'.$case);
   if($case==='lost')throw new RuntimeException('simulated_lost_reply');return ['status'=>$case==='duplicate'?400:201,'content_type'=>'application/json','body'=>$case==='duplicate'?'{}':$json(['data'=>['leadId'=>$c['guid'],'status'=>'Accepted']])];
  });$check($calls===1&&$result['status']===($case==='success'?'created':'uncertain'),'one_injected_post_'.$case);
  $again=LeadPilotDispatch::send($c['intent'],$c['digest'],static function()use(&$calls){$calls++;});$check($calls===1&&$again['status']==='held','no_post_retry_'.$case);
 }
 $c=$j['cases']['crash'];$claim=$api::lead_claim($c['intent'],$c['digest']);$check($claim['status']==='claimed','crash_after_claim_no_result');
 $j['phase']='awaiting_fresh_recovery';$save($j);$check($http===0&&$mail===0,'zero_real_network_or_mail');echo $json(['status'=>'PASS','checks'=>$checks,'simulated_posts'=>3,'real_provider_requests'=>0,'crm_effects'=>0]);return;
}
if($phase==='fresh'){
 $check($j['phase']==='awaiting_fresh_recovery','fresh_process_recovery');
 foreach($j['cases']as$case=>$c){
  $before=$api::lead_read($c['intent']);$check($before['reservation']['guid']===$c['guid']&&$before['claim']!==null,'fresh_durable_claim_'.$case);
  if($case==='crash')$check($before['creation_result']===null&&$before['status']==='uncertain','crash_remains_held');
  $calls=0;$held=LeadPilotDispatch::send($c['intent'],$c['digest'],static function()use(&$calls){$calls++;});$check($held['status']==='held'&&$calls===0,'fresh_no_repost_'.$case);
  $tenant=$before['reservation']['tenant_id'];$url=TargetedRead::url('lead',['id'=>$c['guid']]);$check($url==='https://api.fieldedge.com/open-api/v1/leads/'.$c['guid'],'exact_get_by_saved_guid_'.$case);
  $body=$json(['data'=>['leadId'=>$c['guid'],'trackingId'=>$c['guid'],'status'=>'Accepted']]);
  $r=$api::lead_observe($c['intent'],'get-original',null,$tenant,$c['guid'],200,$body,'2026-11-18T19:00:00Z');$check($r['status']==='observed','get_recovery_'.$case);
  $check($api::lead_observe($c['intent'],'get-original',null,$tenant,$c['guid'],200,$body,'2026-11-18T19:00:00Z')['status']==='replayed','exact_get_replay_'.$case);
  $check($api::lead_observe($c['intent'],'get-original',null,$tenant,$c['guid'],200,'{}','2026-11-18T19:00:00Z')['status']==='conflict','changed_event_rejected_'.$case);
  $check($api::lead_observe($c['intent'],'old-head',null,$tenant,$c['guid'],200,$body,'2026-11-18T19:00:01Z')['status']==='held','out_of_order_rejected_'.$case);
  $check($api::lead_observe($c['intent'],'wrong-tenant',$r['observation_sha256'],'00000000-0000-4000-8000-000000000999',$c['guid'],200,$body,'2026-11-18T19:00:02Z')['status']==='held','wrong_tenant_rejected_'.$case);
  $unknown=$json(['data'=>['leadId'=>$c['guid'],'status'=>'Unrecognized']]);$u=$api::lead_observe($c['intent'],'unknown',$r['observation_sha256'],$tenant,$c['guid'],200,$unknown,'2026-11-18T19:00:03Z');$check($u['status']==='held','unknown_status_held_'.$case);
  $check($api::lead_observe($c['intent'],'backdated',$u['observation_sha256'],$tenant,$c['guid'],200,$body,'2026-11-18T18:59:59Z')['status']==='held','backdated_rejected_'.$case);
  $bad=$json(['data'=>['leadId'=>'00000000-0000-4000-8000-000000000999','status'=>'Accepted']]);$m=$api::lead_observe($c['intent'],'mismatched',$u['observation_sha256'],$tenant,$c['guid'],200,$bad,'2026-11-18T19:00:04Z');$check($m['status']==='held','mismatched_provider_guid_held_'.$case);
  $notfound=$api::lead_observe($c['intent'],'not-found',$m['observation_sha256'],$tenant,$c['guid'],404,'{}','2026-11-18T19:00:05Z');$check($notfound['status']==='held','get_404_never_reposts_'.$case);
  $final=$api::lead_observe($c['intent'],'confirmed',$notfound['observation_sha256'],$tenant,$c['guid'],200,$body,'2026-11-18T19:00:06Z');$after=$api::lead_read($c['intent']);
  $check($final['status']==='observed'&&$after['creation_result']===$before['creation_result']&&count($after['observations'])===5,'append_only_creation_result_preserved_'.$case);
  $check($after['crm_write_allowed']===false&&$after['export_eligible']===false,'no_crm_or_ads_authority_'.$case);
 }
 $j['phase']='verified';$save($j);$check($http===0&&$mail===0,'zero_real_network_or_mail');echo $json(['status'=>'PASS','checks'=>$checks,'real_provider_requests'=>0,'transport'=>'injected only; GET bodies are fixtures, not authenticated provider acceptance']);return;
}
if($phase==='cleanup'){
 $check($j['phase']==='verified','accepted_fixture_cleanup_only');
 foreach($j['cases']as$c){
  $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}fc_subscribers WHERE id=%d",$c['contact']),ARRAY_A);$check(($row['email']??null)===$c['email'],'owned_contact_cleanup');
  $sub=$wpdb->get_row($wpdb->prepare("SELECT response FROM {$wpdb->prefix}fluentform_submissions WHERE id=%d AND form_id=40",$c['entry']),ARRAY_A);$check((json_decode($sub['response']??'',true)['gate_a_marker']??null)===$c['marker'],'owned_submission_cleanup');
  $wpdb->delete($wpdb->prefix.'fc_subscribers',['id'=>$c['contact']]);$wpdb->delete($wpdb->prefix.'fluentform_submissions',['id'=>$c['entry']]);$wpdb->delete(SiteEngine_Ads_Bridge_Inquiries::table_name(),['inquiry_id'=>$c['inquiry'],'contact_id'=>$c['contact']]);
  $id=$c['intent'];foreach(['lead','leadclaim','leadresult']as$kind)$wpdb->delete(SiteEngine_Ads_Bridge_Outcomes::table('evidence'),['evidence_id'=>'ps-'.$kind.'-'.$id]);
  $table=SiteEngine_Ads_Bridge_Outcomes::table('evidence');$wpdb->query($wpdb->prepare("DELETE FROM `$table` WHERE relationship_key=%s AND evidence_id LIKE 'ps-leadobs-%%'",hash('sha256','lead-observation-2|'.$id)));
 }
 $after=$capture();$changes=[];foreach($j['before']['tables']as$t=>$v)if(($after['tables'][$t]??null)!==$v)$changes[]=$t;
 $check($changes===[],'all_preexisting_table_checksums_preserved');
 $check($j['before']['options']===$after['options'],'protected_options_and_cron_exact');
 $j['phase']='cleaned';$j['after']=$after;$save($j);echo $json(['status'=>'PASS','checks'=>$checks,'preexisting_tables'=>count($j['before']['tables']),'protected_options'=>count($after['options']),'new_storage'=>'two additive empty AB outcome tables retained','owned_state_removed'=>true,'real_provider_requests'=>0]);return;
}
throw new RuntimeException('explicit_phase_required');
