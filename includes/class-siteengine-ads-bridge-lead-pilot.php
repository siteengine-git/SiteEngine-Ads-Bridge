<?php
/** FieldEdge Gate A: extend the existing AB evidence store, never an FE ledger. */
if (!defined('ABSPATH')) exit;

trait SiteEngine_Ads_Bridge_Lead_Pilot {
    private static function lead_uuid($value): string {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/Di',$value)) throw new InvalidArgumentException('prospective_guid_invalid');
        return strtolower($value);
    }
    private static function lead_parse(string $bytes): array {
        if (strlen($bytes)>24576) throw new InvalidArgumentException('prospective_request_too_large');
        $r=json_decode($bytes,true,20,JSON_THROW_ON_ERROR);
        $keys=['contract_version','provider','tenant_id','inquiry_id','source','guid','request','synthetic'];
        if (!is_array($r)||count($r)!==count($keys)||array_diff(array_keys($r),$keys)||($r['contract_version']??null)!=='2'||($r['provider']??null)!=='fieldedge'||!is_bool($r['synthetic']??null)||!is_array($r['source']??null)) throw new InvalidArgumentException('prospective_envelope_invalid');
        self::lead_uuid($r['tenant_id']);self::id($r['inquiry_id']);$guid=self::lead_uuid($r['guid']);
        if ($r['guid']!==$guid||$r['tenant_id']!==strtolower($r['tenant_id'])) throw new InvalidArgumentException('prospective_guid_canonical_required');
        $q=$r['request']??null;
        if (!is_array($q)||count($q)!==4||array_diff(array_keys($q),['method','endpoint','body','body_sha256'])||($q['method']??null)!=='POST'||($q['endpoint']??null)!=='https://api.fieldedge.com/open-api/v1/leads'||!is_string($q['body']??null)||strlen($q['body'])>16384||hash('sha256',$q['body'])!==($q['body_sha256']??null)) throw new InvalidArgumentException('prospective_exact_request_invalid');
        $payload=json_decode($q['body'],true,16,JSON_THROW_ON_ERROR);
        if (!is_array($payload)||($payload['trackingId']??null)!==$guid) throw new InvalidArgumentException('prospective_tracking_guid_required');
        return $r;
    }
    private static function lead_identity(array $r): string {
        return hash('sha256',self::json(['lead-guid-2',SiteEngine_Ads_Bridge_Outcomes::capability()['destination'],$r['tenant_id'],$r['inquiry_id']]));
    }
    private static function lead_key(string $tenant,string $guid): string { return hash('sha256',self::json(['lead-guid-2',$tenant,$guid])); }
    private static function lead_binding(string $id): array {
        self::hash($id);$v=self::get('lead',$id);
        if (!$v) throw new RuntimeException('prospective_reservation_missing');
        $r=self::lead_parse($v['envelope']);
        if (self::lead_identity($r)!==$id) throw new RuntimeException('prospective_reservation_corrupt');
        self::source($r);
        return $r;
    }
    /** Exact bytes and immutable GUID precede any claim. One row is the reservation. */
    public static function lead_prepare(string $bytes): array {
        return self::guarded(static function()use($bytes){
            $r=self::lead_parse($bytes);self::source($r);$id=self::lead_identity($r);$old=self::get('lead',$id);
            if ($old) return ['contract_version'=>'2','status'=>$old['envelope']===$bytes?'replayed':'conflict','intent_id'=>$id,'reason_code'=>'prospective_original_inquiry_reserved','production_post_enabled'=>false];
            global $wpdb;$t=SiteEngine_Ads_Bridge_Outcomes::table('evidence');$key=self::lead_key($r['tenant_id'],$r['guid']);
            if (self::rows($wpdb->prepare("SELECT evidence_id FROM `$t` WHERE relationship_key=%s LIMIT 1",$key))) return ['contract_version'=>'2','status'=>'conflict','reason_code'=>'prospective_guid_already_reserved'];
            self::put('lead',$id,['envelope'=>$bytes],$key);
            return ['contract_version'=>'2','status'=>'prepared','intent_id'=>$id,'guid'=>$r['guid'],'request_body_sha256'=>$r['request']['body_sha256'],'production_post_enabled'=>false];
        });
    }
    private static function lead_history(string $id): array {
        global $wpdb;$t=SiteEngine_Ads_Bridge_Outcomes::table('evidence');$key=hash('sha256','lead-observation-2|'.$id);$out=[];
        foreach(self::rows($wpdb->prepare("SELECT body,digest FROM `$t` WHERE relationship_key=%s",$key))as$row){
            if(hash('sha256',$row['body'])!==$row['digest']) throw new RuntimeException('prospective_evidence_corrupt');
            $v=json_decode($row['body'],true,16,JSON_THROW_ON_ERROR);$out[]=$v;
        }
        usort($out,static fn($a,$b)=>$a['sequence']<=>$b['sequence']);$previous=null;
        foreach($out as$n=>$v){if($v['sequence']!==$n+1||$v['previous_sha256']!==$previous)throw new RuntimeException('prospective_observation_chain_corrupt');$previous=hash('sha256',self::json($v));}
        return $out;
    }
    private static function lead_read_locked(string $id): array {
        $r=self::lead_binding($id);$c=self::get('leadclaim',$id);$result=self::get('leadresult',$id);$history=self::lead_history($id);$latest=$history?end($history):null;
        return ['contract_version'=>'2','intent_id'=>$id,'reservation'=>$r,'claim'=>$c,'creation_result'=>$result,'observations'=>$history,'latest_sha256'=>$latest?hash('sha256',self::json($latest)):null,'status'=>$latest?$latest['status']:($result?$result['status']:($c?'uncertain':'prepared')),'production_post_enabled'=>false,'crm_write_allowed'=>false,'export_eligible'=>false];
    }
    public static function lead_read(string $id): array { return self::guarded(static fn()=>self::lead_read_locked($id)); }
    public static function lead_claim(string $id,string $digest): array {
        return self::guarded(static function()use($id,$digest){
            $r=self::lead_binding($id);
            if(!$r['synthetic'])return ['status'=>'held','reason_code'=>'prospective_real_post_approval_required','production_post_enabled'=>false];
            if($digest!==$r['request']['body_sha256'])return ['status'=>'conflict','reason_code'=>'prospective_request_digest_mismatch'];
            if(self::get('leadclaim',$id))return ['status'=>'held','reason_code'=>'prospective_already_claimed_get_guid_required','guid'=>$r['guid']];
            $c=['permit'=>bin2hex(random_bytes(32)),'request_body_sha256'=>$digest,'dispatch_mode'=>'injected_transport_only'];self::put('leadclaim',$id,$c);
            return ['status'=>'claimed','intent_id'=>$id,'guid'=>$r['guid']]+$c;
        });
    }
    /** Append the first POST outcome only. A duplicate/400 never authorizes repost. */
    public static function lead_result(string $id,string $permit,string $bytes): array {
        return self::guarded(static function()use($id,$permit,$bytes){
            $r=self::lead_binding($id);$c=self::get('leadclaim',$id);
            if(!$c||!hash_equals($c['permit'],self::hash($permit)))throw new RuntimeException('prospective_claim_mismatch');
            if(strlen($bytes)>4096)throw new InvalidArgumentException('prospective_observation_too_large');$o=json_decode($bytes,true,12,JSON_THROW_ON_ERROR);
            if(!is_array($o)||count($o)!==4||array_diff(array_keys($o),['http_status','response_sha256','lead_id','request_body_sha256'])||($o['request_body_sha256']??null)!==$r['request']['body_sha256'])throw new InvalidArgumentException('prospective_observation_invalid');
            self::hash($o['response_sha256']??'');$http=$o['http_status']??null;
            if($http!==null&&(!is_int($http)||$http<100||$http>599))throw new InvalidArgumentException('prospective_observation_http_invalid');
            if(($o['lead_id']??null)!==null&&self::lead_uuid($o['lead_id'])!==$r['guid'])throw new InvalidArgumentException('prospective_provider_guid_mismatch');
            $old=self::get('leadresult',$id);if($old)return ['status'=>$old['exact_json']===$bytes?'replayed':'conflict','reason_code'=>'prospective_creation_result_immutable'];
            $v=['exact_json'=>$bytes,'observation'=>$o,'status'=>$http===201&&($o['lead_id']??null)===$r['guid']?'created':'uncertain','recovery'=>'GET stored GUID; never repeat POST'];self::put('leadresult',$id,$v);return ['status'=>$v['status'],'guid'=>$r['guid'],'recovery'=>$v['recovery']];
        });
    }
    /** GET observations extend history; they never overwrite the creation result. */
    public static function lead_observe(string $id,string $event,?string $previous,string $tenant,string $guid,int $http,string $body,string $at): array {
        return self::guarded(static function()use($id,$event,$previous,$tenant,$guid,$http,$body,$at){
            $r=self::lead_binding($id);self::id($event);
            if(!self::get('leadclaim',$id))throw new RuntimeException('prospective_claim_required');
            if($tenant!==$r['tenant_id']||$guid!==$r['guid'])throw new RuntimeException('prospective_observation_binding_mismatch');
            if(strlen($body)>65536||$http<100||$http>599)throw new InvalidArgumentException('prospective_observation_invalid');
            $at=\SiteEngine\AdsBridge\Connectors\FieldEdge\Foundation::timestamp($at);
            $request=['event'=>$event,'previous'=>$previous,'tenant'=>$tenant,'guid'=>$guid,'http'=>$http,'response_sha256'=>hash('sha256',$body),'at'=>$at];$digest=hash('sha256',self::json($request));$key=hash('sha256',$id.'|'.$event);$old=self::get('leadobs',$key);
            if($old)return ['status'=>$old['request_sha256']===$digest?'replayed':'conflict','observation'=>$old];
            $history=self::lead_history($id);$last=$history?end($history):null;$head=$last?hash('sha256',self::json($last)):null;
            if($previous!==$head||($last&&strtotime($at)<strtotime($last['observed_at'])))return ['status'=>'held','reason_code'=>'prospective_observation_out_of_order'];
            if(count($history)>=64)return ['status'=>'held','reason_code'=>'prospective_observation_review_required'];
            $status='held';$reason='provider_get_unconfirmed';$provider=null;
            if($http===200){
                try{$p=\SiteEngine\AdsBridge\Connectors\FieldEdge\LeadStatusProjection::examine($tenant,$r['inquiry_id'],$guid,$body);$provider=$p['provider_status'];$status=$p['needs_review']?'held':'observed';$reason=$p['needs_review']?'provider_status_unknown':'provider_identity_status_verified';}
                catch(Throwable $e){$reason='provider_identity_or_shape_mismatch';}
            }
            $v=['sequence'=>count($history)+1,'previous_sha256'=>$head,'request_sha256'=>$digest,'response_sha256'=>hash('sha256',$body),'observed_at'=>$at,'http_status'=>$http,'provider_status'=>$provider,'status'=>$status,'reason_code'=>$reason];
            self::put('leadobs',$key,$v,hash('sha256','lead-observation-2|'.$id));return ['status'=>$status,'observation'=>$v,'observation_sha256'=>hash('sha256',self::json($v)),'crm_write_allowed'=>false,'export_eligible'=>false];
        });
    }
}
