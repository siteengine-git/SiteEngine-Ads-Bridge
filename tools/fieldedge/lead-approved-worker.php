<?php
/** Existing DPAPI launcher alone supplies credentials through redirected stdin. */
ini_set('display_errors','0');ini_set('log_errors','0');ini_set('zend.exception_ignore_args','1');
require_once __DIR__.'/../../includes/connectors/fieldedge/OAuthClient.php';
try{
 if(PHP_SAPI!=='cli')throw new RuntimeException('worker_cli_required');$bytes=stream_get_contents(STDIN,196609);if(strlen($bytes)>196608)throw new RuntimeException('worker_input_bound');$q=json_decode($bytes,true,24,JSON_THROW_ON_ERROR);unset($bytes);
 if(($q['environment']??null)!=='Production_ClimatrolAirLLC')throw new RuntimeException('worker_environment_refused');
 $client=new \SiteEngine\AdsBridge\Connectors\FieldEdge\OAuthClient();
 $r=$client->approvedLead(['client_id'=>$q['client_id'],'client_secret'=>$q['client_secret'],'target_id'=>$q['target_id']],$q['post']??null,$q['get']);unset($q);
 echo json_encode($r+['reason_code'=>'approved_observations_ready'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){echo '{"status":"held","reason_code":"approved_transport_unconfirmed","automatic_retries":0}';exit(2);}
