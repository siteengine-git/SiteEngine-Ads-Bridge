<?php
/**
 * Version, reservation, lock and transaction failure tests using isolated doubles.
 * Run directly with PHP, never through a WordPress bootstrap.
 */
define( 'SEAB_INQUIRY_TEST_INCLUDE', true );
ob_start();
require __DIR__ . '/inquiry-acceptance.php';
$bootstrap_log = ob_get_clean();
if ( $fails ) { echo $bootstrap_log; exit(1); }
$passes = 0; $fails = 0;

function prep_for_case($id,$old,$new,$version=1) {
    return array('contract_version'=>'1','event_id'=>'evt_prepare_'.$id,'site_instance_id'=>'site-test','contact_id'=>$id,'previous_inquiry_id'=>$old,'proposed_inquiry_id'=>$new,'expected_head_version'=>$version,'request_id'=>'req_'.$id,'source'=>array('type'=>'fluent_form','form_id'=>3,'entry_id'=>$id*10+1));
}

[$contact,$old,$new,$activation]=reset_case(50);
$prep=prep_for_case(50,$old,uuid_for(5050),0);
$prep['event_id']='evt_prepare_50_competing'; $prep['request_id']='req_50_competing';
$calls=$contact->field_write_calls;
$result=SiteEngine_Ads_Bridge_Inquiries::prepare_reconciliation($prep);
check_inq('rejected'===$result['status'] && 'head_version_mismatch'===$result['reason_code'] && $calls===$contact->field_write_calls,'stale prepare version cannot reserve or reset the contact');
$prep['expected_head_version']=1;
$result=SiteEngine_Ads_Bridge_Inquiries::prepare_reconciliation($prep);
check_inq('retry'===$result['status'] && 'pending_reconciliation_exists'===$result['reason_code'] && null===SiteEngine_Ads_Bridge_Inquiries::get(uuid_for(5050)),'a second request cannot prepare a competing inquiry for the same head');
$result=SiteEngine_Ads_Bridge_Inquiries::prepare_reconciliation(prep_for_case(50,$old,$new));
check_inq('ready'===$result['status'] && 'already_prepared'===$result['reason_code'],'the matching prepare retry retains its own reservation');

foreach (array(0,-1,1,3,2.0,'2.0','2e0',true,'9223372036854775807999') as $version) {
    $bad=$activation; $bad['head_version']=$version;
    $calls=$contact->field_write_calls;
    $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($bad);
    check_inq('rejected'===$result['status'] && $calls===$contact->field_write_calls,'invalid or nonconsecutive activation version is rejected: '.json_encode($version));
}
foreach (array(1.0,'1.0',true,-1,'9223372036854775807999') as $version) {
    $bad=prep_for_case(50,$old,uuid_for(5051),$version);
    $result=SiteEngine_Ads_Bridge_Inquiries::prepare_reconciliation($bad);
    check_inq('rejected'===$result['status'] && 'invalid_expected_head_version'===$result['reason_code'],'prepare rejects a noninteger, negative or overflowing version: '.json_encode($version));
}
$bad=$activation; $bad['site_instance_id']='different-site';
$result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($bad);
check_inq('rejected'===$result['status'] && 'activation_site_mismatch'===$result['reason_code'],'activation must match the site identity used to prepare');
$bad=prep_for_case(50,$old,uuid_for(5052)); $bad['event_id']=' evt_whitespace';
$result=SiteEngine_Ads_Bridge_Inquiries::prepare_reconciliation($bad);
check_inq('rejected'===$result['status'],'correlation identifiers cannot change identity through surrounding whitespace');
$bad=prep_for_case(50,$old,uuid_for(5052)); $bad['event_id']=str_repeat('x',81);
$result=SiteEngine_Ads_Bridge_Inquiries::prepare_reconciliation($bad);
check_inq('rejected'===$result['status'] && 'invalid_event_id'===$result['reason_code'],'oversized event identities are rejected before database truncation can break retries');
$result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
check_inq('committed'===$result['status'],'valid head version advances from one to two');
$result=SiteEngine_Ads_Bridge_Inquiries::prepare_reconciliation(prep_for_case(50,$old,$new));
check_inq('committed'===$result['status'] && 'already_activated'===$result['reason_code'] && 2===$result['committed_head_version'],'prepare replay after activation reports committed rather than authorizing a second head activation');

// Advance again, then replay the earlier completed event without resetting C.
$GLOBALS['wpdb']->submissions[502]=array('form_id'=>3);
$next=uuid_for(5053); $prep=prep_for_case(50,$new,$next,2);
$prep['event_id']='evt_prepare_50_C'; $prep['request_id']='req_50_C'; $prep['source']['entry_id']=502;
$ready=SiteEngine_Ads_Bridge_Inquiries::prepare_reconciliation($prep);
$commit=$activation; $commit['event_id']='evt_activate_50_C'; $commit['previous_inquiry_id']=$new; $commit['proposed_inquiry_id']=$next; $commit['head_version']=3; $commit['reconciliation_id']=$ready['reconciliation_id'];
$result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($commit);
check_inq('committed'===$result['status'],'a later inquiry advances from two to three');
$contact->attachTags(array(102)); $calls=$contact->field_write_calls;
$result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
check_inq('committed'===$result['status'] && 'already_committed'===$result['reason_code'] && $calls===$contact->field_write_calls && $next===SiteEngine_Ads_Bridge_Inquiries::active_for_contact(50)['inquiry_id'],'replaying an archived activation never resets or reopens the current inquiry');
$result=SiteEngine_Ads_Bridge_Inquiries::prepare_reconciliation(prep_for_case(50,$old,$new));
check_inq('committed'===$result['status'] && 'archived'===$result['inquiry_state'],'historical prepare replay exposes the archived completed state');

[$contact,$old,$new,$activation]=reset_case(51);
$GLOBALS['wpdb']->update('wp_se_ads_inquiries',array('committed_head_version'=>2),array('inquiry_id'=>$old));
$calls=$contact->field_write_calls; $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
check_inq('rejected'===$result['status'] && 'activation_current_head_mismatch'===$result['reason_code'] && $calls===$contact->field_write_calls,'activation rechecks the current persisted head version before projection');

[$contact,$old,$new,$activation]=reset_case(52);
$GLOBALS['wpdb']->deny_locks=true; $calls=$contact->field_write_calls;
$result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
check_inq('retry'===$result['status'] && 'contact_transition_busy'===$result['reason_code'] && $calls===$contact->field_write_calls,'lock contention prevents activation from touching the contact');
check_inq(false===SiteEngine_Ads_Bridge_Inquiries::sync_active_from_subscriber($contact),'history mirroring also honors the contact lock');
$GLOBALS['wpdb']->deny_locks=false;
$GLOBALS['wpdb']->lock_unavailable=true; $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
check_inq('retry'===$result['status'] && 'contact_lock_unavailable'===$result['reason_code'],'unavailable database locks fail closed');
$GLOBALS['wpdb']->lock_unavailable=false;
$GLOBALS['wpdb']->lose_lock=true; $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
check_inq('retry'===$result['status'] && 'contact_lock_lost'===$result['reason_code'] && $calls===$contact->field_write_calls,'lost session lock is detected before the transition writes');
$GLOBALS['wpdb']->lose_lock=false;
// A lost session poisons that PHP request. Model a new request explicitly.
$poison=new ReflectionProperty(SiteEngine_Ads_Bridge_Inquiries::class,'connection_unusable');
$poison->setAccessible(true); $poison->setValue(null,false);
$nested=null;
$contact->on_field_write=function() use($activation,&$nested) { $nested=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation); };
$result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
check_inq('committed'===$result['status'] && 'retry'===$nested['status'] && 'contact_transition_reentrant'===$nested['reason_code'],'a callback cannot reenter the same contact transition');
check_inq(array()===$GLOBALS['wpdb']->locks,'completed transitions release their database locks');

[$contact,$old,$new,$activation]=reset_case(53);
$GLOBALS['wpdb']->storage_engine='MyISAM'; $calls=$contact->field_write_calls;
$result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
check_inq('retry'===$result['status'] && 'inquiry_table_requires_innodb'===$result['reason_code'] && $calls===$contact->field_write_calls,'nontransactional storage is rejected before the CRM reset');
$GLOBALS['wpdb']->storage_engine='InnoDB';

foreach (array('START TRANSACTION'=>'inquiry_transaction_start_failed','COMMIT'=>'inquiry_transaction_commit_failed') as $statement=>$reason) {
    $id='COMMIT'===$statement?55:54;
    [$contact,$old,$new,$activation]=reset_case($id);
    $GLOBALS['wpdb']->fail_query=$statement;
    $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
    check_inq('retry'===$result['status'] && $reason===$result['reason_code'] && 'active'===SiteEngine_Ads_Bridge_Inquiries::get($old)['state'] && 'activating'===SiteEngine_Ads_Bridge_Inquiries::get($new)['state'],'failed '.$statement.' cannot acknowledge an inquiry switch');
    $GLOBALS['wpdb']->fail_query='';
    $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
    check_inq('committed'===$result['status'],'same activation retries after '.$statement.' failure without duplicate inquiry rows');
}
foreach (array('fail_archive'=>'previous_inquiry_archive_failed','fail_activate'=>'new_inquiry_activation_failed') as $flag=>$reason) {
    [$contact,$old,$new,$activation]=reset_case('fail_archive'===$flag?56:57);
    $GLOBALS['wpdb']->$flag=true;
    $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
    check_inq('retry'===$result['status'] && $reason===$result['reason_code'] && 'active'===SiteEngine_Ads_Bridge_Inquiries::get($old)['state'] && 'activating'===SiteEngine_Ads_Bridge_Inquiries::get($new)['state'],'zero-row '.$flag.' write rolls back both inquiry pointers');
    $GLOBALS['wpdb']->$flag=false;
    $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
    check_inq('committed'===$result['status'],'retry succeeds after '.$flag.' is repaired');
}

[$contact,$old,$new,$activation]=reset_case(58);
$contact->throw_on_field_write=true; $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
check_inq('retry'===$result['status'] && array()===$GLOBALS['wpdb']->locks,'projection exceptions release the contact lock');
$contact->throw_on_field_write=false;
$result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
check_inq('committed'===$result['status'],'a subsequent call can reacquire the contact lock after an exception');

[$contact,$old,$new,$activation]=reset_case(59);
$GLOBALS['wpdb']->fail_release=true;
$result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
check_inq('retry'===$result['status'] && 'contact_lock_release_failed'===$result['reason_code'] && array()===$GLOBALS['wpdb']->locks,'an unconfirmed lock release closes the connection and withholds success');
$calls=$contact->field_write_calls;
$GLOBALS['wpdb']->fail_release=false; $poison->setValue(null,false);
$result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
check_inq('committed'===$result['status'] && 'already_committed'===$result['reason_code'] && $calls===$contact->field_write_calls,'a fresh-request retry recovers the durable commit without resetting again');

// Initial head creation must be version zero -> one and cannot race adoption.
$GLOBALS['wpdb']->submissions[600]=array('form_id'=>3);
$contact=new \FluentCrm\App\Models\Subscriber(60,array('submission_id'=>'600'),'new');
\FluentCrm\App\Models\Subscriber::$rows[60]=$contact;
$initial=array('contract_version'=>'1','event_id'=>'evt_initial_60','site_instance_id'=>'site-test','contact_id'=>60,'previous_inquiry_id'=>null,'proposed_inquiry_id'=>uuid_for(6000),'expected_head_version'=>0,'request_id'=>'req_initial_60','source'=>array('type'=>'fluent_form','form_id'=>3,'entry_id'=>600));
$ready=SiteEngine_Ads_Bridge_Inquiries::prepare_reconciliation($initial);
check_inq('ready'===$ready['status'],'a contact without inquiry history prepares its initial version-zero head');
$adopt=array('contract_version'=>'1','event_id'=>'evt_adopt_60','site_instance_id'=>'site-test','contact_id'=>60,'inquiry_id'=>uuid_for(6001),'head_version'=>0);
$result=SiteEngine_Ads_Bridge_Inquiries::adopt_current_inquiry($adopt);
check_inq('retry'===$result['status'] && 'pending_reconciliation_exists'===$result['reason_code'],'adoption cannot replace an initial inquiry already reserved by prepare');
$activation=array('contract_version'=>'1','event_id'=>'evt_initial_activate_60','site_instance_id'=>'site-test','contact_id'=>60,'previous_inquiry_id'=>null,'proposed_inquiry_id'=>uuid_for(6000),'head_version'=>1,'reconciliation_id'=>$ready['reconciliation_id']);
$result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
check_inq('committed'===$result['status'] && 1===(int)SiteEngine_Ads_Bridge_Inquiries::active_for_contact(60)['committed_head_version'],'the initial activation creates version one');

// Rollback failure is terminal for this request: close the session and refuse reuse.
[$contact,$old,$new,$activation]=reset_case(61);
$GLOBALS['wpdb']->fail_archive=true; $GLOBALS['wpdb']->fail_query='ROLLBACK';
$result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
check_inq('retry'===$result['status'] && 'inquiry_transaction_rollback_failed'===$result['reason_code'] && array()===$GLOBALS['wpdb']->locks,'unconfirmed rollback closes the failed database session and releases its locks');
$result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
check_inq('retry'===$result['status'] && 'inquiry_connection_unusable'===$result['reason_code'],'a request with an unconfirmed rollback cannot start another transition');

echo "\nSUMMARY: {$passes} PASS, {$fails} FAIL\n";
exit($fails?1:0);
