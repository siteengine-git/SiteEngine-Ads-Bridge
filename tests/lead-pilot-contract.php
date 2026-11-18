<?php
/** Pure parsers only. Native SQL acceptance is recorded separately. */
define('ABSPATH',__DIR__.'/');
require_once __DIR__.'/../includes/connectors/fieldedge/Foundation.php';
require_once __DIR__.'/../includes/class-siteengine-ads-bridge-prospective.php';
require_once __DIR__.'/../includes/connectors/fieldedge/LeadPilotDispatch.php';
require_once __DIR__.'/support/fieldedge/LeadFixture.php';
use SiteEngine\AdsBridge\Connectors\FieldEdge\LeadPilotDispatch;
use SiteEngine\AdsBridge\Connectors\FieldEdge\LeadCreateDryRun;
use SiteEngine\AdsBridge\Connectors\FieldEdge\LeadStatusProjection;
$n=0;$check=static function($ok,$label)use(&$n){if(!$ok)throw new RuntimeException('FAIL '.$label);$n++;};
$input=\SiteEngine\AdsBridge\Tests\FieldEdge\LeadFixture::input();$dry=LeadCreateDryRun::prepare($input);
$context=['tenant_id'=>'00000000-0000-4000-8000-000000000001','inquiry_id'=>'inq_00000000-0000-4000-8000-000000000002','source'=>$input['inquiry']['source']+['transaction_id'=>'ff:'.$input['inquiry']['source']['form_id'].':'.$input['inquiry']['source']['entry_id']]];
$guid='00000000-0000-4000-8000-000000000003';$bytes=LeadPilotDispatch::envelope($dry,$context,$guid);$r=json_decode($bytes,true);
$parse=new ReflectionMethod(SiteEngine_Ads_Bridge_Prospective::class,'lead_parse');$parse->setAccessible(true);
$check($parse->invoke(null,$bytes)===$r,'exact GUID body');
$cases=[];foreach(['guid'=>'invalid','tenant_id'=>'synthetic-invalid','contract_version'=>'1','synthetic'=>'true','provider'=>'other']as$k=>$v){$c=$r;$c[$k]=$v;$cases[]=$c;}
$c=$r;$c['request']['body_sha256']=str_repeat('0',64);$cases[]=$c;
$c=$r;$c['request']['endpoint']='https://evil.invalid/leads';$cases[]=$c;
$c=$r;$c['request']['body']='{}';$c['request']['body_sha256']=hash('sha256','{}');$cases[]=$c;
$c=$r;$c['credential']='forbidden';$cases[]=$c;
foreach($cases as$c){$held=false;try{$parse->invoke(null,json_encode($c));}catch(Throwable $e){$held=true;}$check($held,'malformed envelope rejected');}
$live=$r;$live['synthetic']=false;$check($parse->invoke(null,json_encode($live))===$live,'real envelope may be reserved but never dispatched');
foreach(['Accepted','Rejected','QuoteAccepted','QuoteRejected','QuoteExpired','JobCanceled','JobCompleted','JobFinalized','Unexpected']as$status){$p=LeadStatusProjection::examine($context['tenant_id'],$context['inquiry_id'],$guid,json_encode(['data'=>['leadId'=>$guid,'trackingId'=>$guid,'status'=>$status]]));$check($p['needs_review']===($status==='Unexpected')&&$p['crm_lifecycle_write_allowed']===false,'reviewed allowlist without CRM interpretation');}
$held=false;try{LeadStatusProjection::examine($context['tenant_id'],$context['inquiry_id'],$guid,json_encode(['data'=>['leadId'=>$guid,'trackingId'=>'00000000-0000-4000-8000-000000000099','status'=>'Accepted']]));}catch(Throwable $e){$held=true;}$check($held,'mismatched tracking rejected');
$check(LeadPilotDispatch::send('not-used','not-used')['status']==='held','no default network transport');
echo "RESULT: $n PASS; parsers only, no native persistence claim\n";
