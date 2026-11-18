<?php
namespace SiteEngine\AdsBridge\Import;
use RuntimeException;
/** Portable, inactive supported FluentCRM configuration; no operational state. */
final class Format {
    public static function revision(array $objects,array $dependencies,array $bindings):string{return 'cfg-'.JSON::hash([$objects,$dependencies,$bindings]);}
    public static function bindings(?array $definitions=null):array{
        $definitions??=json_decode(file_get_contents(__DIR__.'/dependency-definitions.json'),true,64,JSON_THROW_ON_ERROR);
        $out=['form.primary'=>['kind'=>'form','resolution'=>'destination_required']];
        foreach($definitions as $key=>$d)$out[$key]=['kind'=>$d['kind'],'key'=>$key];return $out;
    }
    public static function safe($v,int $depth=0):void{
        if($depth>20)throw new RuntimeException('configuration_depth_bound');
        if(is_array($v)){foreach($v as $k=>$x){if(is_string($k)&&preg_match('/(^|_)(password|credential|secret|token|contact_id|subscriber_id|inquiry|receipt|ledger|queue|access_key|api_key)(s|_|$)/i',$k))throw new RuntimeException('private_or_operational_configuration');self::safe($x,$depth+1);}}
        elseif(!is_null($v)&&!is_bool($v)&&!is_int($v)&&!is_string($v))throw new RuntimeException('configuration_value_unsupported');
        elseif(is_string($v)&&(strlen($v)>100000||str_contains($v,"\0")))throw new RuntimeException('configuration_text_bound');
    }
    public static function conditions(array $conditions,array $dependencies):void{
        if(!array_is_list($conditions)||count($conditions)>32)throw new RuntimeException('unsupported_conditions');
        foreach($conditions as $group){if(!is_array($group)||!array_is_list($group)||count($group)>32)throw new RuntimeException('unsupported_conditions');foreach($group as $c){
            if(!is_array($c)||array_diff(array_keys($c),['source','operator','value'])||count($c)!==3||count($c['source']??[])!==2||!in_array($c['operator']??null,['=','!=','in','not_in','contains','not_contains','>','<','>=','<=','empty','not_empty'],true))throw new RuntimeException('unsupported_condition');
            [$provider,$field]=$c['source'];
            if($provider==='subscriber'&&in_array($field,['tags','lists'],true)){
                foreach((array)$c['value'] as $key)if(!is_string($key)||($dependencies[$key]['kind']??null)!==($field==='tags'?'tag':'list'))throw new RuntimeException('condition_dependency_unmapped');
            }elseif($provider==='custom_fields'){
                if(($dependencies[$field]['kind']??null)!=='field')throw new RuntimeException('condition_field_unmapped');
            }elseif($provider!=='subscriber'||!in_array($field,['status','contact_type','country'],true))throw new RuntimeException('unsupported_condition_source');
        }}
    }
    public static function conditionMap(array $conditions,array $registry,bool $toNative):array{
        foreach($conditions as &$group)foreach($group as &$c){[$provider,$field]=$c['source'];
            $find=static function($value,string $kind)use($registry,$toNative){
                if($toNative){$m=$registry['objects'][$value]??null;if(!$m||$m['kind']!==$kind)throw new RuntimeException('condition_dependency_unmapped');return $kind==='field'?(string)$m['id']:(int)$m['id'];}
                foreach($registry['objects'] as $key=>$m)if($m['kind']===$kind&&(string)$m['id']===(string)$value)return $key;
                throw new RuntimeException('condition_dependency_unmapped');};
            if($provider==='subscriber'&&in_array($field,['lists','tags'],true))$c['value']=array_map(static fn($v)=>$find($v,$field==='tags'?'tag':'list'),(array)$c['value']);
            elseif($provider==='custom_fields')$c['source'][1]=$find($field,'field');
        }unset($group,$c);return $conditions;
    }
    public static function validate(array $v,array $config,array $known):void{
        self::safe($v);
        if(array_diff(array_keys($v),['format','version','configuration_revision','execution_enabled','objects','dependencies','dependency_bindings','views','required_bindings','omitted'])||
            ($v['format']??null)!=='siteengine.leadgen.native-export/2'||($v['version']??null)!=='2'||($v['execution_enabled']??null)!==false||!is_array($v['objects']??null)||!$v['objects']||count($v['objects'])>256)throw new RuntimeException('configuration_v2_required');
        Dependencies::validate($v['dependencies']??[],$config);
        if(($v['configuration_revision']??null)!==self::revision($v['objects'],$v['dependencies'],$v['dependency_bindings']??[])||JSON::hash($v['dependency_bindings'])!==JSON::hash(self::bindings($v['dependencies'])))throw new RuntimeException('configuration_revision_or_bindings_changed');
        $journeys=Contract::catalog()['journeys'];$ordering=[];
        foreach($v['objects'] as $key=>$d){
            if(!preg_match('/^[a-z][a-z0-9_.-]{1,110}$/D',$key)||array_diff(array_keys($d),['kind','body'])||!isset($d['body']['title'])||!is_string($d['body']['title']))throw new RuntimeException('portable_object_shape');
            $kind=$d['kind'];$b=$d['body'];$example=null;foreach($known as $k=>$row)if($row['kind']===$kind){$example=$row['body'];break;}
            if(!$example||array_diff(array_keys($b),array_keys($example))||array_diff(array_keys($example),array_keys($b))||($b['status']??null)!=='draft')throw new RuntimeException('unsupported_object_type_or_shape');
            if(in_array($kind,['email_sequence','email_sequence_mail'],true))Sequences::validate($kind,$b,$v['objects']);
            if($kind==='template'&&($b['design']!=='raw_html'||$b['edit_type']!=='html'||(!is_array($b['template_config'])&&!is_string($b['template_config']))||(!is_array($b['footer_settings'])&&!is_string($b['footer_settings']))))throw new RuntimeException('unsupported_raw_html_template');
            if($kind==='automation'){
                $journey=substr($key,strlen('automation.'));if(!str_starts_with($key,'automation.')||!isset($journeys[$journey])||$b['trigger']!=='siteengine_crm_inquiry_preview'||
                    array_diff(array_keys($b['settings']),['entry_event','__force_run_actions','form_ref','run_multiple'])||($b['settings']['__force_run_actions']??null)!=='no'||($b['settings']['form_ref']??null)!=='form.primary'||($b['settings']['entry_event']??null)!==$journeys[$journey]['entry']||!in_array($b['settings']['run_multiple']??'no',['yes','no'],true))throw new RuntimeException('inactive_journey_settings_required');
                self::conditions($b['conditions'],$v['dependencies']);
            }elseif(in_array($kind,['template','campaign'],true)){
                if(!isset($known[$key])||$known[$key]['kind']!==$kind)throw new RuntimeException('unsupported_content_family');
            }
            if(in_array($kind,['campaign','sequence_email'],true)){
                if($b['scheduled']!==null||$b['design']!=='raw_html'||array_diff(array_keys($b['settings']),array_keys($example['settings'])))throw new RuntimeException('inactive_campaign_required');
                foreach(['subscribers','excludedSubscribers'] as $a)if(($b['settings'][$a]??null)!==[])throw new RuntimeException('audience_not_portable');
                foreach(['open_tracker','click_tracker'] as $a)if(($b['settings'][$a]??false)!==false)throw new RuntimeException('tracking_policy_unresolved');
                if(JSON::hash($b['settings']['mailer_settings']??[])!==JSON::hash($example['settings']['mailer_settings']??[]))throw new RuntimeException('custom_mailer_unsupported');
            }
            if(isset($b['funnel_ref'])&&($v['objects'][$b['funnel_ref']]['kind']??null)!=='automation')throw new RuntimeException('funnel_reference_unmapped');
            if($kind==='step'){
                if(!in_array($b['action'],['send_custom_email','fluentcrm_wait_times'],true)||$b['type']!=='action'||!is_int($b['sequence'])||$b['sequence']<1||isset($ordering[$b['funnel_ref']][$b['sequence']]))throw new RuntimeException('step_order_or_type_unsupported');
                $ordering[$b['funnel_ref']][$b['sequence']]=true;
                foreach(['delay','c_delay'] as $time)if(!is_int($b[$time])||$b[$time]<0||$b[$time]>31536000)throw new RuntimeException('step_timing_invalid');
                if($b['parent_ref']!==null&&(($v['objects'][$b['parent_ref']]['kind']??null)!=='step'||$v['objects'][$b['parent_ref']]['body']['funnel_ref']!==$b['funnel_ref']||$v['objects'][$b['parent_ref']]['body']['sequence']>=$b['sequence']))throw new RuntimeException('step_parent_invalid');
                if($b['action']==='send_custom_email'){
                    $s=$b['settings'];if(array_diff(array_keys($s),['campaign_ref','campaign','send_email_to_type','send_email_custom','is_scheduled','scheduled_at','skip_if_overdue','mailer_settings'])||($v['objects'][$s['campaign_ref']??'']['kind']??null)!=='sequence_email'||($s['campaign']??null)!==[]||($s['is_scheduled']??null)!=='no'||($s['scheduled_at']??null)!==''||($s['mailer_settings']??null)!==[]||!in_array($s['send_email_to_type']??null,['contact','custom'],true)||(($s['send_email_to_type']==='custom')&&($s['send_email_custom']??'')!==''&&!filter_var($s['send_email_custom'],FILTER_VALIDATE_EMAIL)))throw new RuntimeException('unsupported_email_step_settings');
                }else{ $s=$b['settings'];if(array_diff(array_keys($s),['wait_type','wait_time_amount','wait_time_unit'])||($s['wait_type']??null)!=='unit_wait'||!in_array($s['wait_time_unit']??null,['minutes','hours','days'],true)||!ctype_digit((string)($s['wait_time_amount']??''))||(int)$s['wait_time_amount']<1||(int)$s['wait_time_amount']>525600)throw new RuntimeException('unsupported_wait_settings'); }
                self::conditions($b['conditions'],$v['dependencies']);
            }
            if($kind==='email_sequence_mail'){if(isset($ordering[$b['sequence_ref']][$b['delay']]))throw new RuntimeException('sequence_delay_order_ambiguous');$ordering[$b['sequence_ref']][$b['delay']]=true;}
        }
        if(($v['omitted']??null)!==['native_ids','contacts','inquiries','consent','ledgers','queues','credentials','registry']||($v['required_bindings']??null)!==['form.primary','field.service_type','field.outcome_reason']||JSON::hash($v['views']??null)!==JSON::hash(Contract::catalog()['views']))throw new RuntimeException('configuration_only_metadata_required');
    }
}
