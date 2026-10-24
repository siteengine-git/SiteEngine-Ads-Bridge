<?php
/**
 * Durable projection recovery acceptance. Isolated doubles; no WordPress load.
 * Run directly: php tests/inquiry-recovery-acceptance.php
 */
define('SEAB_INQUIRY_TEST_INCLUDE',true);
ob_start(); require __DIR__.'/inquiry-acceptance.php'; $bootstrap_log=ob_get_clean();
if ($fails) { echo $bootstrap_log; exit(1); }
$passes=0; $fails=0;

// Stop after each possible field deletion. Replay must only send unfinished keys.
for ($boundary=1; $boundary<=10; $boundary++) {
    [$contact,$old,$new,$activation]=reset_case(100+$boundary);
    $contact->throw_after_field_keys=$boundary;
    $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
    $pending=SiteEngine_Ads_Bridge_Inquiries::get($new);
    check_inq('retry'===$result['status'] && 'activating'===$pending['state'] && !empty($pending['projection_plan']) && empty($pending['projection_verified_at_utc']) && 'active'===SiteEngine_Ads_Bridge_Inquiries::get($old)['state'],'interruption after field '.$boundary.' retains the original plan and prior history');
    $completed=array_slice(array_keys(end($contact->field_batches)),0,$boundary);
    $batches=count($contact->field_batches);
    $contact->throw_after_field_keys=0;
    $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
    $new_batches=array_slice($contact->field_batches,$batches);
    $resent=false;
    foreach($new_batches as $batch) if(array_intersect($completed,array_keys($batch))) $resent=true;
    check_inq('committed'===$result['status'] && !$resent && '4500'==(string)SiteEngine_Ads_Bridge_Inquiries::get($old)['invoiced_amount'],'recovery after field '.$boundary.' completes without resubmitting completed keys');
}

foreach(array('throw_after_detach','throw_after_attach') as $i=>$fault) {
    [$contact,$old,$new,$activation]=reset_case(120+$i);
    $contact->$fault=true;
    $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
    $fields=$contact->field_write_calls; $tags=$contact->tag_write_calls;
    check_inq('retry'===$result['status'] && 'active'===SiteEngine_Ads_Bridge_Inquiries::get($old)['state'],'interrupted '.$fault.' cannot acknowledge or archive the previous inquiry');
    $contact->$fault=false;
    $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
    check_inq('committed'===$result['status'] && $fields===$contact->field_write_calls && $contact->tag_write_calls===$tags+('throw_after_detach'===$fault?1:0),'retry after '.$fault.' performs only the missing tag operation');
}

[$contact,$old,$new,$activation]=reset_case(122);
$GLOBALS['wpdb']->fail_checkpoint=true;
$result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
$calls=$contact->field_write_calls; $tags=$contact->tag_write_calls;
check_inq('retry'===$result['status'] && 'projection_checkpoint_not_persisted'===$result['reason_code'] && empty(SiteEngine_Ads_Bridge_Inquiries::get($new)['projection_verified_at_utc']) && 'active'===SiteEngine_Ads_Bridge_Inquiries::get($old)['state'],'a failed checkpoint write withholds the pointer commit');
$GLOBALS['wpdb']->fail_checkpoint=false;
$result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
check_inq('committed'===$result['status'] && $calls===$contact->field_write_calls && $tags===$contact->tag_write_calls,'fully reset contact resumes after a missing checkpoint without any CRM write');

[$contact,$old,$new,$activation]=reset_case(123);
$GLOBALS['wpdb']->fail_activate=true;
$result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
$calls=$contact->field_write_calls; $tags=$contact->tag_write_calls;
check_inq('retry'===$result['status'] && !empty(SiteEngine_Ads_Bridge_Inquiries::get($new)['projection_verified_at_utc']),'the completed reset checkpoint survives rollback of the inquiry pointers');
$GLOBALS['wpdb']->fail_activate=false;
$result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
check_inq('committed'===$result['status'] && $calls===$contact->field_write_calls && $tags===$contact->tag_write_calls,'pointer retry uses the checkpoint without clearing or retagging the contact');

[$contact,$old,$new,$activation]=reset_case(124);
$GLOBALS['wpdb']->fail_activate=true;
SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
$GLOBALS['wpdb']->fail_activate=false;
$contact->syncCustomFieldValues(array('invoiced_amount'=>'99'),false);
$calls=$contact->field_write_calls; $tags=$contact->tag_write_calls;
$result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
check_inq('retry'===$result['status'] && 'projection_checkpoint_drift'===$result['reason_code'] && $calls===$contact->field_write_calls && $tags===$contact->tag_write_calls && '99'===($contact->custom_fields()['invoiced_amount']??''),'edits after the verified reset stop recovery and preserve the changed value');
check_inq('active'===SiteEngine_Ads_Bridge_Inquiries::get($old)['state'] && '4500'==(string)SiteEngine_Ads_Bridge_Inquiries::get($old)['invoiced_amount'],'checkpoint drift leaves prior history and active pointer intact');

[$contact,$old,$new,$activation]=reset_case(125);
$contact->throw_after_field_keys=1;
SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
$contact->throw_after_field_keys=0;
$contact->syncCustomFieldValues(array('invoiced_amount'=>'88'),false);
$calls=$contact->field_write_calls; $tags=$contact->tag_write_calls;
$result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
check_inq('retry'===$result['status'] && 'projection_recovery_conflict'===$result['reason_code'] && $calls===$contact->field_write_calls && $tags===$contact->tag_write_calls && '88'===($contact->custom_fields()['invoiced_amount']??''),'a third value after a partial reset fails preflight before any further CRM writes');

[$contact,$old,$new,$activation]=reset_case(126);
$contact->ignore_tag_writes=true;
SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
$contact->ignore_tag_writes=false;
$contact->attachTags(array(102));
$calls=$contact->field_write_calls; $tags=$contact->tag_write_calls;
$result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
check_inq('retry'===$result['status'] && 'projection_recovery_conflict'===$result['reason_code'] && $calls===$contact->field_write_calls && $tags===$contact->tag_write_calls,'an unexpected lifecycle edit during recovery is preserved for review');

[$contact,$old,$new,$activation]=reset_case(127);
$contact->throw_after_field_keys=1;
SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
$contact->throw_after_field_keys=0;
SiteEngine_Ads_Bridge_Config::instance()->overrides['exports.won_jobs.value_field']='sale_total';
$calls=$contact->field_write_calls;
$result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
check_inq('retry'===$result['status'] && 'projection_plan_configuration_changed'===$result['reason_code'] && $calls===$contact->field_write_calls,'configuration changes cannot silently rewrite a pending reset plan');
SiteEngine_Ads_Bridge_Config::instance()->overrides=array();
$result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
check_inq('committed'===$result['status'],'restoring the frozen mapping allows the same activation to resume');

[$contact,$old,$new,$activation]=reset_case(128);
$contact->ignore_tag_writes=true;
SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
$contact->ignore_tag_writes=false;
$pending=SiteEngine_Ads_Bridge_Inquiries::get($new);
$GLOBALS['wpdb']->update('wp_se_ads_inquiries',array('projection_plan'=>$pending['projection_plan'].' '),array('inquiry_id'=>$new));
$calls=$contact->field_write_calls; $tags=$contact->tag_write_calls;
$result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
check_inq('retry'===$result['status'] && 'projection_plan_corrupt'===$result['reason_code'] && $calls===$contact->field_write_calls && $tags===$contact->tag_write_calls,'corrupt stored plan cannot initiate another CRM write');

[$contact,$old,$new,$activation]=reset_case(129);
$GLOBALS['wpdb']->after_checkpoint=function()use($contact){ $contact->syncCustomFieldValues(array('invoiced_amount'=>'77'),false); };
$result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
check_inq('retry'===$result['status'] && 'projection_checkpoint_drift'===$result['reason_code'] && 'active'===SiteEngine_Ads_Bridge_Inquiries::get($old)['state'],'final readback detects an edit between checkpoint persistence and pointer commit');

[$contact,$old,$new,$activation]=reset_case(130);
$GLOBALS['wpdb']->update('wp_se_ads_inquiries',array('state'=>'activating','activation_event_id'=>$activation['event_id'],'activation_payload_hash'=>hash_for_recovery($activation),'committed_head_version'=>2),array('inquiry_id'=>$new));
$calls=$contact->field_write_calls;
$result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
check_inq('retry'===$result['status'] && 'legacy_activation_projection_unknown'===$result['reason_code'] && $calls===$contact->field_write_calls,'an older activating row without a saved original plan requires review rather than a guessed reset');

[$contact,$old,$new,$activation]=reset_case(131);
$GLOBALS['wpdb']->missing_recovery_columns=true;
$calls=$contact->field_write_calls;
$result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
check_inq('retry'===$result['status'] && 'inquiry_schema_migration_required'===$result['reason_code'] && $calls===$contact->field_write_calls,'physical recovery columns are required before activation writes');
$GLOBALS['wpdb']->missing_recovery_columns=false;
$GLOBALS['seab_options']['seab_inquiry_schema_version']='1';
$result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
check_inq('retry'===$result['status'] && 'inquiry_schema_migration_required'===$result['reason_code'] && $calls===$contact->field_write_calls,'schema-version option must also confirm explicit migration');
$GLOBALS['seab_options']['seab_inquiry_schema_version']='2';
$result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
check_inq('committed'===$result['status'],'activation proceeds once both physical and recorded schema checks pass');

[$contact,$old,$new,$activation]=reset_case(132);
$GLOBALS['wpdb']->fail_activate=true;
SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
$GLOBALS['wpdb']->fail_activate=false;
$contact->syncCustomFieldValues(array('service_type'=>'Maintenance','new_unrelated_field'=>'keep'),false);
$contact->attachTags(array(202)); $calls=$contact->field_write_calls; $tags=$contact->tag_write_calls;
$result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
check_inq('committed'===$result['status'] && $calls===$contact->field_write_calls && $tags===$contact->tag_write_calls && 'Maintenance'===($contact->custom_fields()['service_type']??'') && in_array(202,array_map(fn($t)=>(int)$t->id,$contact->tags),true),'recovery leaves unrelated fields and non-lifecycle tags intact');

[$contact,$old,$new,$activation]=reset_case(133);
$contact->syncCustomFieldValues(array('submission_id'=>'1330'),false);
$contact->throw_after_field_keys=1;
$result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
$contact->throw_after_field_keys=0;
$batches=count($contact->field_batches);
check_inq('retry'===$result['status'] && '1331'===($contact->custom_fields()['submission_id']??''),'an interruption after projecting a changed canonical submission retains its new immutable binding');
$result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
$resent=false;
foreach(array_slice($contact->field_batches,$batches) as $batch) if(array_key_exists('submission_id',$batch)) $resent=true;
check_inq('committed'===$result['status'] && !$resent && 'ff:3:1330'===SiteEngine_Ads_Bridge_Inquiries::get($old)['transaction_id'] && 'ff:3:1331'===SiteEngine_Ads_Bridge_Inquiries::get($new)['transaction_id'],'binding recovery skips the completed submission write and preserves both canonical transactions');

[$contact,$old,$new,$activation]=reset_case(134);
$GLOBALS['wpdb']->after_checkpoint=function(){ throw new \RuntimeException('injected_after_checkpoint_crash'); };
$result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
$calls=$contact->field_write_calls; $tags=$contact->tag_write_calls;
check_inq('retry'===$result['status'] && !empty(SiteEngine_Ads_Bridge_Inquiries::get($new)['projection_verified_at_utc']) && 'active'===SiteEngine_Ads_Bridge_Inquiries::get($old)['state'],'an exception immediately after persisting the checkpoint retains both the checkpoint and prior active history');
$result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
check_inq('committed'===$result['status'] && $calls===$contact->field_write_calls && $tags===$contact->tag_write_calls,'retry after checkpoint-persistence exception commits without CRM writes');

check_inq(array()===$GLOBALS['wpdb']->locks && !SiteEngine_Ads_Bridge_Inquiries::is_reconciling(),'all recovery paths release the request guard and contact locks');
echo "\nSUMMARY: {$passes} PASS, {$fails} FAIL\n";
exit($fails?1:0);

function hash_for_recovery($payload) {
    $method=new ReflectionMethod(SiteEngine_Ads_Bridge_Inquiries::class,'payload_hash');
    $method->setAccessible(true); return $method->invoke(null,$payload);
}
