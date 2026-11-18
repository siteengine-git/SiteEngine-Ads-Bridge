<?php
/** Gate A isolated transport. Native OAuth/HTTP execution remains unavailable. */
namespace SiteEngine\AdsBridge\Connectors\FieldEdge;
require_once __DIR__.'/LeadCreateDryRun.php';
require_once __DIR__.'/LeadResponse.php';
final class LeadPilotDispatch {
    public static function envelope(array $dry,array $context,string $guid): string {
        LeadCreateDryRun::decide($dry);
        $source=$context['source'];$original=$source;unset($original['transaction_id']);
        if($original!==$dry['local_inquiry']['source'])throw new \InvalidArgumentException('original_form_binding_mismatch');
        $body=json_encode(['trackingId'=>$guid]+$dry['payload'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
        return json_encode(['contract_version'=>'2','provider'=>'fieldedge','tenant_id'=>$context['tenant_id'],'inquiry_id'=>$context['inquiry_id'],'source'=>$source,'guid'=>$guid,'request'=>['method'=>'POST','endpoint'=>LeadResponse::URL,'body'=>$body,'body_sha256'=>hash('sha256',$body)],'synthetic'=>true],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
    }
    /** Only an explicit injected transport; never retries, loads secrets or opens HTTP. */
    public static function send(string $id,string $digest,?callable $injected=null): array {
        if($injected===null)return ['status'=>'held','reason_code'=>'isolated_transport_required'];
        $read=\SiteEngine_Ads_Bridge_Prospective::lead_read($id);
        if(($read['reservation']['synthetic']??null)!==true)return ['status'=>'held','reason_code'=>'real_post_approval_required'];
        $claim=\SiteEngine_Ads_Bridge_Prospective::lead_claim($id,$digest);
        if(($claim['status']??null)!=='claimed')return $claim;
        global $wpdb;
        $name='seab_c_'.substr(\SiteEngine_Ads_Bridge_Coordination::capability()['destination'],0,48);
        if($wpdb->get_var('SELECT @@in_transaction')!=='0'||$wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)',$name))!==null) return ['status'=>'held','reason_code'=>'database_boundary_unconfirmed'];
        if(!$wpdb->close())return ['status'=>'held','reason_code'=>'database_close_unconfirmed'];
        $request=$read['reservation']['request'];$response=null;
        try{$response=$injected('POST',LeadResponse::URL,$request['body'],$read['reservation']['tenant_id']);}catch(\Throwable $e){ /* Claimed intent remains uncertain; no second POST. */ }
        if(!$wpdb->db_connect(false))return ['status'=>'held','reason_code'=>'database_reconnect_unconfirmed'];
        $classified=LeadResponse::classify(is_array($response)?$response:null);
        $http=$classified['http_status'];$lead=$classified['classification']==='success'?$classified['lead_id']:null;
        $bytes=json_encode(['http_status'=>$http,'response_sha256'=>is_string($response['body']??null)?hash('sha256',$response['body']):hash('sha256','no-provider-reply'),'lead_id'=>$lead,'request_body_sha256'=>$digest],JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
        return \SiteEngine_Ads_Bridge_Prospective::lead_result($id,$claim['permit'],$bytes);
    }
}
