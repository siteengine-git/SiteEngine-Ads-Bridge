<?php
/** Pure contract validation only; native durable evidence is recorded separately. */
define('ABSPATH',__DIR__.'/');
require_once __DIR__.'/../includes/connectors/fieldedge/Foundation.php';
require_once __DIR__.'/../includes/class-siteengine-ads-bridge-prospective.php';
$parse=new ReflectionMethod(SiteEngine_Ads_Bridge_Prospective::class,'parse');$parse->setAccessible(true);$passes=0;
$check=static function($ok,$label)use(&$passes){if(!$ok)throw new RuntimeException('FAIL:'.$label);$passes++;echo 'PASS: '.$label."\n";};
$r=['contract_version'=>'1','provider'=>'fieldedge','tenant_id'=>'synthetic-test','operation_id'=>'synthetic-operation','inquiry_id'=>'inq_90000000-0000-4000-8000-000000000001','source'=>['type'=>'fluent_form','form_id'=>1,'entry_id'=>1,'transaction_id'=>'ff:1:1'],'request'=>['method'=>'POST','endpoint'=>'https://api.fieldedge.com/open-api/v1/leads','body'=>'{"synthetic":true}','body_sha256'=>hash('sha256','{"synthetic":true}')],'synthetic'=>true];
$check($parse->invoke(null,json_encode($r))===$r,'exact envelope/request accepted for canonical source check');
$cases=[];foreach(['contract_version'=>'2','provider'=>'other','synthetic'=>false]as$k=>$v){$c=$r;$c[$k]=$v;$cases[]=$c;}
foreach(['method'=>'GET','endpoint'=>'https://evil.invalid/leads','body_sha256'=>str_repeat('a',64),'body'=>'[]']as$k=>$v){$c=$r;$c['request'][$k]=$v;if($k==='body')$c['request']['body_sha256']=hash('sha256',$v);$cases[]=$c;}
$c=$r;$c['credential']='forbidden';$cases[]=$c;$c=$r;$c['request']['headers']=[];$cases[]=$c;$c=$r;$c['request']['body']=str_repeat('x',16385);$cases[]=$c;
foreach($cases as$n=>$c){$refused=false;try{$parse->invoke(null,json_encode($c));}catch(Throwable $e){$refused=true;}$check($refused,'unsupported/malformed request refused case'.$n);}
foreach(['{','null',str_repeat(' ',32769)]as$bad){$refused=false;try{$parse->invoke(null,$bad);}catch(Throwable $e){$refused=true;}$check($refused,'invalid/oversized envelope refused');}
echo "RESULT: $passes PASS;validation only,no native/transport claim\n";
