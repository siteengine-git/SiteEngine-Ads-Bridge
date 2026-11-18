<?php
/** Pure card validator tests; native one-shot persistence proof is separate. */
define('ABSPATH',__DIR__.'/');require_once __DIR__.'/../includes/connectors/fieldedge/Foundation.php';require_once __DIR__.'/../includes/class-siteengine-ads-bridge-prospective.php';
$m=new ReflectionMethod(SiteEngine_Ads_Bridge_Prospective::class,'pilot_card');$m->setAccessible(true);$n=0;
$check=static function($v,$label)use(&$n){if(!$v)throw new RuntimeException('FAIL:'.$label);++$n;};
$intent=['contract_version'=>'1','provider'=>'fieldedge','tenant_id'=>'synthetic-card','operation_id'=>'fresh-card-operation','inquiry_id'=>'inq_90000000-0000-4000-8000-000000000001','source'=>['type'=>'fluent_form','form_id'=>3,'entry_id'=>1,'transaction_id'=>'ff:3:1'],'request'=>['method'=>'POST','endpoint'=>'https://api.fieldedge.com/open-api/v1/leads','body'=>'{"synthetic":true}','body_sha256'=>hash('sha256','{"synthetic":true}')],'synthetic'=>true];
$c=['contract_version'=>'1','mode'=>'review_only','card_id'=>'synthetic-card','target'=>['provider'=>'fieldedge','environment'=>'isolated_synthetic','tenant_id'=>'synthetic-card','credential_reference_sha256'=>hash('sha256','opaque-reference-only')],'intent_json'=>json_encode($intent,JSON_UNESCAPED_SLASHES),'request_budget'=>['oauth_token_posts'=>1,'lead_posts'=>1,'automatic_retries'=>0],'unresolved'=>[],'subject_kind'=>'synthetic'];
$bytes=json_encode($c,JSON_UNESCAPED_SLASHES);$check($m->invoke(null,$bytes)===$c,'exact complete synthetic review card');$check(hash('sha256',$bytes)!==hash('sha256',$bytes."\n"),'raw bytes pin differs on whitespace');
$bad=[];foreach(['mode'=>'production_enabled','subject_kind'=>'owner_designation_required','contract_version'=>'2','intent_json'=>'{}','unresolved'=>['owner appointment required']]as$k=>$v){$x=$c;$x[$k]=$v;$bad[]=$x;}
foreach(['tenant_id'=>'different-target','credential_reference_sha256'=>'bad','provider'=>'other']as$k=>$v){$x=$c;$x['target'][$k]=$v;$bad[]=$x;}
foreach(['lead_posts'=>2,'automatic_retries'=>1,'oauth_token_posts'=>'1']as$k=>$v){$x=$c;$x['request_budget'][$k]=$v;$bad[]=$x;}
$x=$c;$x['authorization']=true;$bad[]=$x;$x=$c;unset($x['unresolved']);$bad[]=$x;
foreach($bad as$i=>$x){$threw=false;try{$m->invoke(null,json_encode($x));}catch(Throwable $e){$threw=true;}$check($threw,'invalid card'.$i);}
foreach(['null','{',str_repeat(' ',65537)]as$x){$threw=false;try{$m->invoke(null,$x);}catch(Throwable $e){$threw=true;}$check($threw,'malformed/oversized bytes');}
echo "RESULT: $n PASS; pure validation, no SQL or transport\n";
