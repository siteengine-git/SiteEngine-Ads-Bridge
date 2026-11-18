<?php
/** Gate B extends existing prospective evidence/claims; no second state store. */
if(!defined('ABSPATH'))exit;
require_once __DIR__.'/connectors/fieldedge/LiveLeadPayload.php';
trait SiteEngine_Ads_Bridge_Lead_Approval {
    private static function lead_card_value(string $id,string $bytes):array {
        if(strlen($bytes)>32768)throw new InvalidArgumentException('prospective_card_too_large');
        $c=json_decode($bytes,true,20,JSON_THROW_ON_ERROR);$r=self::lead_binding($id);
        $keys=['contract_version','intent_id','inquiry_id','tenant_id','guid','request_body','request_body_sha256','environment','expires_at','effects','budget','runtime_sha256'];
        if(!is_array($c)||count($c)!==count($keys)||array_diff(array_keys($c),$keys)||($c['contract_version']??null)!=='2'||$c['intent_id']!==$id||$c['inquiry_id']!==$r['inquiry_id']||$c['tenant_id']!==$r['tenant_id']||$c['guid']!==$r['guid']||$c['request_body']!==$r['request']['body']||$c['request_body_sha256']!==$r['request']['body_sha256']||$c['environment']!=='Production_ClimatrolAirLLC')throw new InvalidArgumentException('prospective_card_binding_mismatch');
        \SiteEngine\AdsBridge\Connectors\FieldEdge\LiveLeadPayload::validate($c['request_body'],$r['guid']);
        if(($c['runtime_sha256']??null)!==hash_file('sha256',__FILE__))throw new RuntimeException('prospective_approved_runtime_changed');
        $expires=\SiteEngine\AdsBridge\Connectors\FieldEdge\Foundation::timestamp($c['expires_at']);
        if($expires!==$c['expires_at'])throw new InvalidArgumentException('prospective_card_expiry_invalid');
        if($c['budget']!==['oauth_token_posts'=>2,'lead_posts'=>1,'lead_gets'=>2,'automatic_retries'=>0])throw new InvalidArgumentException('prospective_approval_budget_invalid');
        if($c['effects']!==['creates_lead'=>true,'may_create_customer'=>true,'scheduling_authority'=>'FieldEdge','confirmed_appointment'=>false,'preserves_inquiry_preferences'=>true,'staff_notifications'=>'possible_provider_controlled','no_site_messages'=>true,'no_crm_lifecycle'=>true,'no_google_operations'=>true])throw new InvalidArgumentException('prospective_effects_confirmation_required');
        return $c;
    }
    public static function lead_review(string $id,string $bytes):array {
        return self::guarded(static function()use($id,$bytes){
            self::lead_card_value($id,$bytes);$old=self::get('leadcard',$id);$sha=hash('sha256',$bytes);
            if($old)return ['status'=>$old['card_json']===$bytes?'replayed':'conflict','card_sha256'=>$old['card_sha256'],'approval'=>'not_implied'];
            if(self::get('leadclaim',$id))throw new RuntimeException('prospective_already_claimed_get_guid_required');
            self::put('leadcard',$id,['card_json'=>$bytes,'card_sha256'=>$sha]);return ['status'=>'review_ready','card_sha256'=>$sha,'approval'=>'required','production_post_enabled'=>false];
        });
    }
    private static function lead_approved(string $id,string $sha):array {
        self::hash($sha);$v=self::get('leadcard',$id);$a=self::get('leadapproval',$id);
        if(!$v||!$a||$v['card_sha256']!==$sha||$a['card_sha256']!==$sha)throw new RuntimeException('prospective_specific_approval_required');
        $c=self::lead_card_value($id,$v['card_json']);if(strtotime($c['expires_at'])<=time())throw new RuntimeException('prospective_approval_expired');
        return $c;
    }
    /** Trusted operator action only AFTER explicit approval of this exact card SHA. */
    public static function lead_approve(string $id,string $sha,string $reference):array {
        return self::guarded(static function()use($id,$sha,$reference){
            self::hash($sha);self::id($reference);$v=self::get('leadcard',$id);
            if(!$v||$v['card_sha256']!==$sha)throw new RuntimeException('prospective_reviewed_card_required');
            $c=self::lead_card_value($id,$v['card_json']);if(strtotime($c['expires_at'])<=time()||strtotime($c['expires_at'])>time()+86400)throw new RuntimeException('prospective_approval_expiry_invalid');
            $old=self::get('leadapproval',$id);if($old)return ['status'=>$old['card_sha256']===$sha&&$old['approval_reference']===$reference?'replayed':'conflict','card_sha256'=>$old['card_sha256']];
            if(self::get('leadclaim',$id))throw new RuntimeException('prospective_already_claimed_get_guid_required');
            self::put('leadapproval',$id,['card_sha256'=>$sha,'approval_reference'=>$reference,'approver_id'=>get_current_user_id(),'approved_at'=>gmdate('Y-m-d\TH:i:s\Z')]);
            return ['status'=>'approved','card_sha256'=>$sha];
        });
    }
    public static function lead_claim_approved(string $id,string $sha,string $digest):array {
        return self::guarded(static function()use($id,$sha,$digest){
            $c=self::lead_approved($id,$sha);$r=self::lead_binding($id);
            if($r['synthetic'])throw new RuntimeException('prospective_real_reservation_required');
            if($digest!==$r['request']['body_sha256'])throw new RuntimeException('prospective_request_digest_mismatch');
            if(self::get('leadclaim',$id))throw new RuntimeException('prospective_already_claimed_get_guid_required');
            $claim=['permit'=>bin2hex(random_bytes(32)),'request_body_sha256'=>$digest,'dispatch_mode'=>'approved_single_post','card_sha256'=>$sha];self::put('leadclaim',$id,$claim);
            return ['status'=>'claimed','intent_id'=>$id,'reservation'=>$r,'expires_at'=>$c['expires_at']]+$claim;
        });
    }
    /** Initial GET and one explicitly requested recovery GET, each claimed once. */
    public static function lead_claim_get(string $id,string $sha,string $slot):array {
        return self::guarded(static function()use($id,$sha,$slot){
            $c=self::lead_approved($id,$sha);$r=self::lead_binding($id);$post=self::get('leadclaim',$id);
            if(!in_array($slot,['initial','recovery'],true)||!$post||($post['dispatch_mode']??null)!=='approved_single_post'||($post['card_sha256']??null)!==$sha)throw new RuntimeException('prospective_approved_post_claim_required');
            $key=hash('sha256',$id.'|'.$slot);if(self::get('leadgetclaim',$key))throw new RuntimeException('prospective_get_budget_already_claimed');
            $claim=['permit'=>bin2hex(random_bytes(32)),'card_sha256'=>$sha,'slot'=>$slot];self::put('leadgetclaim',$key,$claim);
            return ['status'=>'claimed','intent_id'=>$id,'reservation'=>$r,'expires_at'=>$c['expires_at'],'dispatch_mode'=>'approved_guid_get','post_permit'=>$post['permit']]+$claim;
        });
    }
    public static function lead_approved_status(string $id,string $sha,string $slot,string $permit,int $http,string $body,string $at):array {
        $gate=self::guarded(static function()use($id,$sha,$slot,$permit){
            self::hash($sha);$r=self::lead_binding($id);$claim=self::get('leadgetclaim',hash('sha256',$id.'|'.$slot));
            if(!$claim||$claim['card_sha256']!==$sha||!hash_equals($claim['permit'],self::hash($permit)))throw new RuntimeException('prospective_get_claim_mismatch');
            $read=self::lead_read_locked($id);$old=self::get('leadobs',hash('sha256',$id.'|approved-'.$slot));return ['status'=>'verified','tenant'=>$r['tenant_id'],'guid'=>$r['guid'],'previous'=>$old?$old['previous_sha256']:$read['latest_sha256']];
        });
        if(($gate['status']??null)!=='verified')return $gate;
        return self::lead_observe($id,'approved-'.$slot,$gate['previous'],$gate['tenant'],$gate['guid'],$http,$body,$at);
    }
}
