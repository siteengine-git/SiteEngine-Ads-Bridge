<?php
/** Boundary doubles only; real CB/Linux/SQL evidence is recorded separately. */
define('ABSPATH',__DIR__.'/');
require_once __DIR__.'/../includes/class-siteengine-ads-bridge-outcomes.php';
$read=new ReflectionMethod(SiteEngine_Ads_Bridge_Outcomes::class,'read_call_snapshot');
$read->setAccessible(true);
$identity='twilio:CA00000000000000000000000000000001';
if (($argv[1]??'')==='missing') {
    $v=$read->invoke(null,$identity);
    if($v!==['ok'=>false,'reason_code'=>'call_bridge_interface_unavailable'])throw new RuntimeException('missing API did not hold');
    echo "PASS: missing supported API holds without repository fallback\n";exit;
}
eval('namespace SiteEngine\\CallBridge\\Integration; final class CallEvidence { public static function read(string $id): array { if($GLOBALS["throw_read"])throw new \\RuntimeException("synthetic private failure");return $GLOBALS["envelope"]; } }');
$passed=0;$check=static function($ok,$label)use(&$passed){if(!$ok)throw new RuntimeException('FAIL:'.$label);++$passed;echo 'PASS: '.$label."\n";};
$GLOBALS['throw_read']=false;
$unavailable=[];
foreach(['timestamp_timezone','authoritative_consent_linkage','inquiry_identity','business_qualification','advertising_eligibility','durable_child_leg_mapping']as$k)$unavailable[$k]='explicit_unavailable_reason';
$call=['identity'=>$identity,'provider'=>'twilio','provider_call_sid'=>substr($identity,7),'record_id'=>1,'created_at'=>'2026-11-11 10:00:00','acquisition'=>['matched_session_id'=>null],'number_assignment'=>['tracking_number_id'=>null],'outcome'=>['status'=>'completed','duration_sec'=>75,'technical_qualification'=>'qualified']]+array_fill_keys(array_keys($unavailable),null);
$valid=['contract'=>'siteengine.call-evidence.v1','status'=>'found','call'=>$call,'unavailable'=>$unavailable];
$GLOBALS['envelope']=$valid;
$v=$read->invoke(null,$identity);
$check($v['ok']&&$v['evidence']===$valid,'supported exact evidence and unavailable reasons retained');
$check($v['evidence']['call']['business_qualification']===null&&$v['evidence']['call']['advertising_eligibility']===null,'technical duration qualification grants no business or advertising permission');
foreach(['invalid_identity','not_found','storage_error','identity_conflict']as$status){$GLOBALS['envelope']=['contract'=>$valid['contract'],'status'=>$status,'call'=>null,'unavailable'=>[]];$v=$read->invoke(null,$identity);$check(!$v['ok']&&$v['reason_code']==='call_evidence_'.$status,'explicit supported failure status holds:'.$status);}
foreach(['contract','status','unavailable']as$k){$GLOBALS['envelope']=$valid;unset($GLOBALS['envelope'][$k]);$check(!$read->invoke(null,$identity)['ok'],'missing envelope field holds:'.$k);}
$GLOBALS['envelope']=$valid;$GLOBALS['envelope']['contract']='unknown';$check(!$read->invoke(null,$identity)['ok'],'unknown contract holds');
foreach(['identity'=>'twilio:CA00000000000000000000000000000002','provider'=>'other','provider_call_sid'=>'CA00000000000000000000000000000002','record_id'=>0]as$k=>$value){$GLOBALS['envelope']=$valid;$GLOBALS['envelope']['call'][$k]=$value;$check(!$read->invoke(null,$identity)['ok'],'persisted identity mismatch holds:'.$k);}
foreach(['acquisition','number_assignment','outcome']as$k){$GLOBALS['envelope']=$valid;$GLOBALS['envelope']['call'][$k]=null;$check(!$read->invoke(null,$identity)['ok'],'missing persisted evidence group holds:'.$k);}
foreach(array_keys($unavailable)as$k){$GLOBALS['envelope']=$valid;unset($GLOBALS['envelope']['unavailable'][$k]);$check(!$read->invoke(null,$identity)['ok'],'missing explicit unavailable reason holds:'.$k);}
$GLOBALS['throw_read']=true;$v=$read->invoke(null,$identity);$check(!$v['ok']&&!str_contains(json_encode($v),'private'),'exception holds without exposing private failure');
echo "RESULT: $passed PASS; boundary doubles only, no installed/native claim\n";
