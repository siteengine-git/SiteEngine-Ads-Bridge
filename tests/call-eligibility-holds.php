<?php
/** Consumer-gate doubles only. No positive call authority exists in Consent's contract. */
namespace FluentCrm\App\Models {final class Subscriber {public static function find($id){if(($GLOBALS['contact_mode']??'')==='throw')throw new \RuntimeException('synthetic unavailable');return $GLOBALS['contact']??null;}}}
namespace {
define('ABSPATH',__DIR__.'/');
final class SiteEngine_Ads_Bridge_Config {public static function instance(){return new self;}public function get($k,$d=null){return $GLOBALS['dnc_name']??'DNC';}}
require_once __DIR__.'/../includes/class-siteengine-ads-bridge-call-import.php';require_once __DIR__.'/../includes/class-siteengine-ads-bridge-outcomes.php';$n=0;$check=static function($v,$label)use(&$n){if(!$v)throw new \RuntimeException('FAIL:'.$label);++$n;};
$GLOBALS['contact']=(object)['lists'=>[]];$inq=['contact_id'=>1,'consent_receipt_id'=>'untrusted-raw-reference'];
$r=SiteEngine_Ads_Bridge_Outcomes::call_eligibility($inq);$check($r['reason_code']==='call_consent_authority_contract_unavailable'&&$r['google_call_delivery']===false,'missing API/reference cannot grant');
$inq['advertising']=true;$inq['_se_consent_server']=['google_consent'=>['ad_user_data'=>'granted']];$check(SiteEngine_Ads_Bridge_Outcomes::call_eligibility($inq)===$r,'copied form/browser claims cannot grant');
$GLOBALS['contact']=(object)['lists'=>[(object)['id'=>1,'title'=>'DNC']]];$check(SiteEngine_Ads_Bridge_Outcomes::call_eligibility($inq)['reason_code']==='current_dnc','current DNC veto');
$GLOBALS['contact']=null;$check(SiteEngine_Ads_Bridge_Outcomes::call_eligibility($inq)['reason_code']==='current_dnc_unavailable','missing current contact holds');
$GLOBALS['contact_mode']='throw';$check(SiteEngine_Ads_Bridge_Outcomes::call_eligibility($inq)['reason_code']==='current_dnc_unavailable','read exception holds');unset($GLOBALS['contact_mode']);
$GLOBALS['contact']=(object)['lists'=>[(object)['id'=>1]]];$check(SiteEngine_Ads_Bridge_Outcomes::call_eligibility($inq)['reason_code']==='current_dnc_unavailable','malformed current membership holds');
$GLOBALS['contact']=(object)['lists'=>[]];$GLOBALS['dnc_name']='';$check(SiteEngine_Ads_Bridge_Outcomes::call_eligibility($inq)['reason_code']==='current_dnc_unavailable','unconfigured DNC holds');
$GLOBALS['dnc_name']='DNC';$inq['consent_denied']=true;$check(SiteEngine_Ads_Bridge_Outcomes::call_eligibility($inq)['google_call_delivery']===false,'negative evidence never upgraded');
echo "RESULT: $n PASS; gate doubles only, no legitimate positive call-authority fixture claimed\n";
}
