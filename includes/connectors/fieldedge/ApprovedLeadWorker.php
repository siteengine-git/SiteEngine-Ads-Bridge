<?php
/** Same production handler for CLI and injected offline faults; no test mode. */
namespace SiteEngine\AdsBridge\Connectors\FieldEdge;
require_once __DIR__.'/OAuthClient.php';
final class ApprovedLeadWorker {
    public static function run(string $bytes,?OAuthClient $client=null):array {
        $trace=new LeadDiagnostics();
        try{
            $trace->enter('input');if(strlen($bytes)>196608)throw new \RuntimeException('worker_input_bound');
            $q=json_decode($bytes,true,24,JSON_THROW_ON_ERROR);
            if(!is_array($q)||($q['environment']??null)!=='Production_ClimatrolAirLLC')throw new \RuntimeException('worker_environment_refused');
            $client??=new OAuthClient();
            $r=$client->approvedLead(['client_id'=>$q['client_id']??null,'client_secret'=>$q['client_secret']??null,'target_id'=>$q['target_id']??null],$q['post']??null,$q['get']??[],$trace);unset($q);
            $trace->enter('serialization');
            // GET is arbitrary response bytes, not guaranteed UTF-8. Preserve the
            // bytes privately without letting JSON failure destroy a POST receipt.
            $body=$r['get_body'];$r['get_body_base64']=base64_encode($body);$r['get_body_sha256']=hash('sha256',$body);$r['get_body_bytes']=strlen($body);unset($r['get_body']);
            $trace->enter('complete');$r['diagnostics']=$trace->snapshot();$r['reason_code']='approved_observations_ready';
            json_encode($r,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);return $r;
        }catch(\Throwable $e){$reason=$trace->failure($e);$snapshot=$trace->snapshot();return ['status'=>'held','reason_code'=>$reason,'creation'=>$snapshot['creation_checkpoint'],'diagnostics'=>$snapshot,'automatic_retries'=>0];}
    }
    public static function encode(array $result):string {return json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);}
    public static function decodeStatusBody(array $q):string {
        if(isset($q['body_base64'])){
            if(!is_string($q['body_base64'])||strlen($q['body_base64'])>87384)throw new \InvalidArgumentException('status_body_bound');
            $body=base64_decode($q['body_base64'],true);
            if($body===false||strlen($body)>65536)throw new \InvalidArgumentException('status_body_invalid');return $body;
        }
        if(!is_string($q['body']??null)||strlen($q['body'])>65536)throw new \InvalidArgumentException('status_body_invalid');return $q['body'];
    }
}
