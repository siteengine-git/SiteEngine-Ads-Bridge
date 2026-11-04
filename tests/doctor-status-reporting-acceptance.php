<?php
/** Actual admin formatter/notice; WordPress/Base/Doctor doubles, no writes. */
ob_start();require __DIR__.'/setup-reporting-acceptance.php';ob_end_clean();$n=0;
function current_user_can($cap){return $GLOBALS['allowed']??true;}function wp_unslash($v){return $v;}
$options['seab_settings_v2']=$valid;$c=reset_config();SiteEngine_Ads_Bridge_Doctor::$fail=1;
$single=['status'=>'FAIL','check'=>'Google Sheets value input','message'=>'value_input_option "BAD" is invalid. Use RAW or USER_ENTERED.'];
SiteEngine_Ads_Bridge_Doctor::$rows=[$single,['status'=>'WARN','check'=>'Warning','message'=>'not a failure']];
$status=SiteEngine_Ads_Bridge_Admin::module_status();ok($status['status']==='error'&&$status['label']==='Needs Attention','one failure preserves actual error status');
ok(count($status['doctor_failures'])===1&&$status['doctor_failures'][0]['message']===$single['message'],'actual failing explanation retained exactly; WARN excluded');
ok(strpos($status['message'],$single['check'])!==false&&strpos($status['message'],$single['message'])!==false,'module message names and explains single failure');
ok(strpos($status['diagnostics_url'],'tab=diagnostics')!==false&&strpos($status['doctor_failures'][0]['corrective_url'],'tab=sheets')!==false,'direct Diagnostics and supported Sheets destinations');
$second=['status'=>'FAIL','check'=>'Currency','message'=>'Expected uppercase currency.'];$unknown=['status'=>'FAIL','check'=>'Unknown <script>check</script>','message'=>'Unsafe <img src=x onerror=alert(1)> text'];
SiteEngine_Ads_Bridge_Doctor::$fail=3;SiteEngine_Ads_Bridge_Doctor::$rows=[$single,$second,$unknown];$status=SiteEngine_Ads_Bridge_Admin::module_status();
ok(count($status['doctor_failures'])===3&&$status['status']==='error','multiple failures all retained without severity suppression');
foreach([$single,$second,$unknown]as$row)ok(strpos($status['message'],$row['check'])!==false&&strpos($status['message'],$row['message'])!==false,'message retains each check and explanation: '.$row['check']);
ok(strpos($status['doctor_failures'][1]['corrective_url'],'page=business-data')!==false,'known shared Currency check routes to its owner');
ok($status['doctor_failures'][2]['corrective_url']===''&&$status['doctor_failures'][2]['corrective_label']==='','unknown check does not invent a corrective destination');
$_GET['page']='siteengine-dashboard';ob_start();SiteEngine_Ads_Bridge_Admin::maybe_show_doctor_failures();$html=ob_get_clean();
ok(substr_count($html,'<li>')===3&&strpos($html,'tab=diagnostics')!==false&&strpos($html,'tab=sheets')!==false,'dashboard notice has every failure and clickable destinations');
ok(strpos($html,'<script>')===false&&strpos($html,'<img')===false&&strpos($html,'&lt;script&gt;')!==false,'Doctor labels/explanations escaped at HTML boundary');
$allowed=false;ob_start();SiteEngine_Ads_Bridge_Admin::maybe_show_doctor_failures();$html=ob_get_clean();ok($html==='','no diagnostic notice to unauthorized user');$allowed=true;
$_GET['page']='unrelated';ob_start();SiteEngine_Ads_Bridge_Admin::maybe_show_doctor_failures();$html=ob_get_clean();ok($html==='','unrelated administrator screens unchanged');
SiteEngine_Ads_Bridge_Doctor::$fail=0;SiteEngine_Ads_Bridge_Doctor::$rows=[];ok(SiteEngine_Ads_Bridge_Admin::module_status()['status']==='ready','no-failure Ready behavior retained');ok($writes===0,'reporting and notice never write options');
echo "SUMMARY: $n PASS; actual formatter/notice, doubled WordPress/Base/Doctor\n";
