<?php
/** Explicit synthetic native fixture driver. Invoke with wp eval require, never traffic. */
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type()!=='staging' ||
    !preg_match('#^http://127\.0\.0\.1:[0-9]+$#D',home_url()) || !current_user_can('manage_options')) {
    throw new RuntimeException('isolated_loopback_staging_required');
}
global $wpdb;
$mode=getenv('SEAB_DEMO_MODE')?:'inspect';
$owner='climatrol-synthetic-20261109';
$state=get_option('seab_climatrol_demo_owner',null);
$assert=static function($ok,$label) { if (!$ok) throw new RuntimeException('FAIL:'.$label); };
if ($mode==='configure') {
    $assert($state===null,'fresh_demo_allocation');
    $assert((int)$wpdb->get_var('SELECT COUNT(*) FROM `'.SiteEngine_Ads_Bridge_Inquiries::table_name().'`')===0,'fresh_inquiries');
    $assert($wpdb->insert($wpdb->prefix.'fluentform_forms',['title'=>'SYNTHETIC Climatrol demonstration','status'=>'published','form_fields'=>wp_json_encode(['fields'=>[]]),'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')])===1,'form_insert');
    $form=(int)$wpdb->insert_id;
    foreach (['New','Qualified','Unqualified','Won'] as $title) $assert($wpdb->insert($wpdb->prefix.'fc_tags',['title'=>$title,'slug'=>strtolower($title),'created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')])===1,'tag_insert');
    $assert($wpdb->insert($wpdb->prefix.'fc_lists',['title'=>'DNC','slug'=>'dnc','created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')])===1,'dnc_list_insert');
    $list=(int)$wpdb->insert_id;
    SiteEngine_Ads_Bridge_Setup::with_configuration(static function() use($form) {
        $c=SiteEngine_Ads_Bridge_Settings::get(); $c['fluent_forms']['form_id']=$form;
        $c['consent']['required_policy_version']=SiteEngine_Consent::instance()->get_config()['policy_version'];
        SiteEngine_Ads_Bridge_Settings::save($c);
    });
    $state=['owner'=>$owner,'form_id'=>$form,'dnc_id'=>$list,'fixture_ids'=>[]];
    $assert(add_option('seab_climatrol_demo_owner',$state,'',false),'owner_record');
    echo wp_json_encode(['status'=>'configured','settings_source'=>SiteEngine_Ads_Bridge_Config::instance()->configuration_source(),'form_id'=>$form]); return;
}
$assert(is_array($state)&&$state['owner']===$owner,'allocation_owned');
if (in_array($mode,['fixture','fixture-fe'],true)) {
    $fe=$mode==='fixture-fe'; $slot=$fe?'fe_fixture_ids':'fixture_ids';
    $assert(empty($state[$slot]),'no_fixture_replay');
    $number=$fe?'3':'1'; $email=$fe?'climatrol-fe-synthetic@example.invalid':'climatrol-synthetic@example.invalid';
    $cc=SiteEngine_Consent::instance()->get_config(); $authority=new SiteEngine_Consent_Authority($cc);
    $grant=$authority->save(['continuity_key'=>hash('sha256',$owner.$number),'continuity_token'=>'','event_id'=>'90000000-0000-4000-8000-00000000000'.$number,
        'method'=>'synthetic_internal_test','policy_version'=>$cc['policy_version'],'banner_version'=>$cc['banner_version'],
        'consent'=>['necessary'=>true,'analytics'=>false,'advertising'=>true,'personalization'=>false],
        'page_url'=>home_url('/synthetic-demo'),'referrer_url'=>''],'','SYNTHETIC internal demonstration');
    $assert(!is_wp_error($grant),'native_consent_capture');
    $stamp=SiteEngine_Consent_Authority::seal_stamp($authority->resolve($grant),$state['form_id']);
    $assert(($stamp['state']??null)==='verified','native_consent_resolve');
    $response=$authority->stamp(['first_name'=>'Synthetic','last_name'=>'Climatrol','email'=>$email,'phone'=>'+12025550123','gclid'=>'synthetic-not-for-google-delivery','city'=>'Test City','state'=>'IL','country'=>'US','zip_code'=>'60601'],$stamp);
    $assert($wpdb->insert($wpdb->prefix.'fluentform_submissions',['form_id'=>$state['form_id'],'response'=>wp_json_encode($response),'status'=>'unread','created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')])===1,'native_submission_insert');
    $entry=(int)$wpdb->insert_id;
    $assert($wpdb->insert($wpdb->prefix.'fc_subscribers',['email'=>$email,'first_name'=>'Synthetic','last_name'=>'Climatrol','status'=>'subscribed','created_at'=>current_time('mysql'),'updated_at'=>current_time('mysql')])===1,'native_contact_insert');
    $contact=(int)$wpdb->insert_id; $inquiry='inq_90000000-0000-4000-8000-00000000000'.$number;
    $state[$slot]=['entry_id'=>$entry,'contact_id'=>$contact,'inquiry_id'=>$inquiry];
    $state[$fe?'fe_consent_ack':'consent_ack']=$grant; update_option('seab_climatrol_demo_owner',$state,false);
    $adopt=seab_inquiry_adopt_current(['contract_version'=>'1','event_id'=>'synthetic-adopt-'.$number,'site_instance_id'=>'synthetic-isolated-demo','contact_id'=>$contact,'inquiry_id'=>$inquiry,'head_version'=>1,
        'source'=>['type'=>'fluent_form','reference'=>'form:'.$state['form_id'].':entry:'.$entry,'form_id'=>$state['form_id'],'entry_id'=>$entry]]);
    $assert($adopt['status']==='adopted','native_form_inquiry_adoption');
    echo wp_json_encode(['status'=>'fixture_persisted','form_id'=>$state['form_id'],'entry_id'=>$entry,'contact_id'=>$contact,'inquiry_id'=>$inquiry,'adopt_status'=>$adopt['status']]); return;
}
if ($mode==='fe-outcome') {
    $assert(!isset($state['fe_body']),'no_fe_fixture_resave');
    require_once SEAB_PLUGIN_DIR.'tests/support/fieldedge/ClimatrolFixture.php';
    require_once SEAB_PLUGIN_DIR.'includes/connectors/fieldedge/SubmissionAdapter.php';
    $inputs=\SiteEngine\AdsBridge\Tests\FieldEdge\ClimatrolFixture::inputs();
    $ids=$state['fe_fixture_ids']??$state['fixture_ids'];
    $inputs[1]=['inquiry_id'=>$ids['inquiry_id'],'source'=>['type'=>'fluent_form','form_id'=>$state['form_id'],'entry_id'=>$ids['entry_id']]];
    $inputs[2][0]['inquiry_id']=$ids['inquiry_id'];
    // A separate labeled synthetic tenant keeps pilot persistence cases independent.
    $tenant='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
    $inputs[0]['tenant']=$tenant; $inputs[0]['notification']['companyId']=$tenant;
    $inputs[0]['notification']['id']='90000000-0000-4000-8000-000000000009';
    $inputs[2][0]['tenant_id']=$tenant; $inputs[3]['tenant_id']=$tenant;
    $notice=json_encode($inputs[0]['notification'],JSON_THROW_ON_ERROR); $secret='synthetic-internal-demo';
    $inputs[4]=\SiteEngine\AdsBridge\Connectors\FieldEdge\Foundation::notification($notice,'sha256='.hash_hmac('sha256',$notice,$secret),$secret,$tenant);
    $packet=\SiteEngine\AdsBridge\Connectors\FieldEdge\ClimatrolWorkflow::normalize(...$inputs);
    $adapter=new \SiteEngine\AdsBridge\Connectors\FieldEdge\SubmissionAdapter();
    $body=$adapter->prepare($packet,'climatrol-fe-converted-1','climatrol-fe-review-1'); $r=json_decode($body,true);
    $o=array_intersect_key($r,array_flip(['provider','tenant_id','customer_id','job_id','invoice_id','inquiry_id','completed_at','invoice_at','amount','currency','snapshot_sha256','value_policy_id']));
    $review=seab_external_outcome_review(['evidence_id'=>'climatrol-fe-review-1','outcome'=>$o,'provenance'=>['reference'=>'synthetic-fe-normalized-packet-1','sha256'=>$packet['packet_sha256']],'synthetic'=>true,'source'=>'form']);
    $assert($review['status']==='recorded','fe_operator_review_persisted');
    $result=$adapter->send($body); $assert($result['status']==='recorded' && $result['receipt_verified']===true && $result['attribution_status']==='Attributed','actual_fe_adapter_persisted_receipt');
    $state['fe_body']=$body; $state['fe_receipt']=$result['ab_response']['receipt_id'];$state['fe_packet']=$packet;update_option('seab_climatrol_demo_owner',$state,false);
    echo wp_json_encode(['status'=>'PASS','packet_sha256'=>$packet['packet_sha256'],'actual_adapter_result'=>$result,'eligibility'=>(new SiteEngine_Ads_Bridge_CLI())->external_outcome_export($state['fe_receipt'])]);return;
}
if ($mode==='fe-readback') {
    require_once SEAB_PLUGIN_DIR.'includes/connectors/fieldedge/SubmissionAdapter.php';
    $result=(new \SiteEngine\AdsBridge\Connectors\FieldEdge\SubmissionAdapter())->send($state['fe_body']);
    $assert($result['status']==='recorded' && $result['receipt_verified']===true && $result['ab_response']['receipt_id']===$state['fe_receipt'],'fresh_fe_adapter_readback');
    echo wp_json_encode(['status'=>'PASS','actual_adapter_result'=>$result]);return;
}
if ($mode==='outcome') {
    $assert(!isset($state['body']),'no_historical_fixture_resave');
    $o=['provider'=>'fieldedge','tenant_id'=>'11111111-1111-4111-8111-111111111111','customer_id'=>'33333333-3333-4333-8333-333333333333',
        'job_id'=>'44444444-4444-4444-8444-444444444444','invoice_id'=>'44444444-4444-4444-8444-444444444444','inquiry_id'=>$state['fixture_ids']['inquiry_id'],
        'completed_at'=>'2026-11-01T14:20:00.1234567Z','invoice_at'=>'2026-11-01T00:00:00Z','amount'=>'1250.00','currency'=>'USD','snapshot_sha256'=>hash('sha256','synthetic-work-invoice-snapshot'),'value_policy_id'=>'synthetic-usd-demo-1'];
    $review=seab_external_outcome_review(['evidence_id'=>'synthetic-review-1','outcome'=>$o,'provenance'=>['reference'=>'synthetic-review-climatrol','sha256'=>hash('sha256','explicit-synthetic-job-invoice-association')],'synthetic'=>true,'source'=>'form']);
    $assert($review['status']==='recorded','review_persisted');
    $r=['contract_version'=>'1','destination'=>seab_external_outcome_capability()['destination'],'operation_id'=>'synthetic-converted-1','event_id'=>'synthetic-invoice-notice-1','event_sha256'=>hash('sha256','synthetic-provider-notice-1'),'evidence_id'=>'synthetic-review-1']+$o;
    $state['body']=wp_json_encode($r); $result=seab_external_outcome_submit($state['body']);
    $assert($result['status']==='recorded','native_outcome_recorded');
    $state['receipt']=$result['receipt_id']; $state['result']=$result; update_option('seab_climatrol_demo_owner',$state,false);
    echo wp_json_encode(['status'=>'PASS','review'=>$review,'outcome'=>$result]); return;
}
if ($mode==='readback') {
    $stored=seab_external_outcome_read($state['receipt']); $replay=seab_external_outcome_submit($state['body']);
    $assert($stored['body']===$state['body'] && json_decode($stored['response'],true)===$state['result'] && $replay===$state['result'],'fresh_request_exact_replay_readback');
    $r=json_decode($state['body'],true); $r['amount']='1251.00'; $conflict=seab_external_outcome_submit(wp_json_encode($r));
    $assert($conflict['status']==='conflict' && $conflict['reason_code']==='operation_identity_reused','conflicting_body_refused');
    echo wp_json_encode(['status'=>'PASS','fresh_replay_exact'=>true,'conflict'=>$conflict,'eligibility'=>(new SiteEngine_Ads_Bridge_CLI())->external_outcome_export($state['receipt'])]); return;
}
if ($mode==='negative-cases') {
    $assert(!isset($state['negative_results']),'negative_cases_not_repeated');
    $r=json_decode($state['body'],true); $results=[];
    $case=static function($name,array $change) use($r) { return array_replace($r,['operation_id'=>'test-'.$name,'event_id'=>'notice-'.$name,'event_sha256'=>hash('sha256',$name)],$change); };
    $alias=seab_external_outcome_submit(wp_json_encode($case('alias',[])));
    $assert($alias['status']==='recorded' && $alias['canonical_receipt_id']===$state['receipt'],'notice_deduplicated'); $results['different_notice']=$alias;
    $unmatched=seab_external_outcome_submit(wp_json_encode($case('unmatched',['evidence_id'=>'no-such-evidence'])));
    $assert($unmatched['status']==='held' && $unmatched['reason_code']==='association_missing','unmatched_held'); $results['unmatched']=$unmatched;
    $o=array_intersect_key($r,array_flip(['provider','tenant_id','customer_id','job_id','invoice_id','inquiry_id','completed_at','invoice_at','amount','currency','snapshot_sha256','value_policy_id']));
    foreach (['additional-job'=>['job_id'=>'90000000-0000-4000-8000-000000000004'],'revised-amount'=>['amount'=>'1251.00'],'additional-invoice'=>['invoice_id'=>'90000000-0000-4000-8000-000000000005']] as $name=>$change) {
        $candidate=array_replace($o,$change); $evidence='review-'.$name;
        seab_external_outcome_review(['evidence_id'=>$evidence,'outcome'=>$candidate,'provenance'=>['reference'=>'synthetic-'.$name,'sha256'=>hash('sha256',$name)],'synthetic'=>true,'source'=>'form']);
        $result=seab_external_outcome_submit(wp_json_encode($case($name,$change+['evidence_id'=>$evidence])));
        $assert($result['status']==='held' && $result['reason_code']==='additional_or_revised_outcome_requires_review',$name.'_held'); $results[$name]=$result;
    }
    // Ambiguity belongs to a separate held-only job, preserving the supported case.
    $ambiguous=array_replace($o,['job_id'=>'90000000-0000-4000-8000-000000000006']);
    foreach (['a'=>$o['inquiry_id'],'b'=>'inq_90000000-0000-4000-8000-000000000002'] as $which=>$inquiry) {
        seab_external_outcome_review(['evidence_id'=>'review-ambiguous-'.$which,'outcome'=>array_replace($ambiguous,['inquiry_id'=>$inquiry]),'provenance'=>['reference'=>'synthetic-ambiguity-review','sha256'=>hash('sha256',$which)],'synthetic'=>true,'source'=>'form']);
    }
    $result=seab_external_outcome_submit(wp_json_encode($case('ambiguous',['job_id'=>$ambiguous['job_id'],'evidence_id'=>'review-ambiguous-a'])));
    $assert($result['status']==='held' && $result['reason_code']==='association_ambiguous','ambiguous_held'); $results['ambiguous']=$result;
    $state['negative_results']=$results; update_option('seab_climatrol_demo_owner',$state,false);
    echo wp_json_encode(['status'=>'PASS','cases'=>$results]); return;
}
if ($mode==='permission-cases') {
    $assert(!isset($state['permission_results']),'permission_cases_not_repeated');
    $id=$state['fixture_ids']['contact_id']; $entry=$state['fixture_ids']['entry_id'];
    $sub=\FluentCrm\App\Models\Subscriber::find($id);
    $sub->attachLists([$state['dnc_id']]);
    try { $dnc=(new SiteEngine_Ads_Bridge_CLI())->external_outcome_export($state['receipt']); }
    finally { \FluentCrm\App\Models\Subscriber::find($id)->detachLists([$state['dnc_id']]); }
    $assert($dnc['status']==='held' && $dnc['reason_code']==='current_dnc','current_dnc_blocks_attributed_outcome');
    $table=$wpdb->prefix.'fluentform_submissions'; $before=$wpdb->get_var($wpdb->prepare("SELECT response FROM `$table` WHERE id=%d",$entry));
    $negative=json_decode($before,true); unset($negative['_se_consent_server']);
    $assert($wpdb->update($table,['response'=>wp_json_encode($negative)],['id'=>$entry])===1,'owned_missing_consent_fixture');
    try { $missing=(new SiteEngine_Ads_Bridge_CLI())->external_outcome_export($state['receipt']); }
    finally {
        $now=$wpdb->get_var($wpdb->prepare("SELECT response FROM `$table` WHERE id=%d",$entry));
        $assert($now===wp_json_encode($negative),'missing_consent_fixture_unchanged');
        $assert($wpdb->update($table,['response'=>$before],['id'=>$entry])===1,'owned_original_response_restored');
    }
    $assert($missing['status']==='held' && $missing['reason_code']==='consent_evidence_missing' && $missing['association_status']==='attributed','missing_consent_holds_export_preserves_attribution');
    $stored=seab_external_outcome_read($state['receipt']); $assert(json_decode($stored['response'],true)===$state['result'],'permission_checks_do_not_rewrite_receipt');
    $fresh=(new SiteEngine_Ads_Bridge_CLI())->external_outcome_export($state['receipt']); $assert($fresh['status']==='eligible','fresh_permission_readback');
    $state['permission_results']=['dnc'=>$dnc,'missing'=>$missing,'restored'=>'eligible']; update_option('seab_climatrol_demo_owner',$state,false);
    echo wp_json_encode(['status'=>'PASS','cases'=>$state['permission_results']]); return;
}
if ($mode==='uncertain-receipt') {
    $assert(!isset($state['uncertain_body']),'uncertain_case_not_repeated');
    $r=json_decode($state['body'],true); $r['operation_id']='test-uncertain'; $r['event_id']='notice-uncertain';
    $body=wp_json_encode($r); $count=0;
    $inject=static function($sql) use(&$count) {
        if (str_starts_with($sql,'SELECT * FROM `'.SiteEngine_Ads_Bridge_Outcomes::table('receipts').'` WHERE receipt_id=')) {
            $count++; if ($count===2) throw new RuntimeException('outcome_receipt_readback_unconfirmed');
        }
        return $sql;
    };
    add_filter('query',$inject,9999);
    try { $result=seab_external_outcome_submit($body); } finally { remove_filter('query',$inject,9999); }
    $assert($result['status']==='retry','lost_receipt_readback_returns_retry');
    $saved=seab_external_outcome_read($result['receipt_id']);
    $assert($saved && $saved['body']===$body,'uncertain_receipt_is_durably_stored');
    $state['uncertain_body']=$body; $state['uncertain_receipt']=$result['receipt_id']; update_option('seab_climatrol_demo_owner',$state,false);
    echo wp_json_encode(['status'=>'PASS','fault'=>'post-insert SELECT exception, actual native INSERT retained','result'=>$result]); return;
}
if ($mode==='uncertain-recovery') {
    $replay=seab_external_outcome_submit($state['uncertain_body']);
    $assert($replay['status']==='recorded' && $replay['canonical_receipt_id']===$state['receipt'],'fresh_uncertain_replay_recovers_without_effect');
    $primary=(int)$wpdb->get_var('SELECT COUNT(*) FROM `'.SiteEngine_Ads_Bridge_Outcomes::table('receipts').'` WHERE inquiry_key IS NOT NULL');
    $assert($primary===1,'exactly_one_primary_outcome');
    echo wp_json_encode(['status'=>'PASS','fresh_recovery'=>$replay,'primary_outcomes'=>$primary]); return;
}
if ($mode==='concurrency') {
    $assert(!isset($state['concurrency_body']),'concurrency_case_not_repeated');
    $r=json_decode($state['body'],true); $r['operation_id']='test-concurrency'; $r['event_id']='notice-concurrency'; $body=wp_json_encode($r);
    $state['concurrency_body']=$body; update_option('seab_climatrol_demo_owner',$state,false);
    $marker=dirname(rtrim(ABSPATH,'/')).'/outcome-concurrency-entered';
    $assert(!file_exists($marker),'fresh_concurrency_marker');
    $submit='echo wp_json_encode(seab_external_outcome_submit(base64_decode('.var_export(base64_encode($body),true).')));';
    $slow='$marker='.var_export($marker,true).';$once=false;add_filter("query",static function($sql)use(&$once,$marker){if(!$once&&str_starts_with($sql,"INSERT INTO `".SiteEngine_Ads_Bridge_Outcomes::table("receipts")."`")){$once=true;file_put_contents($marker,"locked");usleep(5000000);}return $sql;});'.$submit;
    $launch=static function($code) {
        $proc=proc_open([PHP_BINARY,'/usr/bin/wp','--path='.rtrim(ABSPATH,'/'),'--user=1','eval',$code],[0=>['file','/dev/null','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        if (!is_resource($proc)) throw new RuntimeException('child_launch_failed'); return [$proc,$pipes];
    };
    [$a,$ap]=$launch($slow); $deadline=microtime(true)+15;
    while (!file_exists($marker)&&microtime(true)<$deadline) usleep(20000);
    $assert(file_exists($marker),'native_writer_inside_new_receipt_boundary');
    [$b,$bp]=$launch($submit);
    $bout=stream_get_contents($bp[1]);$berr=stream_get_contents($bp[2]);$bexit=proc_close($b);
    $aout=stream_get_contents($ap[1]);$aerr=stream_get_contents($ap[2]);$aexit=proc_close($a);
    $assert($aexit===0&&$bexit===0,'native_children_exit');
    $ar=json_decode($aout,true);$br=json_decode($bout,true);
    $assert(($ar['status']??null)==='recorded'&&($br['status']??null)==='retry'&&$br['reason_code']==='coordination_busy','native_concurrent_writer_refused');
    $again=seab_external_outcome_submit($body);$assert($again===$ar,'native_contender_exact_recovery');
    $count=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM `'.SiteEngine_Ads_Bridge_Outcomes::table('receipts').'` WHERE receipt_id=%s',$ar['receipt_id']));
    $assert($count===1,'one_receipt_after_native_contention');
    $state['concurrency_results']=['a'=>$ar,'b'=>$br,'recovery_exact'=>true,'receipt_count'=>$count];update_option('seab_climatrol_demo_owner',$state,false);
    unlink($marker);echo wp_json_encode(['status'=>'PASS','cases'=>$state['concurrency_results']]);return;
}
if ($mode==='call-evidence') {
    $assert(!isset($state['call_evidence']),'call_case_not_repeated');
    $sid='CA90000000000000000000000000000001';$repo=new \SiteEngine\CallBridge\DB\CallLogRepository();
    $assert($repo->findByCallSid($sid,true)===null,'owned_call_absent');
    $id=$repo->insert(['twilio_call_sid'=>$sid,'transaction_id'=>'twilio:'.$sid,'tracking_number'=>'+12025550124','caller_number'=>'+12025550123','call_status'=>'completed','call_duration_sec'=>75,'qualification_status'=>'qualified','notes'=>'SYNTHETIC INTERNAL Climatrol handoff only; no provider call']);
    $assert(is_int($id)&&$id>0,'cb_producer_repository_insert');
    $state['call_fixture_id']=$id;update_option('seab_climatrol_demo_owner',$state,false);
    $inq=($state['fe_fixture_ids']??$state['fixture_ids'])['inquiry_id'];
    $result=seab_external_call_evidence($sid,$inq);
    $assert($result['persisted']===true && $result['call_identity']==='twilio:'.$sid && $result['reason_code']==='call_origin_google_contract_unsupported','call_identity_separate_persisted_relation_held');
    $state['call_evidence']=$result;update_option('seab_climatrol_demo_owner',$state,false);
    echo wp_json_encode(['status'=>'PASS','producer'=>'CB native repository, explicitly synthetic; no real call','intake'=>$result]);return;
}
if ($mode==='protection-cases') {
    $assert(!isset($state['protection_results']),'protection_cases_not_repeated');
    $r=json_decode($state['body'],true);$r['operation_id']='test-caller-transaction';$r['event_id']='notice-caller-transaction';
    $wpdb->query('START TRANSACTION');
    try {$transaction=seab_external_outcome_submit(wp_json_encode($r));}finally{$wpdb->query('ROLLBACK');}
    $assert($transaction['status']==='retry'&&$transaction['reason_code']==='coordination_caller_transaction','caller_transaction_refused');
    $r=json_decode($state['body'],true);$r['operation_id']='test-event-reuse';$event=seab_external_outcome_submit(wp_json_encode($r));
    $assert($event['status']==='conflict'&&$event['reason_code']==='source_event_identity_reused','source_event_reuse_refused');
    $r['amount']=1250.0;$invalid=seab_external_outcome_submit(wp_json_encode($r));
    $assert($invalid['status']==='conflict'&&$invalid['reason_code']==='invalid_request','noncanonical_amount_refused');
    // Corrupt/lost evidence readback at the other newly introduced insert boundary.
    $o=array_intersect_key(json_decode($state['body'],true),array_flip(['provider','tenant_id','customer_id','job_id','invoice_id','inquiry_id','completed_at','invoice_at','amount','currency','snapshot_sha256','value_policy_id']));
    $o['job_id']='90000000-0000-4000-8000-000000000008';
    $evidence=['evidence_id'=>'review-lost-readback','outcome'=>$o,'provenance'=>['reference'=>'synthetic-evidence-recovery','sha256'=>hash('sha256','synthetic-review-recovery')],'synthetic'=>true,'source'=>'form'];
    $reads=0;$inject=static function($sql)use(&$reads){if(str_starts_with($sql,'SELECT ')&&str_contains($sql,'`'.SiteEngine_Ads_Bridge_Outcomes::table('evidence').'` WHERE evidence_id=')&&str_contains($sql,'review-lost-readback')){if(++$reads===2)throw new RuntimeException('outcome_evidence_readback_unconfirmed');}return $sql;};
    add_filter('query',$inject,9999);$lost=false;
    try{seab_external_outcome_review($evidence);}catch(RuntimeException $e){$lost=$e->getMessage()==='outcome_evidence_readback_unconfirmed';}finally{remove_filter('query',$inject,9999);}
    $assert($lost,'evidence_post_insert_readback_loss');$state['uncertain_evidence']=$evidence;
    $state['protection_results']=['caller_transaction'=>$transaction,'source_event_reuse'=>$event,'invalid_amount'=>$invalid,'evidence_readback_loss_observed'=>true];
    update_option('seab_climatrol_demo_owner',$state,false);echo wp_json_encode(['status'=>'PASS','cases'=>$state['protection_results']]);return;
}
if ($mode==='evidence-recovery') {
    $result=seab_external_outcome_review($state['uncertain_evidence']);
    $assert($result['status']==='recorded'&&$result['reason_code']==='evidence_already_reviewed','fresh_evidence_recovery_without_duplicate');
    $count=(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM `'.SiteEngine_Ads_Bridge_Outcomes::table('evidence').'` WHERE evidence_id=%s',$state['uncertain_evidence']['evidence_id']));
    $assert($count===1,'one_review_after_readback_loss');echo wp_json_encode(['status'=>'PASS','review'=>$result,'evidence_rows'=>$count]);return;
}
if ($mode==='association-dispatch-delta') {
    $assert(!isset($state['association_dispatch_delta']),'association_delta_not_repeated');
    $r=json_decode($state['body'],true);$o=array_intersect_key($r,array_flip(['provider','tenant_id','customer_id','job_id','invoice_id','inquiry_id','completed_at','invoice_at','amount','currency','snapshot_sha256','value_policy_id']));
    $o['inquiry_id']='inq_90000000-0000-4000-8000-000000000002';
    seab_external_outcome_review(['evidence_id'=>'review-pilot-later-ambiguity','outcome'=>$o,'provenance'=>['reference'=>'synthetic-later-ambiguity','sha256'=>hash('sha256','synthetic-ambiguity')],'synthetic'=>true,'source'=>'form']);
    $held=(new SiteEngine_Ads_Bridge_CLI())->external_outcome_export($state['receipt']);
    $assert($held['status']==='held'&&$held['reason_code']==='association_ambiguous','new_ambiguity_blocks_future_dispatch');
    $assert(seab_external_outcome_submit($state['body'])===$state['result'],'ambiguity_does_not_rewrite_historical_receipt');
    $fe=(new SiteEngine_Ads_Bridge_CLI())->external_outcome_export($state['fe_receipt']);$assert($fe['status']==='eligible','independent_fe_review_remains_eligible');
    $state['association_dispatch_delta']=$held;update_option('seab_climatrol_demo_owner',$state,false);echo wp_json_encode(['status'=>'PASS','pilot_dispatch'=>$held,'receipt_unchanged'=>true,'fe_eligibility'=>'eligible']);return;
}
echo wp_json_encode(['state'=>'inspect','owner'=>$state['owner'],'fixture_ids'=>$state['fixture_ids'],'receipt'=>$state['receipt']??null]);
