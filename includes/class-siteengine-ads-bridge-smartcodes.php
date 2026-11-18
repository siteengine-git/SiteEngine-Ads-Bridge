<?php
defined('ABSPATH') || exit;
/** Compatibility for existing imported content, independent of import administration. */
final class SiteEngine_Ads_Bridge_Smartcodes {
    public static function init():void {
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
        if($key!=='service_label')return $literal;$r=\SiteEngine\AdsBridge\Import\Store::read(\SiteEngine\AdsBridge\Import\Store::REGISTRY,[]);return esc_html($r['service_label']??$default);
    }
}
