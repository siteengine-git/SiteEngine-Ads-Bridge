<?php
/** Official call-upload shape only; no synthetic positive Consent authority invented. */
define('ABSPATH',__DIR__.'/');require_once __DIR__.'/../includes/class-siteengine-ads-bridge-call-import.php';$n=0;
$check=static function($v,$label)use(&$n){if(!$v)throw new RuntimeException('FAIL:'.$label);++$n;};
$facts=['caller_id'=>'+12025550101','call_start_date_time'=>'2026-11-01 01:30:00-05:00','conversion_name'=>'SYNTHETIC Reviewed Job','conversion_date_time'=>'2026-11-01 01:30:00-06:00','conversion_value'=>'1250.00','currency_code'=>'USD'];
$r=SiteEngine_Ads_Bridge_Call_Import::preview($facts);$check($r['structural_mapping']==='PASS'&&count($r['structural_preview']['values'])===8,'explicit offset DST later instant');$check(array_slice($r['structural_preview']['values'],6)===['',''],'no manufactured Consent columns');$check($r['status']==='held'&&$r['export_eligible']===false&&$r['delivery_enabled']===false,'structural mapping never authorizes');$check(!isset($r['values']),'no existing sheet dispatch row');
$bad=[];foreach(['caller_id'=>'2025550101','call_start_date_time'=>'2026-11-01 01:30:00','conversion_date_time'=>'2026-11-01 01:30:00-04:00','conversion_value'=>'-1','currency_code'=>'usd','conversion_name'=>'']as$k=>$v){$x=$facts;$x[$k]=$v;$bad[]=$x;}
$check($r['structural_preview']['values'][1]==='2026-11-01 01:30:00-0500'&&$r['structural_preview']['values'][3]==='2026-11-01 01:30:00-0600','CSV offsets use documented four digit format');
$x=$facts;$x['call_start_date_time']='2026-02-30 01:30:00-05:00';$bad[]=$x;$x=$facts;$x['consent_granted']=true;$bad[]=$x;$x=$facts;unset($x['caller_id']);$bad[]=$x;
foreach($bad as$i=>$x){$v=SiteEngine_Ads_Bridge_Call_Import::preview($x);$check($v['structural_mapping']==='FAIL'&&!isset($v['structural_preview'])&&$v['export_eligible']===false,'invalid or invented authority'.$i);}
$q=SiteEngine_Ads_Bridge_Call_Import::requirements();$check($q['existing_21_column_sheet_compatible']===false&&$q['delivery_enabled']===false,'separate call schema no sink');
echo "RESULT: $n PASS; structural synthetic fixtures only, not call Consent/Google acceptance\n";
