<?php
namespace SiteEngine\AdsBridge\Import;
use RuntimeException;
use Throwable;

final class Admin {
    public static function init():void {
        add_action('wp_ajax_seab_crm_import',[self::class,'ajax']);
        add_action('init',[Preview::class,'register'],30);
    }
    /** Request-local containment only; no permanent MU or unrelated traffic control. */
    public static function containment():void {
        add_filter('pre_wp_mail','__return_false',PHP_INT_MAX);
        add_filter('pre_http_request',static fn()=>new \WP_Error('seab_import_delivery_blocked','External HTTP is disabled during this configuration import request.'),PHP_INT_MAX);
        add_filter('pre_schedule_event','__return_false',PHP_INT_MAX);
        add_filter('action_scheduler_allow_async_request_runner','__return_false',PHP_INT_MAX);
        add_filter('action_scheduler_disable_async_request','__return_true',PHP_INT_MAX);
    }
    public static function ajax():void {
        if(!current_user_can('manage_options')||!current_user_can('unfiltered_html'))wp_send_json_error(['message'=>'Administrator permission required.'],403);
        check_ajax_referer('seab_crm_import');self::containment();
        @set_time_limit(20);
        try{
            $action=sanitize_key(wp_unslash($_POST['operation']??''));
            if($action!=='continue')throw new RuntimeException('Normal CRM ZIP import has been retired. Only exact saved-operation recovery is supported.');
            $j=Store::job();if(!$j||!in_array($j['status']??'', ['running','uncertain'],true))throw new RuntimeException('No unfinished native operation to resume. Completed journals remain unchanged.');
            $result=Engine::run();
            $result['recovery']=Recovery::inspect();wp_send_json_success($result);
        }catch(Throwable $e){
            $view=null;try{$view=Engine::view();}catch(Throwable $ignored){}
            wp_send_json_error(['message'=>$e->getMessage(),'state'=>$view],409);
        }
    }
    public static function choices():array {
        global $wpdb;$forms=$wpdb->get_results("SELECT id,title,form_fields FROM `{$wpdb->prefix}fluentform_forms` ORDER BY id LIMIT 101",ARRAY_A);
        if($wpdb->last_error||!is_array($forms)||count($forms)>100)throw new RuntimeException('Destination form list is unavailable or exceeds 100.');
        $out=[];foreach($forms as $f){$selectors=[];$walk=static function($v)use(&$walk,&$selectors){if(!is_array($v))return;if(isset($v['attributes']['name'])&&is_string($v['attributes']['name']))$selectors[$v['attributes']['name']]=$v['settings']['label']??$v['attributes']['name'];foreach($v as $x)if(is_array($x))$walk($x);};$walk(json_decode($f['form_fields'],true));$out[]=['id'=>(int)$f['id'],'title'=>$f['title'],'selectors'=>$selectors];}
        $fields=class_exists('FluentCrm\\App\\Models\\CustomContactField')?(new \FluentCrm\App\Models\CustomContactField())->getGlobalFields()['fields']:[];
        $services=function_exists('siteengine_get_services_offered')?siteengine_get_services_offered():[];
        return ['forms'=>$out,'fields'=>array_map(fn($f)=>array_intersect_key($f,array_flip(['slug','label','type'])),$fields),'services'=>is_array($services)?$services:[]];
    }
}
