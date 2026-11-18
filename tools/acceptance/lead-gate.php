<?php
/** Private request file -> existing AB evidence API. No HTTP, token or public route. */
if(!defined('WP_CLI')||!WP_CLI||!current_user_can('manage_options')||home_url()!=='https://climatrol.siteengine.space'||realpath(ABSPATH)!=='/home/climatrol.siteengine.space/public_html'||posix_getpwuid(posix_geteuid())['name']!=='clima6647')throw new RuntimeException('scoped_owner_admin_required');
$file=$args[0]??'';$sha=$args[1]??'';$private='/home/climatrol.siteengine.space/.se-ads-pilot-gatea-20261118/gate-b/';
if(!preg_match('/^[a-f0-9]{64}$/D',$sha)||!preg_match('#^'.preg_quote($private,'#').'request-[a-f0-9]{32}\.json$#D',$file)||is_link($file)||is_link(dirname($file))||!is_file($file)||fileowner($file)!==posix_geteuid()||(fileperms($file)&0077)!==0||filesize($file)>131072||hash_file('sha256',$file)!==$sha)throw new RuntimeException('private_request_pin_required');
$q=json_decode(file_get_contents($file),true,24,JSON_THROW_ON_ERROR);$api=SiteEngine_Ads_Bridge_Prospective::class;$op=$q['operation']??'';
try{
 $id=$q['intent_id']??'';$card=$q['card_sha256']??'';
 switch($op){
  case 'inspect':$r=['status'=>'ready','destination'=>SiteEngine_Ads_Bridge_Outcomes::capability()['destination'],'master_form'=>(int)SiteEngine_Ads_Bridge_Config::instance()->get('fluent_forms.form_id'),'runtime_sha256'=>hash_file('sha256',SEAB_PLUGIN_DIR.'includes/class-siteengine-ads-bridge-lead-approval.php')];break;
  case 'validate':\SiteEngine\AdsBridge\Connectors\FieldEdge\LiveLeadPayload::validate($q['body'],$q['guid']);$r=['status'=>'valid'];break;
  case 'adopt':
   global $wpdb;$a=$q['adoption'];$s=$a['source']??[];$entry=$wpdb->get_row($wpdb->prepare("SELECT form_id,response FROM {$wpdb->prefix}fluentform_submissions WHERE id=%d",$s['entry_id']??0),ARRAY_A);$contact=\FluentCrm\App\Models\Subscriber::find($a['contact_id']??0);$raw=json_decode($entry['response']??'',true);$emailField=SiteEngine_Ads_Bridge_Config::instance()->get('fluent_forms.fields.email','email');
   if((int)($entry['form_id']??0)!==40||($s['form_id']??null)!==40||!$contact||!is_string($raw[$emailField]??null)||strcasecmp(trim($raw[$emailField]),trim($contact->email))!==0)throw new RuntimeException('operator_test_entry_contact_mismatch');
   $r=seab_inquiry_adopt_current($a);break;
  case 'reserve':$r=$api::lead_prepare($q['envelope']);break;
  case 'review':$r=$api::lead_review($id,$q['card_json']);break;
  case 'prepare':
   $r=$api::lead_prepare($q['envelope']);if(!in_array($r['status']??null,['prepared','replayed'],true))break;
   $id=$r['intent_id'];$review=$api::lead_review($id,$q['card_json']);$r=['status'=>$review['status'],'intent_id'=>$id,'review'=>$review];break;
  case 'read':$r=$api::lead_read($id);break;
  case 'approve':$r=$api::lead_approve($id,$card,$q['approval_reference']);break;
  case 'claim':$r=$api::lead_claim_approved($id,$card,$q['request_body_sha256']);break;
  case 'get_claim':$r=$api::lead_claim_get($id,$card,$q['slot']);break;
  case 'result':$r=$api::lead_result($id,$q['permit'],$q['observation_json']);break;
  case 'status':require_once SEAB_PLUGIN_DIR.'includes/connectors/fieldedge/ApprovedLeadWorker.php';$body=\SiteEngine\AdsBridge\Connectors\FieldEdge\ApprovedLeadWorker::decodeStatusBody($q);$r=$api::lead_approved_status($id,$card,$q['slot'],$q['permit'],$q['http_status'],$body,$q['observed_at']);break;
  default:throw new RuntimeException('lead_operation_refused');
 }
 global $wpdb;$lock='seab_c_'.substr(SiteEngine_Ads_Bridge_Coordination::capability()['destination'],0,48);
 if($wpdb->get_var('SELECT @@in_transaction')!=='0'||$wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)',$lock))!==null)throw new RuntimeException('lead_postcall_boundary_unconfirmed');
 echo json_encode(['request_sha256'=>$sha,'operation'=>$op,'version'=>SEAB_VERSION,'result'=>$r,'execution'=>['transaction_absent'=>true,'coordination_lock_free'=>true,'owner_uid'=>posix_geteuid(),'site'=>'https://climatrol.siteengine.space']],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
}finally{unlink($file);}
