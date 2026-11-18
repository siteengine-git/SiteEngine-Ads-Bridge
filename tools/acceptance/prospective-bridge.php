<?php
/** Trusted owner/CLI adapter over existing pilot functions. No transport or new authority. */
final class SiteEngine_Ads_Prospective_Bridge {
    private static function require_true($v,string $reason): void {if(!$v)throw new RuntimeException($reason);}
    private static function exact(array $v,array $keys): void {self::require_true(!array_diff(array_keys($v),$keys)&&!array_diff($keys,array_keys($v)),'bridge_shape_invalid');}
    public static function handle(string $bytes): array {
        $base=['contract_version'=>'1','request_sha256'=>hash('sha256',$bytes),'ok'=>false,'execution'=>null];
        try {
            self::require_true(strlen($bytes)<=131072,'bridge_request_bound');$q=json_decode($bytes,true,32,JSON_THROW_ON_ERROR);
            self::require_true(is_array($q),'bridge_shape_invalid');self::exact($q,['contract_version','operation','route_json','route_sha256','card_json','card_sha256','intent_id','request_sha256','permit','observation_json']);
            self::require_true($q['contract_version']==='1'&&in_array($q['operation'],['review','read','claim','observe'],true),'bridge_operation_refused');
            $base+=['operation'=>$q['operation'],'route_sha256'=>$q['route_sha256'],'card_sha256'=>$q['card_sha256']];
            self::require_true(is_string($q['route_json'])&&strlen($q['route_json'])<=16384&&hash('sha256',$q['route_json'])===$q['route_sha256']&&is_string($q['card_json'])&&strlen($q['card_json'])<=32768&&hash('sha256',$q['card_json'])===$q['card_sha256'],'bridge_exact_bytes_required');
            $r=json_decode($q['route_json'],true,24,JSON_THROW_ON_ERROR);$c=json_decode($q['card_json'],true,24,JSON_THROW_ON_ERROR);
            self::require_true(is_array($r)&&is_array($c),'bridge_shape_invalid');self::exact($r,['contract_version','mode','execution_host','site','profile_path','profile_sha256','shared_runner_sha256','bridge','binding']);
            self::require_true($r['contract_version']==='1'&&$r['mode']==='double_only'&&($c['card_id']??null)==='route-'.$q['route_sha256']&&($c['mode']??null)==='review_only'&&($c['subject_kind']??null)==='synthetic','bridge_route_mode_refused');
            self::require_true(defined('ABSPATH')&&defined('WP_CLI')&&WP_CLI&&current_user_can('manage_options'),'bridge_admin_cli_required');
            $s=$r['site'];self::exact($s,['ssh_alias','owner','root','home','database_sha256','prefix','ads_manifest_sha256']);
            $home=parse_url(home_url());self::require_true(($home['host']??null)==='127.0.0.1'&&($home['port']??0)>1024&&str_contains(ABSPATH,'/.crm-acceptance/'),'bridge_isolation_required');
            global $wpdb;
            self::require_true($s['ssh_alias']==='alma-agent'&&realpath(ABSPATH)===realpath($s['root'])&&home_url()===$s['home']&&$wpdb->prefix===$s['prefix']&&hash('sha256',DB_NAME)===$s['database_sha256']&&hash_file('sha256',SEAB_PLUGIN_DIR.'SUCCESSOR-MANIFEST.json')===$s['ads_manifest_sha256'],'bridge_site_pin_mismatch');
            self::require_true(function_exists('posix_geteuid')&&function_exists('posix_getpwuid'),'bridge_owner_evidence_unavailable');$uid=posix_geteuid();$owner=posix_getpwuid($uid);
            self::require_true(($owner['name']??null)===$s['owner']&&fileowner(realpath(ABSPATH))===$uid,'bridge_owner_mismatch');
            self::require_true(realpath($r['bridge']['path']??'')===realpath(__FILE__)&&hash_file('sha256',__FILE__)===($r['bridge']['sha256']??null),'bridge_source_pin_mismatch');
            $i=json_decode($c['intent_json']??'',true,24,JSON_THROW_ON_ERROR);$b=$r['binding'];self::exact($b,['tenant_id','inquiry_id','source','operation_id','intent_sha256','request_body_sha256','credential_reference_sha256']);
            self::require_true(hash('sha256',$c['intent_json'])===$b['intent_sha256']&&($i['tenant_id']??null)===$b['tenant_id']&&($i['inquiry_id']??null)===$b['inquiry_id']&&($i['source']??null)===$b['source']&&($i['operation_id']??null)===$b['operation_id']&&($i['request']['body_sha256']??null)===$b['request_body_sha256']&&($c['target']['credential_reference_sha256']??null)===$b['credential_reference_sha256']&&($c['target']['tenant_id']??null)===$b['tenant_id'],'bridge_original_binding_mismatch');
            if(in_array($q['operation'],['review','read'],true))self::require_true($q['intent_id']===null&&$q['request_sha256']===null&&$q['permit']===null&&$q['observation_json']===null,'bridge_unused_parameters_refused');
            if($q['operation']==='review')$result=seab_prospective_pilot_review($q['card_json']);
            elseif($q['operation']==='read')$result=seab_prospective_pilot_read($q['card_sha256']);
            else {
                $read=seab_prospective_pilot_read($q['card_sha256']);$p=$read['prospective']??[];
                self::require_true(($read['status']??null)==='reviewed'&&($read['card_json']??null)===$q['card_json']&&($p['intent_id']??null)===$q['intent_id'],'bridge_review_unconfirmed');
                if($q['operation']==='claim'){
                    self::require_true($q['request_sha256']===$b['request_body_sha256']&&$q['permit']===null&&$q['observation_json']===null,'bridge_claim_binding_mismatch');
                    $result=seab_prospective_pilot_claim($q['card_sha256'],$q['intent_id'],$q['request_sha256']);
                }else {
                    self::require_true($q['request_sha256']===null&&is_string($q['permit'])&&is_string($q['observation_json'])&&strlen($q['observation_json'])<=4096,'bridge_observation_refused');
                    $result=seab_prospective_observe($q['intent_id'],$q['permit'],$q['observation_json']);
                }
            }
            $connection=$wpdb->get_var('SELECT CONNECTION_ID()');$tx=$wpdb->get_var('SELECT @@in_transaction');$name='seab_c_'.substr(SiteEngine_Ads_Bridge_Coordination::capability()['destination'],0,48);$held=$wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)',$name));
            self::require_true($wpdb->last_error===''&&ctype_digit((string)$connection)&&(int)$connection>0&&$tx==='0'&&$held===null,'bridge_postcall_proof_unavailable');
            $out=array_replace($base,['ok'=>true,'result'=>$result,'execution'=>['connection_id'=>(int)$connection,'transaction_absent'=>true,'coordination_lock_free'=>true,'owner_uid'=>$uid,'site_verified'=>true,'production_post_enabled'=>false]]);
            self::require_true(strlen(json_encode($out,JSON_THROW_ON_ERROR))<=131072,'bridge_response_bound');return $out;
        }catch(Throwable $e){$reason=preg_match('/^bridge_[a-z_]+$/D',$e->getMessage())?$e->getMessage():'bridge_outcome_unconfirmed';return $base+['reason_code'=>$reason];}
    }
}
if(isset($seab_prospective_bridge_json)){
    ini_set('display_errors','0');ini_set('log_errors','0');ini_set('zend.exception_ignore_args','1');
    echo 'SEAB_BRIDGE_V1:'.base64_encode(json_encode(SiteEngine_Ads_Prospective_Bridge::handle($seab_prospective_bridge_json),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES))."\n";
}
