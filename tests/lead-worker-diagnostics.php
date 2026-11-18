<?php
/** Entire production handler with injected transports; no credentials/HTTP/AB writes. */
require_once __DIR__.'/../includes/connectors/fieldedge/ApprovedLeadWorker.php';
use SiteEngine\AdsBridge\Connectors\FieldEdge\ApprovedLeadWorker as Worker;
use SiteEngine\AdsBridge\Connectors\FieldEdge\OAuthClient;
$n=0;$check=static function($ok,$label)use(&$n){if(!$ok)throw new RuntimeException('FAIL '.$label);$n++;};
if(isset($argv[1])){$q=json_decode(file_get_contents($argv[1]),true,24,JSON_THROW_ON_ERROR);$q['client_id']='OFFLINE';$q['client_secret']='OFFLINE';}
else{$guid='00000000-0000-4000-8000-000000000002';$tenant='00000000-0000-4000-8000-000000000001';$body=json_encode(['trackingId'=>$guid,'source'=>'siteengine','customer'=>['email'=>'fixture@example.invalid'],'isExistingCustomer'=>false,'appointment'=>(object)[],'serviceRequest'=>(object)[]]);$r=['guid'=>$guid,'tenant_id'=>$tenant,'synthetic'=>false,'request'=>['method'=>'POST','endpoint'=>'https://api.fieldedge.com/open-api/v1/leads','body'=>$body,'body_sha256'=>hash('sha256',$body)]];$post=['status'=>'claimed','dispatch_mode'=>'approved_single_post','permit'=>str_repeat('a',64),'card_sha256'=>str_repeat('b',64),'reservation'=>$r];$get=['status'=>'claimed','dispatch_mode'=>'approved_guid_get','permit'=>str_repeat('c',64),'post_permit'=>$post['permit'],'card_sha256'=>$post['card_sha256'],'reservation'=>$r,'expires_at'=>'2050-01-01T00:00:00Z'];$q=['environment'=>'Production_ClimatrolAirLLC','client_id'=>'OFFLINE','client_secret'=>'OFFLINE','target_id'=>$tenant,'post'=>$post,'get'=>$get];}
$guid=$q['get']['reservation']['guid'];$fixedClock=static fn()=>strtotime('2026-11-18T20:10:00Z');$results=[];
$cases=['success','oauth_throw','oauth_401','oauth_malformed','oauth_bad_token','oauth_short_ttl','post_throw','post_type_error','post_400','post_403','post_malformed_201','post_mismatched_guid','get_throw','get_nonarray','get_404','get_bad_utf8'];
foreach($cases as$case){
 $calls=[];$client=new OAuthClient(static function($m,$url,$h,$body)use($case,&$calls,$guid,$check,$q){
  $kind=$url===OAuthClient::TOKEN_URL?'oauth':($m==='POST'?'post':'get');$calls[]=$kind;
  if($kind==='oauth'){
   if($case==='oauth_throw')throw new RuntimeException('SENSITIVE_EXCEPTION_SENTINEL');
   if($case==='oauth_401')return ['status'=>401,'content_type'=>'application/json','body'=>'{"secret":"SENSITIVE_OAUTH_BODY"}'];
   if($case==='oauth_malformed')return ['status'=>200,'content_type'=>'application/json','body'=>'{'];
   if($case==='oauth_bad_token')return ['status'=>200,'content_type'=>'application/json','body'=>'{"token_type":"Bearer","expires_in":3600}'];
   return ['status'=>200,'content_type'=>'application/json','body'=>json_encode(['access_token'=>'SENSITIVE_TOKEN_SENTINEL','token_type'=>'Bearer','expires_in'=>$case==='oauth_short_ttl'?1:3600])];
  }
  if($kind==='post'){
   $check($body===$q['get']['reservation']['request']['body'],'retained approved request bytes unchanged');
   if($case==='post_throw')throw new RuntimeException('https_transport_failed');
   if($case==='post_type_error')throw new TypeError('SENSITIVE_TYPE_ERROR');
   return ['status'=>$case==='post_400'?400:($case==='post_403'?403:201),'content_type'=>'application/json','body'=>$case==='post_malformed_201'?'{':json_encode(['data'=>['leadId'=>$case==='post_mismatched_guid'?'00000000-0000-4000-8000-000000000999':$guid]])];
  }
  $check($url==='https://api.fieldedge.com/open-api/v1/leads/'.$guid&&$body==='','GET only original GUID');
  if($case==='get_throw')throw new RuntimeException('https_transport_failed');if($case==='get_nonarray')return 'SENSITIVE_BAD_TRANSPORT';
  return ['status'=>$case==='get_404'?404:200,'body'=>$case==='get_bad_utf8'?"\xff":($case==='get_404'?'':json_encode(['data'=>['leadId'=>$guid,'status'=>'Accepted']]))];
 },$fixedClock);
 $out=Worker::run(json_encode($q,JSON_THROW_ON_ERROR),$client);$wire=Worker::encode($out);$decoded=json_decode($wire,true,32,JSON_THROW_ON_ERROR);$trace=$out['diagnostics'];
 if(isset($argv[2])&&in_array($case,['success','oauth_401','get_bad_utf8'],true)){if(!is_dir($argv[2]))throw new RuntimeException('fixture_directory_required');file_put_contents($argv[2].'/'.$case.'.json',$wire);}
 $check(!str_contains($wire,'SENSITIVE_'),'no token/body/exception text in diagnostics or result');
 $expected=['oauth'=>count(array_filter($calls,static fn($v)=>$v==='oauth')),'post'=>count(array_filter($calls,static fn($v)=>$v==='post')),'get'=>count(array_filter($calls,static fn($v)=>$v==='get'))];$check($trace['transport_invocations']===$expected,'exact transport invocation counters');
 $check(max($expected)<=1,'no retry in worker');$check($decoded['automatic_retries']===0,'explicit no retry');
 if(str_starts_with($case,'oauth_')){$check($out['status']==='held'&&$expected['post']===0&&$expected['get']===0&&$trace['phase']==='oauth','OAuth failure phase retained before POST');}
 else{$check($out['status']==='observations_ready','POST/GET outcome remains serializable');$check(Worker::decodeStatusBody(['body_base64'=>$out['get_body_base64']])===($case==='get_bad_utf8'?"\xff":base64_decode($out['get_body_base64'])),'exact GET bytes survive wire');}
 if(in_array($case,['get_throw','get_nonarray','get_bad_utf8'],true))$check($out['creation']['http_status']===201&&$out['creation']['lead_id']===$guid,'confirmed POST receipt survives GET fault');
 if(in_array($case,['post_throw','post_type_error','get_throw','get_nonarray'],true))$check(count(array_filter($trace['events'],static fn($v)=>$v['event']==='failure'))>=1,'swallowed transport failure replaced with phase event');
 if($case==='oauth_401')$check(count(array_filter($trace['events'],static fn($e)=>($e['http_status']??null)===401))===1,'OAuth HTTP status recorded without token/body');
 $results[$case]=['status'=>$out['status'],'phase'=>$trace['phase'],'counts'=>$expected,'creation_http'=>$out['creation']['http_status']??null];
}
foreach(['json','oversize','environment','credentials','binding']as$case){$bad=$q;if($case==='environment')$bad['environment']='other';if($case==='credentials')$bad['client_secret']=null;if($case==='binding')$bad['target_id']='other';$calls=0;$client=new OAuthClient(static function()use(&$calls){$calls++;throw new RuntimeException('SENSITIVE');},$fixedClock);$bytes=$case==='json'?'{':($case==='oversize'?str_repeat(' ',196609):json_encode($bad));$out=Worker::run($bytes,$client);$check($out['status']==='held'&&$calls===0,'input/binding/credential failure before any transport');$check(!str_contains(Worker::encode($out),'SENSITIVE'),'failure text suppressed');$expected=$case==='credentials'?'credentials':($case==='binding'?'binding':'input');$check($out['diagnostics']['phase']===$expected,'correct pretransport phase');}
foreach(['!notbase64!',str_repeat('YQ==',30000)]as$bad){$held=false;try{Worker::decodeStatusBody(['body_base64'=>$bad]);}catch(Throwable $e){$held=true;}$check($held,'invalid/oversized response encoding rejected');}
echo json_encode(['status'=>'PASS','assertions'=>$n,'fault_cases'=>count($cases)+5,'retained_exact_body'=>isset($argv[1]),'request_body_sha256'=>$q['get']['reservation']['request']['body_sha256'],'network_requests'=>0,'cases'=>$results],JSON_THROW_ON_ERROR)."\n";
