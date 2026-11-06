<?php
/** New exporter boundary: native WP/SQL, wholly synthetic Google HTTP double. */
if (!defined('WP_CLI') || !WP_CLI || wp_get_environment_type()!=='staging' ||
    !preg_match('#^http://127\.0\.0\.1:[0-9]+$#D',home_url()) || !current_user_can('manage_options')) throw new RuntimeException('isolated_native_fixture_required');
$owner=get_option('seab_climatrol_demo_owner');
if (($owner['owner']??null)!=='climatrol-synthetic-20261109') throw new RuntimeException('owned_demo_required');
$assert=static function($ok,$label){if(!$ok)throw new RuntimeException('FAIL:'.$label);};
$mode=getenv('SEAB_TRANSPORT_MODE');
if ($mode==='setup') {
    $assert(get_option('seab_climatrol_transport_fixture',null)===null,'fresh_transport_fixture');
    $path=dirname(rtrim(ABSPATH,'/')).'/SYNTHETIC-no-google-account.json';
    $assert(!file_exists($path),'fresh_synthetic_file');
    // Ephemeral local signing fixture, not a Google/SSH account or credential.
    // Every HTTP request is intercepted below; this key authenticates nowhere.
    $key=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
    $assert($key!==false&&openssl_pkey_export($key,$pem),'synthetic_signing_fixture');
    $bytes=wp_json_encode(['client_email'=>'synthetic@example.invalid','private_key'=>$pem]);
    $f=fopen($path,'x');$assert($f!==false,'exclusive_fixture_file');chmod($path,0600);
    $assert(fwrite($f,$bytes)===strlen($bytes)&&fflush($f),'synthetic_file_written');fclose($f);
    $before=SiteEngine_Ads_Bridge_Settings::get();$target=get_option('seab_outcome_demo_target_v1',null);
    $fixture=['settings_before'=>$before,'target_before'=>$target,'path'=>$path,'file_sha256'=>hash('sha256',$bytes)];
    add_option('seab_climatrol_transport_fixture',$fixture,'',false);
    SiteEngine_Ads_Bridge_Setup::with_configuration(static function()use($before,$path){
        $c=$before;$c['google_sheets']['enabled']=true;$c['google_sheets']['spreadsheet_id']='SYNTHETIC-native-test-sheet';$c['google_sheets']['credentials_path']=$path;
        SiteEngine_Ads_Bridge_Settings::save($c);
        update_option('seab_outcome_demo_target_v1',['destination'=>seab_external_outcome_capability()['destination'],'spreadsheet_id'=>'SYNTHETIC-native-test-sheet','tab'=>'SYNTHETIC_Climatrol_20261109'],false);
    });
    echo wp_json_encode(['status'=>'PASS','setup'=>'synthetic signing file; no real account/network','families_disabled'=>true]);return;
}
$fixture=get_option('seab_climatrol_transport_fixture');$assert(is_array($fixture),'transport_fixture_present');
if ($mode==='test') {
    $assert(!get_option('seab_climatrol_transport_sheet',null),'fresh_synthetic_worksheet');
    $cli=new SiteEngine_Ads_Bridge_CLI();$preview=$cli->external_outcome_export($owner['receipt']);$assert($preview['status']==='eligible','eligible_test_source');
    $headers=$preview['headers'];$calls=0;
    $http=static function($pre,$args,$url)use($headers,&$calls){
        $json=static fn($data)=>['headers'=>[],'body'=>wp_json_encode($data),'response'=>['code'=>200,'message'=>'OK'],'cookies'=>[]];
        if ($url==='https://oauth2.googleapis.com/token') return $json(['access_token'=>'synthetic-http-double-only']);
        if (!str_starts_with($url,'https://sheets.googleapis.com/v4/spreadsheets/SYNTHETIC-native-test-sheet/values/')) throw new RuntimeException('unexpected_http_forbidden');
        if (str_contains($url,':append?')) {
            $calls++;$rows=get_option('seab_climatrol_transport_sheet',[]);$body=json_decode($args['body'],true);$rows[]=$body['values'][0];update_option('seab_climatrol_transport_sheet',$rows,false);
            return new WP_Error('synthetic_lost_ack','Synthetic worksheet persisted, append response lost');
        }
        if (str_contains(rawurldecode($url),'!1:1')) return $json(['values'=>[$headers]]);
        if (str_contains(rawurldecode($url),'!A2:U')) return $json(['values'=>get_option('seab_climatrol_transport_sheet',[])]);
        throw new RuntimeException('unexpected_sheet_range');
    };
    add_filter('pre_http_request',$http,PHP_INT_MAX,3);
    try {
        $lost=$cli->external_outcome_export($owner['receipt'],true);
        $assert($lost['reason_code']==='outcome_append_uncertain_reconcile_required','lost_ack_holds');
        $again=$cli->external_outcome_export($owner['receipt'],true);
        $assert($again['reason_code']==='outcome_append_uncertain_reconcile_required'&&$calls===1,'uncertain_retry_no_second_append');
        $reconcile=$cli->external_outcome_export($owner['receipt'],false,true);
        $assert($reconcile['reason_code']==='outcome_append_reconciled','exact_sheet_readback_reconciles_native_ledger');
        $replay=$cli->external_outcome_export($owner['receipt'],true);
        $assert($replay['status']==='already_exported'&&$calls===1,'stage_replay_no_second_append');
        $rows=get_option('seab_climatrol_transport_sheet');$assert(count($rows)===1&&count($rows[0])===21,'one_21_column_test_double_row');
        // The physical append guard also refuses a legacy caller after intent.
        $append=new ReflectionMethod($cli,'google_sheets_append_row');$append->setAccessible(true);
        $legacy=$append->invoke($cli,'SYNTHETIC-native-test-sheet','SYNTHETIC_Climatrol_20261109',$preview['values'],'synthetic','RAW',['cid'=>$owner['fixture_ids']['contact_id'],'txn'=>$preview['values'][1]]);
        $assert(!$legacy['ok']&&$calls===1,'legacy_exporter_cannot_bypass_outcome_intent');
    } finally { remove_filter('pre_http_request',$http,PHP_INT_MAX); }
    $result=['status'=>'PASS','scope'=>'native SQL/WordPress; synthetic HTTP worksheet only','physical_append_requests'=>$calls,'test_double_rows'=>count($rows),'lost_ack'=>$lost,'retry'=>$again,'reconciliation'=>$reconcile,'replay'=>$replay,'legacy_dispatch_blocked'=>true];
    update_option('seab_climatrol_transport_result',$result,false);echo wp_json_encode($result);return;
}
if ($mode==='restore') {
    $assert(is_file($fixture['path'])&&hash_file('sha256',$fixture['path'])===$fixture['file_sha256'],'owned_synthetic_file_unchanged');
    SiteEngine_Ads_Bridge_Setup::with_configuration(static function()use($fixture){
        SiteEngine_Ads_Bridge_Settings::save($fixture['settings_before']);
        if ($fixture['target_before']===null) delete_option('seab_outcome_demo_target_v1'); else update_option('seab_outcome_demo_target_v1',$fixture['target_before'],false);
    });
    $assert(SiteEngine_Ads_Bridge_Settings::get()===$fixture['settings_before'],'isolated_settings_exactly_restored');
    unlink($fixture['path']);echo wp_json_encode(['status'=>'PASS','isolated_settings_restored'=>true,'owned_synthetic_signing_file_removed'=>true,'native_test_ledger_retained'=>true]);return;
}
throw new RuntimeException('unknown_transport_mode');
