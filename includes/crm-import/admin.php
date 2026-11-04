<?php
namespace SiteEngine\AdsBridge\Import;
use RuntimeException;
use Throwable;

final class Admin {
    public static function init():void {
        add_action('wp_ajax_seab_crm_import',[self::class,'ajax']);
        add_action('init',[Preview::class,'register'],30);
        // Only supply missing smartcode groups; coexist with the producer on Alma.
        if(!has_filter('fluent_crm/smartcode_group_callback_base'))add_filter('fluent_crm/smartcode_group_callback_base',[self::class,'baseCode'],10,3);
        if(!has_filter('fluent_crm/smartcode_group_callback_secrm'))add_filter('fluent_crm/smartcode_group_callback_secrm',[self::class,'serviceCode'],10,3);
    }
    public static function baseCode($literal,$key,$default='') {
        if(!in_array($key,['business_name','phone','privacy_policy_url','street_address','city','state','zip','timezone','business_type','services_offered'],true))return $literal;
        if($key==='services_offered'){$v=function_exists('siteengine_get_services_offered')?siteengine_get_services_offered():[];return esc_html(is_array($v)?implode(', ',$v):$default);}
        return function_exists('siteengine_has')&&siteengine_has($key)?esc_html((string)siteengine_get($key)):$default;
    }
    public static function serviceCode($literal,$key,$default='') {
        if($key!=='service_label')return $literal;$r=Store::read(Store::REGISTRY,[]);return esc_html($r['service_label']??$default);
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
            if($action==='upload'){
                $f=$_FILES['configuration']??[];if(($f['error']??1)!==UPLOAD_ERR_OK||!is_uploaded_file($f['tmp_name']??''))throw new RuntimeException('Choose a configuration ZIP to upload.');$result=Engine::upload($f['tmp_name']);
            }elseif($action==='preview'){
                $input=json_decode(wp_unslash($_POST['mappings']??'{}'),true,16,JSON_THROW_ON_ERROR);$choices=json_decode(wp_unslash($_POST['choices']??'{}'),true,16,JSON_THROW_ON_ERROR);
                if(!is_array($input)||!is_array($choices))throw new RuntimeException('Destination mappings are required.');$result=Engine::preview($input,$choices);
            }elseif($action==='begin')$result=Engine::begin((string)($_POST['plan_hash']??''));
            elseif($action==='continue')$result=Engine::run();
            else throw new RuntimeException('Unknown import action.');
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
    public static function render():void {
        if(!current_user_can('manage_options'))return;
        echo '<h2>Import CRM Configuration</h2><p>Upload the portable ZIP from the CRM builder. Review destination mappings and changes, then import editable, inactive FluentCRM configuration. No contacts, history, credentials, messages or enrollment are imported.</p>';
        $recovery=Recovery::inspect();echo '<div id="seab-import-recovery">';Recovery::render($recovery);echo '</div>';
        try{$state=Engine::view();$state['recovery']=$recovery;$choices=self::choices();}
        catch(Throwable $e){echo '<div class="notice notice-error inline"><p>'.esc_html($e->getMessage()).'</p></div>';return;}
        echo '<style>#seab-crm-import [hidden]{display:none!important}</style><div id="seab-crm-import" data-nonce="'.esc_attr(wp_create_nonce('seab_crm_import')).'"><p id="seab-import-message" role="status"></p>';
        echo '<form id="seab-import-upload"><label>Configuration ZIP <input type="file" name="configuration" accept=".zip" required></label> <button class="button">Upload ZIP</button></form>';
        echo '<form id="seab-import-mappings" hidden><h3>Destination mappings</h3><p><label>Master form <select name="form" required></select></label></p><p><label>Service input on this form <select name="form_service" required></select></label></p>';
        echo '<p><label>CRM service field <select name="service_field"></select></label></p><p><label>CRM outcome-reason field <select name="outcome_field"></select></label></p><p><label>Service label <input name="service_label" list="seab-import-services" required maxlength="200"></label><datalist id="seab-import-services">';
        foreach($choices['services'] as $service)echo '<option value="'.esc_attr($service).'">';
        echo '</datalist></p><p>Business facts and template placeholders use SiteEngine Base on this destination. Existing business data is preserved.</p><button class="button">Review changes</button></form>';
        echo '<div id="seab-import-review"></div><p><button type="button" class="button button-primary" id="seab-import-begin" hidden>Import configuration</button> <button type="button" class="button" id="seab-import-resume" hidden>Resume saved import</button></p><div id="seab-import-summary"></div><details><summary>Advanced details</summary><pre id="seab-import-details"></pre></details></div>';
        echo '<script type="application/json" id="seab-import-initial">'.wp_json_encode(['state'=>$state,'choices'=>$choices],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).'</script>';
        echo '<script src="'.esc_url(SEAB_PLUGIN_URL.'includes/crm-import/import.js?ver='.SEAB_VERSION).'"></script>';
    }
}
