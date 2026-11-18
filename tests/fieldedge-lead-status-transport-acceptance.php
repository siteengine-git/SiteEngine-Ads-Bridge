<?php
/**
 * Exact read-only status poll through the existing authenticated transport.
 * Synthetic credentials and responses only. Nothing reaches FieldEdge.
 */
require_once __DIR__ . '/../includes/connectors/fieldedge/OAuthClient.php';
require_once __DIR__ . '/../includes/connectors/fieldedge/LeadStatusDiscovery.php';
use SiteEngine\AdsBridge\Connectors\FieldEdge\OAuthClient;
use SiteEngine\AdsBridge\Connectors\FieldEdge\LeadStatusDiscovery as S;
use SiteEngine\AdsBridge\Connectors\FieldEdge\SelectedCaseJournal as J;
use SiteEngine\AdsBridge\Connectors\FieldEdge\TargetedRead as T;

$n=0;$test=static function(bool $ok,string $why)use(&$n){if(!$ok){throw new RuntimeException('FAIL '.$why);}++$n;};
$tenant='22222222-2222-4222-8222-222222222222';
$lead='11111111-1111-4111-8111-111111111111';
$case=['schema'=>1,'tenant_id'=>$tenant,'inquiry_id'=>'ff:39:889','lead_id'=>$lead];
$creds=['client_id'=>'SYNTHETIC','client_secret'=>'SYNTHETIC-SECRET','target_id'=>$tenant];
$token=['status'=>200,'content_type'=>'application/json','body'=>'{"access_token":"synthetic.token.with-three-segments","token_type":"Bearer","expires_in":3600}'];
$provider=['status'=>200,'content_type'=>'application/json','body'=>json_encode(['data'=>[
    'leadId'=>$lead,'trackingId'=>$lead,'status'=>'JobFinalized',
    'customer'=>['email'=>'PRIVATE-DO-NOT-LEAK@example.invalid'],
    'workOrderId'=>'33333333-3333-4333-8333-333333333333'
]],JSON_THROW_ON_ERROR)];
$requests=[];
$transport=static function(string $method,string $url,array $headers,string $body)use(&$requests,$token,$provider){
    $requests[]=['method'=>$method,'url'=>$url,'authorization'=>in_array('Authorization: Bearer synthetic.token.with-three-segments',$headers,true)];
    if($url===OAuthClient::TOKEN_URL)return $token;
    if(str_starts_with($url,'https://api.fieldedge.com/open-api/v1/leads/'))return $provider;
    throw new RuntimeException('unexpected_request');
};
$journal_path=sys_get_temp_dir().'/fe-lead-http-'.bin2hex(random_bytes(8)).'.json';
$j=new J($journal_path,str_repeat('a',64));
$result=(new OAuthClient($transport,static fn():int=>100))->selectedCase($creds,$j,static fn($get,$journal)=>S::run($get,$journal,$case));
$test($result['reason_code']==='selected_case_completed','end-to-end synthetic GET accepted');
$test(count($requests)===2,'one token + one business GET');
$test($requests[0]['method']==='POST' && $requests[0]['url']===OAuthClient::TOKEN_URL,'exact token route');
$test($requests[1]['method']==='GET' && $requests[1]['url']===T::url('lead',['id'=>$lead]),'exact lead route');
$test($requests[1]['authorization'],'bearer used only in transport');
$test($result['findings']['status']==='JobFinalized','confirmed lifecycle reported');
$test($result['findings']['outcome']==='job_finalized','evidence preserved');
$test($result['findings']['work_order_id']===null,'fabricated response WO ignored');
$test($result['findings']['crm_lifecycle_write_allowed']===false,'no tag mutation');
$test($result['findings']['advertising_export_allowed']===false,'no advertising export');
$test($result['journal']['closed']===true && $result['journal']['token_attempts']===1,'journal closed and token limited');
$test(count($result['journal']['attempts'])===1 && $result['journal']['attempts'][0]['http_status']===200,'single GET in durable journal');
$test(!str_contains(json_encode($result),'PRIVATE-DO-NOT-LEAK'),'PII absent from output');
$test(!str_contains(json_encode($result),'synthetic.token'),'token absent from output');
$test(!str_contains(json_encode($result),'SYNTHETIC-SECRET'),'secret absent from output');
unset($j);
$stored = file_get_contents($journal_path);
$test(is_string($stored) && (json_decode($stored,true,16,JSON_THROW_ON_ERROR)['closed']??false)===true,'journal persisted after exclusive writer closes');
unlink($journal_path);
echo "PASS $n synthetic authenticated lead GET/journal assertions; external requests=0\n";
