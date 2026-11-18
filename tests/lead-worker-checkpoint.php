<?php
/** Focused injected checkpoint regression; no HTTP, real inquiry or claim. */
require_once __DIR__.'/../includes/connectors/fieldedge/ApprovedLeadWorker.php';
use SiteEngine\AdsBridge\Connectors\FieldEdge\ApprovedLeadWorker as Worker;
use SiteEngine\AdsBridge\Connectors\FieldEdge\OAuthClient;
$mode=$args[0]??$argv[1]??'verify';
$guid='00000000-0000-4000-8000-000000000002';$tenant='00000000-0000-4000-8000-000000000001';
$body=json_encode(['trackingId'=>$guid,'source'=>'siteengine','customer'=>['email'=>'fixture@example.invalid'],'isExistingCustomer'=>false,'appointment'=>(object)[],'serviceRequest'=>(object)[]]);
$r=['guid'=>$guid,'tenant_id'=>$tenant,'synthetic'=>false,'request'=>['method'=>'POST','endpoint'=>'https://api.fieldedge.com/open-api/v1/leads','body'=>$body,'body_sha256'=>hash('sha256',$body)]];
$post=['status'=>'claimed','dispatch_mode'=>'approved_single_post','permit'=>str_repeat('a',64),'card_sha256'=>str_repeat('b',64),'reservation'=>$r];
$get=['status'=>'claimed','dispatch_mode'=>'approved_guid_get','permit'=>str_repeat('c',64),'post_permit'=>$post['permit'],'card_sha256'=>$post['card_sha256'],'reservation'=>$r,'expires_at'=>'2050-01-01T00:00:00Z'];
$q=['environment'=>'Production_ClimatrolAirLLC','client_id'=>'OFFLINE','client_secret'=>'OFFLINE','target_id'=>$tenant,'post'=>$post,'get'=>$get];
$n=0;$check=static function($v,$name)use(&$n){if(!$v)throw new RuntimeException('FAIL '.$name);$n++;};
foreach($mode==='verify'?['get_bad_utf8','checkpoint_failure','get_validation_failure']:[$mode]as$case){
 $calls=[];$checkpoint=null;
 $client=new OAuthClient(static function($method,$url)use($case,$guid,&$calls,&$checkpoint,$check){
  $kind=$url===OAuthClient::TOKEN_URL?'oauth':($method==='POST'?'post':'get');$calls[]=$kind;
  if($kind==='oauth')return ['status'=>200,'content_type'=>'application/json','body'=>'{"access_token":"OFFLINE.token","token_type":"Bearer","expires_in":3600}'];
  if($kind==='post')return ['status'=>201,'content_type'=>'application/json','body'=>json_encode(['data'=>['leadId'=>$guid,'status'=>'Accepted']])];
  $check($checkpoint!==null,'creation checkpoint emitted before GET');
  if($case==='child_crash')exit(7);
  if($case==='child_timeout')usleep(2000000);
  return $case==='get_validation_failure'?['status'=>999,'body'=>new stdClass()]:['status'=>200,'body'=>"\xff"];
 });
 $sink=static function($value)use($case,&$checkpoint,$guid,$check){
  $checkpoint=$value;$check($value['http_status']===201&&$value['lead_id']===$guid,'confirmed checkpoint');
  if($case==='checkpoint_failure')throw new RuntimeException('worker_checkpoint_failed');
  if(str_starts_with($case,'child_')){fwrite(STDOUT,json_encode(['protocol'=>'lead-creation-checkpoint-1','creation'=>$value])."\n");fflush(STDOUT);}
 };
 $out=Worker::run(json_encode($q),$client,$sink);
 $check($out['creation']===$checkpoint,'creation survives later failure');
 $check(!str_contains(Worker::encode($out),'OFFLINE.token'),'no token in receipt');
 if($case==='checkpoint_failure'){$check($calls===['oauth','post'],'checkpoint failure prevents GET');$check($out['status']==='held'&&$out['reason_code']==='worker_checkpoint_failed','checkpoint failure explicit');}
 else{$check($out['status']==='observations_ready','GET fault produces receipt');$check($out['get_http']===($case==='get_validation_failure'?null:200),'GET validation preserved');}
}
$check(OAuthClient::HTTP_TIMEOUT_SECONDS===15,'actual cURL call ceiling');
echo json_encode(['status'=>'PASS','assertions'=>$n,'network_requests'=>0,'new_inquiries_or_claims'=>0,'native_wp'=>defined('WP_CLI')&&WP_CLI])."\n";
