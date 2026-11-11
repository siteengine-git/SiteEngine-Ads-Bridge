<?php
/** Trusted prospective submission boundary; no transport, worker or new ledger. */
if (!defined('ABSPATH')) exit;
final class SiteEngine_Ads_Bridge_Prospective {
    private static function json(array $v): string { return json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES); }
    private static function hash(string $v): string { if(!preg_match('/^[a-f0-9]{64}$/D',$v))throw new InvalidArgumentException('prospective_digest_invalid');return $v; }
    private static function id($v): string { return \SiteEngine\AdsBridge\Connectors\FieldEdge\Foundation::id($v); }
    private static function rows(string $sql): array { global $wpdb;$v=$wpdb->get_results($sql,ARRAY_A);if($wpdb->last_error!==''||!is_array($v))throw new RuntimeException('prospective_read_unavailable');return $v; }
    private static function get(string $kind,string $id): ?array {
        global $wpdb;$t=SiteEngine_Ads_Bridge_Outcomes::table('evidence');$v=self::rows($wpdb->prepare("SELECT body,digest FROM `$t` WHERE evidence_id=%s",'ps-'.$kind.'-'.$id));
        if(!$v)return null;if(count($v)!==1||hash('sha256',$v[0]['body'])!==$v[0]['digest'])throw new RuntimeException('prospective_evidence_corrupt');return json_decode($v[0]['body'],true,24,JSON_THROW_ON_ERROR);
    }
    private static function put(string $kind,string $id,array $body,?string $relationship=null): void {
        global $wpdb;$bytes=self::json($body);$t=SiteEngine_Ads_Bridge_Outcomes::table('evidence');
        if($wpdb->insert($t,['evidence_id'=>'ps-'.$kind.'-'.$id,'relationship_key'=>$relationship??hash('sha256','prospective|'.$id),'digest'=>hash('sha256',$bytes),'body'=>$bytes])!==1)throw new RuntimeException('prospective_write_unconfirmed');
        if(self::get($kind,$id)!==$body)throw new RuntimeException('prospective_write_readback_unconfirmed');
    }
    private static function guarded(callable $work): array {
        if(!current_user_can('manage_options'))return ['contract_version'=>'1','status'=>'held','reason_code'=>'administrator_required'];
        try{return SiteEngine_Ads_Bridge_Outcomes::protected_call($work);}
        catch(Throwable $e){$r=$e->getMessage();return ['contract_version'=>'1','status'=>'held','reason_code'=>preg_match('/^(prospective_|coordination_|outcome_)[a-z_]+$/D',$r)?$r:'prospective_operation_unconfirmed'];}
    }
    private static function source(array $r): void {
        $inq=SiteEngine_Ads_Bridge_Inquiries::get($r['inquiry_id']);if(!$inq||!in_array($inq['state'],['active','archived'],true))throw new RuntimeException('prospective_inquiry_unavailable');
        $s=$r['source'];
        if(($s['type']??null)==='fluent_form') {
            if(!is_int($s['form_id']??null)||!is_int($s['entry_id']??null)||(int)$inq['form_id']!==$s['form_id']||(int)$inq['submission_id']!==$s['entry_id']||($s['transaction_id']??null)!==$inq['transaction_id']||$s['transaction_id']!=='ff:'.$s['form_id'].':'.$s['entry_id'])throw new RuntimeException('prospective_original_source_mismatch');
        } elseif(($s['type']??null)==='call') {
            if($inq['source_type']!=='call'||($s['identity']??null)!==$inq['transaction_id']||!preg_match('/^twilio:CA[a-f0-9]{32}$/Di',$s['identity'])||$inq['form_id']!==null||$inq['submission_id']!==null)throw new RuntimeException('prospective_original_source_mismatch');
            global $wpdb;$t=SiteEngine_Ads_Bridge_Outcomes::table('evidence');$v=self::rows($wpdb->prepare("SELECT body,digest FROM `$t` WHERE evidence_id=%s",self::id($s['evidence_id']??null)));
            if(count($v)!==1||hash('sha256',$v[0]['body'])!==$v[0]['digest'])throw new RuntimeException('prospective_call_evidence_unavailable');$e=json_decode($v[0]['body'],true,24,JSON_THROW_ON_ERROR);
            if(($e['inquiry_id']??null)!==$r['inquiry_id']||($e['call_identity']??null)!==$s['identity']||($e['evidence_contract']??null)!=='siteengine.call-evidence.v1')throw new RuntimeException('prospective_call_evidence_mismatch');
        } else throw new RuntimeException('prospective_source_type_unsupported');
    }
    private static function parse(string $body): array {
        if(strlen($body)>32768)throw new InvalidArgumentException('prospective_request_too_large');$r=json_decode($body,true,24,JSON_THROW_ON_ERROR);
        if(!is_array($r)||array_diff(array_keys($r),['contract_version','provider','tenant_id','operation_id','inquiry_id','source','request','synthetic'])||($r['contract_version']??null)!=='1'||($r['provider']??null)!=='fieldedge'||($r['synthetic']??null)!==true||!is_array($r['source']??null)||!is_array($r['request']??null))throw new InvalidArgumentException('prospective_envelope_invalid');
        foreach(['tenant_id','operation_id','inquiry_id']as$k)self::id($r[$k]??null);
        $q=$r['request'];if(array_diff(array_keys($q),['method','endpoint','body','body_sha256'])||($q['method']??null)!=='POST'||($q['endpoint']??null)!=='https://api.fieldedge.com/open-api/v1/leads'||!is_string($q['body']??null)||strlen($q['body'])>16384||!hash_equals(hash('sha256',$q['body']),self::hash($q['body_sha256']??'')))throw new InvalidArgumentException('prospective_exact_request_invalid');
        $decoded=json_decode($q['body'],false,16,JSON_THROW_ON_ERROR);if(!$decoded instanceof stdClass)throw new InvalidArgumentException('prospective_request_object_required');return $r;
    }
    public static function prepare(string $body): array {
        return self::guarded(static function()use($body){$r=self::parse($body);self::source($r);$id=hash('sha256',self::json([SiteEngine_Ads_Bridge_Outcomes::capability()['destination'],$r['provider'],$r['tenant_id'],$r['inquiry_id']]));$old=self::get('intent',$id);$base=['contract_version'=>'1','intent_id'=>$id,'body_sha256'=>$r['request']['body_sha256']];
            if($old)return $base+['status'=>$old['intent_json']===$body?'replayed':'conflict','reason_code'=>$old['intent_json']===$body?'prospective_intent_exists':'prospective_original_inquiry_intent_conflict'];
            self::put('intent',$id,['intent_json'=>$body]);return $base+['status'=>'prepared','reason_code'=>'prospective_intent_persisted'];});
    }
    private static function read_locked(string $id): array {
        $id=self::hash($id);$i=self::get('intent',$id);if(!$i)return ['contract_version'=>'1','status'=>'not_found','intent_id'=>$id];$r=self::parse($i['intent_json']);$claim=self::get('claim',$id);$o=self::get('result',$id);$status=$o?['success'=>'succeeded','validation_failed'=>'validation_failed','uncertain'=>'uncertain'][$o['observation']['classification']]:($claim?'claimed':'prepared');
        if($claim&&(!is_string($claim['permit']??null)||!preg_match('/^[a-f0-9]{64}$/D',$claim['permit'])||($claim['body_sha256']??null)!==$r['request']['body_sha256']||($claim['dispatch_mode']??null)!=='synthetic_transport_only'))throw new RuntimeException('prospective_claim_corrupt');
        $mapping=$status==='succeeded'?['provider'=>$r['provider'],'tenant_id'=>$r['tenant_id'],'lead_id'=>$o['observation']['lead_id'],'inquiry_id'=>$r['inquiry_id'],'source'=>$r['source']]:null;
        return ['contract_version'=>'1','status'=>$status,'intent_id'=>$id,'body_sha256'=>$r['request']['body_sha256'],'intent_json'=>$i['intent_json'],'claim'=>$claim,'observation'=>$o['observation']??null,'mapping'=>$mapping];
    }
    public static function read(string $id): array { return self::guarded(static fn()=>self::read_locked($id)); }
    public static function claim(string $id,string $digest): array {
        return self::guarded(static function()use($id,$digest){$v=self::read_locked($id);if(($v['status']??null)!=='prepared')return ['contract_version'=>'1','status'=>'held','reason_code'=>'prospective_already_claimed_or_unavailable','intent_id'=>$id];
            if(!hash_equals($v['body_sha256'],self::hash($digest)))return ['contract_version'=>'1','status'=>'conflict','reason_code'=>'prospective_request_digest_mismatch'];$r=self::parse($v['intent_json']);self::source($r);
            $claim=['permit'=>bin2hex(random_bytes(32)),'body_sha256'=>$digest,'dispatch_mode'=>'synthetic_transport_only'];self::put('claim',$id,$claim);return ['contract_version'=>'1','status'=>'claimed','intent_id'=>$id]+$claim;});
    }
    public static function observe(string $id,string $permit,string $body): array {
        return self::guarded(static function()use($id,$permit,$body){$v=self::read_locked($id);$c=$v['claim']??null;if(!$c||!hash_equals($c['permit'],self::hash($permit)))return ['contract_version'=>'1','status'=>'held','reason_code'=>'prospective_claim_mismatch'];
            if(strlen($body)>4096)throw new InvalidArgumentException('prospective_observation_too_large');$o=json_decode($body,true,12,JSON_THROW_ON_ERROR);
            if(!is_array($o)||array_diff(array_keys($o),['contract_version','request_body_sha256','classification','http_status','response_sha256','lead_id','lead_status','received_at'])||($o['contract_version']??null)!=='1'||($o['request_body_sha256']??null)!==$v['body_sha256']||!in_array($o['classification']??null,['success','validation_failed','uncertain'],true))throw new InvalidArgumentException('prospective_observation_invalid');
            self::hash($o['response_sha256']??'');$http=$o['http_status']??null;if($http!==null&&(!is_int($http)||$http<100||$http>599))throw new InvalidArgumentException('prospective_observation_http_invalid');
            if($o['classification']==='success'&&($http!==201||!is_string($o['lead_id']??null)||!preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/Di',$o['lead_id'])))throw new InvalidArgumentException('prospective_success_identity_invalid');
            if($o['classification']==='validation_failed'&&!in_array($http,[400,422],true))throw new InvalidArgumentException('prospective_validation_status_invalid');
            if($o['classification']!=='success'&&($o['lead_id']??null)!==null)throw new InvalidArgumentException('prospective_unresolved_identity_refused');
            foreach(['lead_status','received_at']as$k)if(($o[$k]??null)!==null&&(!is_string($o[$k])||strlen($o[$k])>160||preg_match('/[\x00-\x1f\x7f]/',$o[$k])))throw new InvalidArgumentException('prospective_observation_metadata_invalid');
            $prior=self::get('result',$id);if($prior){if($prior['observation_json']!==$body)return ['contract_version'=>'1','status'=>'conflict','reason_code'=>'observation_identity_reused'];return self::read_locked($id);}
            $relationship=null;
            if($o['classification']==='success'){
                $r=self::parse($v['intent_json']);$relationship=hash('sha256',self::json(['provider-lead','fieldedge',$r['tenant_id'],strtolower($o['lead_id'])]));global $wpdb;$t=SiteEngine_Ads_Bridge_Outcomes::table('evidence');
                $linked=self::rows($wpdb->prepare("SELECT evidence_id FROM `$t` WHERE relationship_key=%s LIMIT 2",$relationship));if($linked)return ['contract_version'=>'1','status'=>'held','reason_code'=>'prospective_provider_lead_already_bound'];
            }
            self::put('result',$id,['observation_json'=>$body,'observation'=>$o],$relationship);return self::read_locked($id);});
    }
    /** Exact provider identity lookup; no contact matching or record enumeration. */
    public static function by_lead(string $tenant,string $lead): array {
        return self::guarded(static function()use($tenant,$lead){self::id($tenant);if(!preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/Di',$lead))throw new InvalidArgumentException('prospective_lead_identity_invalid');global $wpdb;$t=SiteEngine_Ads_Bridge_Outcomes::table('evidence');$key=hash('sha256',self::json(['provider-lead','fieldedge',$tenant,strtolower($lead)]));$rows=self::rows($wpdb->prepare("SELECT evidence_id FROM `$t` WHERE relationship_key=%s LIMIT 2",$key));if(!$rows)return ['contract_version'=>'1','status'=>'not_found'];if(count($rows)!==1||!str_starts_with($rows[0]['evidence_id'],'ps-result-'))throw new RuntimeException('prospective_lead_mapping_ambiguous');return self::read_locked(substr($rows[0]['evidence_id'],10));});
    }
}
