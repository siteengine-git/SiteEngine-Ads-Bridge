<?php
/** Actual inquiry observer, synthetic vendor/SQL; no native claim. */
define('SEAB_INQUIRY_TEST_INCLUDE',true);ob_start();require __DIR__.'/inquiry-acceptance.php';$log=ob_get_clean();if($fails){echo $log;exit(1);}$passes=0;$fails=0;
[$contact,$old,$new,$activation]=reset_case(81);$old_custom=$contact->custom_fields()['unqualified_at'];$r=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
check_inq($r['status']==='committed'&&$contact->custom_fields()['unqualified_at']===$old_custom,'existing CRM timestamp survives activation without being rewritten');
$contact->syncCustomFieldValues(['unqualified_at'=>''],true);$contact->attachTags([103]);
SiteEngine_Ads_Bridge_Inquiries::sync_from_tag_change($contact,[103]);$row=SiteEngine_Ads_Bridge_Inquiries::get($new);
check_inq($row['lifecycle']==='unqualified'&&!empty($row['unqualified_at']),'observed tag addition records internal inquiry event without CRM timestamp');
check_inq(!array_key_exists('unqualified_at',$contact->custom_fields()),'automation does not recreate retired custom value');
$event=$row['unqualified_at'];SiteEngine_Ads_Bridge_Inquiries::sync_from_tag_change($contact,[103]);SiteEngine_Ads_Bridge_Inquiries::sync_active_from_subscriber($contact);
check_inq(SiteEngine_Ads_Bridge_Inquiries::get($new)['unqualified_at']===$event,'repeat callbacks/refresh preserve first inquiry event');
check_inq(SiteEngine_Ads_Bridge_Inquiries::get($old)['unqualified_at']===$old_custom,'previous inquiry event is unchanged');
foreach($GLOBALS['wpdb']->rows as&$fixture_row){if(($fixture_row['inquiry_id']??null)===$new)$fixture_row['unqualified_at']=null;}unset($fixture_row);SiteEngine_Ads_Bridge_Inquiries::sync_active_from_subscriber($contact);check_inq(SiteEngine_Ads_Bridge_Inquiries::get($new)['unqualified_at']===null,'historical refresh never fabricates an absent event');
echo "SUMMARY $passes PASS / $fails FAIL; synthetic vendor/SQL, actual observer\n";exit($fails?1:0);
