<?php
require_once __DIR__ . '/../includes/connectors/fieldedge/PilotReview.php';
require_once __DIR__ . '/support/fieldedge/SyntheticProspectiveApi.php';
require_once __DIR__ . '/support/fieldedge/PilotCardFixture.php';
use SiteEngine\AdsBridge\Connectors\FieldEdge\PilotReview as Gate;
use SiteEngine\AdsBridge\Connectors\FieldEdge\PilotPayload as Payload;
use SiteEngine\AdsBridge\Tests\FieldEdge\SyntheticProspectiveApi as API;
$n=0;
function gateCheck(bool$v,string$s):void{global$n;if(!$v){throw new RuntimeException('FAIL:'.$s);}++$n;echo"PASS: $s\n";}
$body='{"source":"siteengine","customer":{"phone":"2025550101"},"isExistingCustomer":false,"appointment":{},"serviceRequest":{},"preferredContactMethod":"Phone"}';
$intent=array('contract_version'=>'1','provider'=>'fieldedge','tenant_id'=>'synthetic-pilot','operation_id'=>'new-operation','inquiry_id'=>'inq-new-fixture','source'=>array('type'=>'fluent_form','form_id'=>7,'entry_id'=>901,'transaction_id'=>'ff:7:901'),'request'=>array('method'=>'POST','endpoint'=>'https://api.fieldedge.com/open-api/v1/leads','body'=>$body,'body_sha256'=>hash('sha256',$body)),'synthetic'=>true);
$card=array('contract_version'=>'1','mode'=>'review_only','card_id'=>'new-card','target'=>array('provider'=>'fieldedge','environment'=>'synthetic-test','tenant_id'=>'synthetic-pilot','credential_reference_sha256'=>hash('sha256','synthetic-opaque-reference')),'intent_json'=>json_encode($intent,JSON_UNESCAPED_SLASHES),'request_budget'=>array('oauth_token_posts'=>1,'lead_posts'=>1,'automatic_retries'=>0),'unresolved'=>array(),'subject_kind'=>'synthetic');
$fixture_context=array('source'=>$intent['source'],'tenant_id'=>'synthetic-fixture-target','credential_reference_sha256'=>hash('sha256','opaque-reference'),'operation_id'=>'fresh-fixture-operation','inquiry_id'=>'inq-caller-selected','card_id'=>'fresh-fixture-card');
$fixture=\SiteEngine\AdsBridge\Tests\FieldEdge\PilotCardFixture::card($fixture_context);$fc=json_decode($fixture,true);$fi=json_decode($fc['intent_json'],true);
gateCheck($fi['source']===$intent['source']&&$fi['inquiry_id']==='inq-caller-selected'&&$fi['request']['body']===$body,'native fixture uses caller canonical context and exact minimal body');
gateCheck($fixture===\SiteEngine\AdsBridge\Tests\FieldEdge\PilotCardFixture::card($fixture_context),'fixture exact card bytes deterministic');
$bad_fixture=$fixture_context;$bad_fixture['source']['transaction_id']='ff:7:902';
try{\SiteEngine\AdsBridge\Tests\FieldEdge\PilotCardFixture::card($bad_fixture);gateCheck(false,'bad fixture');}catch(InvalidArgumentException$e){gateCheck(true,'fixture cannot manufacture mismatched source');}
$a=new API();$reviews=array();$bad=false;$production=false;$badClaim=false;$posts=0;
$review=static function(string$json)use($a,&$reviews):array{
 $sha=hash('sha256',$json);$c=json_decode($json,true);$r=$a->prepare($c['intent_json']);
 if(!isset($reviews[$sha])&&isset($a->rows[$r['intent_id']])&&$a->rows[$r['intent_id']]['claim'])return array('status'=>'held');
 $reviews[$sha]=array($json,$r['intent_id']);return $r+array('card_sha256'=>$sha,'production_post_enabled'=>false);
};
$read=static function(string$sha)use($a,&$reviews,&$bad,&$production):array{
 if(!isset($reviews[$sha]))return array('status'=>'not_found');
 return array('contract_version'=>'1','status'=>'reviewed','card_sha256'=>$sha,'card_json'=>$reviews[$sha][0].($bad?' ':''),'production_post_enabled'=>$production,'prospective'=>$a->read($reviews[$sha][1]));
};
$claim=static function(string$sha,string$id,string$d)use($a,&$badClaim):array{return $a->claim($id,$d)+array('card_sha256'=>$badClaim?str_repeat('0',64):$sha,'production_post_enabled'=>false);};
$observe=array($a,'observe');
$make=static fn(array$c):Gate=>new Gate(json_encode($c,JSON_UNESCAPED_SLASHES),$review,$read,$claim,$observe);
$transport=static function()use(&$posts):array{++$posts;return array('status'=>201,'content_type'=>'application/json','body'=>'{"data":{"leadId":"00000000-0000-4000-8000-000000000401","status":2}}');};
gateCheck(Payload::inspect($body)['valid'],'documented minimal required objects without manufactured appointment values validate structurally');
gateCheck($make($card)->review()['status']==='prepared'&&$posts===0,'review persists only card/intent double,no transport');
gateCheck($make($card)->send()['reason_code']==='live_lead_transport_disabled'&&current($a->rows)['claim']===null,'null default cannot claim or execute');
$bad=true;gateCheck($make($card)->send($transport)['status']==='held'&&$posts===0,'changed exact card bytes blocks before transport');$bad=false;
$production=true;gateCheck($make($card)->send($transport)['status']==='held'&&$posts===0,'production-enabled read cannot bypass review-only mode');$production=false;
$result=$make($card)->send($transport);gateCheck($result['status']==='succeeded'&&$posts===1,'one reviewed synthetic request succeeds through accepted adapter');
gateCheck($make($card)->send($transport)['status']==='succeeded'&&$posts===1,'reviewed exact replay produces no second transport');
$b=$card;$b['card_id']='rotated-card';gateCheck($make($b)->send($transport)['status']==='held'&&$posts===1,'new card cannot reuse consumed original intent');
foreach(array('budget','unresolved','subject','tenant','body','mode')as$case){$b=$card;
 if($case==='budget')$b['request_budget']['lead_posts']=2;
 if($case==='unresolved')$b['unresolved']=array('owner field missing');
 if($case==='subject')$b['subject_kind']='owner_designation_required';
 if($case==='tenant')$b['target']['tenant_id']='other';
 if($case==='body'){$i=$intent;$i['request']['body'].=' ';$b['intent_json']=json_encode($i);}
 if($case==='mode')$b['mode']='production';
 gateCheck($make($b)->send($transport)['reason_code']==='pilot_card_incomplete_or_unsupported'&&$posts===1,$case.' mismatch held before dispatch');
}
$object=json_decode($body,true);
$object['appointment']=new stdClass();$object['serviceRequest']=new stdClass();
foreach(array('source','customer','isExistingCustomer','appointment','serviceRequest')as$key){$x=$object;unset($x[$key]);gateCheck(!Payload::inspect(json_encode($x))['valid'],'documented required '.$key.' checked');}
$x=$object;$x['preferredContactMethod']='phone';gateCheck(in_array('preferred_contact_method_casing_unconfirmed',Payload::inspect(json_encode($x))['issues'],true),'example/schema casing disagreement stays explicit');
$x=$object;$x['customer']=array('phone'=>'2025550101','marketingEmailOptedIn'=>true);gateCheck(!Payload::inspect(json_encode($x))['valid'],'marketing optin cannot be silently enabled');
$x=$object;$x['isExistingCustomer']=true;gateCheck(!Payload::inspect(json_encode($x))['valid'],'existing phone match is insufficient for pilot identity');
$x=$object;$x['customer']=array('existingCustomerId'=>'00000000-0000-4000-8000-000000000402');$x['isExistingCustomer']=true;gateCheck(Payload::inspect(json_encode($x))['valid'],'explicit existing customer UUID supported');
$x=$object;$x['trackingId']='unknown-idempotency';gateCheck(!Payload::inspect(json_encode($x))['valid'],'unproven optional tracking transport excluded from minimal pilot');
$fresh=$card;$fresh['card_id']='fresh-claim-test';$fi=$intent;$fi['tenant_id']='synthetic-claim-test';$fresh['target']['tenant_id']=$fi['tenant_id'];$fresh['intent_json']=json_encode($fi,JSON_UNESCAPED_SLASHES);$badClaim=true;
gateCheck($make($fresh)->send($transport)['status']==='held'&&$posts===1,'mismatched card claim refuses transport and does not reset claim');
echo"RESULT: $n PASS, 0 FAIL; local review/transport doubles only; no native provider access.\n";
