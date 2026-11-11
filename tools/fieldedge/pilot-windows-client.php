<?php
/** Windows PHP worker: bounded JSON-line IPC to the FE launcher, never network/secrets. */
ini_set('display_errors','0');ini_set('log_errors','0');ini_set('zend.exception_ignore_args','1');
require_once __DIR__.'/../../includes/connectors/fieldedge/PilotReview.php';
use SiteEngine\AdsBridge\Connectors\FieldEdge\PilotReview;
use SiteEngine\AdsBridge\Connectors\FieldEdge\LeadResponse;
try {
 if(PHP_SAPI!=='cli'){throw new RuntimeException();}
 $line=fgets(STDIN,131074);
 if(!is_string($line)||strlen($line)>131072||!str_ends_with($line,"\n")){throw new RuntimeException();}
 $config=json_decode($line,true,20,JSON_THROW_ON_ERROR);
 if(!in_array($config['mode']??null,array('review','simulate','disabled-execute'),true)||!is_string($config['card_json']??null)||hash('sha256',$config['card_json'])!==($config['card_sha256']??null)){throw new RuntimeException();}
 $calls=0;$lead=0;$lastExecution=null;$lastOperation=null;
 $exchange=static function(string$operation,array$args)use(&$calls,&$lastExecution,&$lastOperation):array{
  if(++$calls>12||!in_array($operation,array('pilot_review','pilot_read','pilot_claim','observe'),true)){throw new RuntimeException();}
  $request=json_encode(array('kind'=>'bridge','operation'=>$operation,'args'=>$args),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
  if(strlen($request)>131072){throw new RuntimeException();}
  fwrite(STDOUT,$request."\n");fflush(STDOUT);
  $answer=fgets(STDIN,262146);
  if(!is_string($answer)||strlen($answer)>262144||!str_ends_with($answer,"\n")){throw new RuntimeException();}
  $r=json_decode($answer,true,24,JSON_THROW_ON_ERROR);
  if(($r['kind']??null)!=='bridge_result'||!is_array($r['result']??null)||($r['execution']['transaction_absent']??null)!==true||($r['execution']['coordination_lock_free']??null)!==true||!is_int($r['execution']['connection_id']??null)||$r['execution']['connection_id']<1){throw new RuntimeException();}
  $lastExecution=$r['execution'];$lastOperation=$operation;
  return $r['result'];
 };
 $gate=new PilotReview($config['card_json'],static fn(string$j):array=>$exchange('pilot_review',array($j)),
  static fn(string$s):array=>$exchange('pilot_read',array($s)),
  static fn(string$s,string$i,string$d):array=>$exchange('pilot_claim',array($s,$i,$d)),
  static fn(string$i,string$p,string$j):array=>$exchange('observe',array($i,$p,$j)));
 if($config['mode']==='disabled-execute'){$result=array('status'=>'held','reason_code'=>'native_production_post_disabled');}
 elseif($config['mode']==='review'){$result=$gate->review();}
 else{$result=$gate->send(static function(string$m,string$u,string$b)use(&$lead,&$lastExecution,&$lastOperation):array{
  if($lastOperation!=='pilot_read'||($lastExecution['transaction_absent']??null)!==true||($lastExecution['coordination_lock_free']??null)!==true||!LeadResponse::allowed($m,$u)||++$lead>1){throw new RuntimeException();}
  return array('status'=>201,'content_type'=>'application/json','body'=>'{"data":{"leadId":"00000000-0000-4000-8000-000000000601","status":0}}');
 });}
 echo json_encode(array('kind'=>'complete','result'=>$result,'bridge_calls'=>$calls,'token_doubles'=>0,'lead_doubles'=>$lead,'last_execution'=>$lastExecution,'provider_requests'=>0,'credentials_loaded'=>false,'production_post_enabled'=>false),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
}catch(Throwable$error){echo '{"kind":"complete","result":{"status":"held","reason_code":"windows_pilot_worker_unconfirmed"},"provider_requests":0,"credentials_loaded":false,"production_post_enabled":false}'."\n";exit(2);}
