<?php
namespace SiteEngine\AdsBridge\Import;
use RuntimeException;
/** Producer dependency validation; no customer values or source runtime state. */
final class Dependencies {
    public static function defaults(array $config): array {
        $d=json_decode(file_get_contents(__DIR__.'/dependency-definitions.json'),true,64,JSON_THROW_ON_ERROR);
        $d['field.service_type']['body']['slug']=$config['bindings']['field.service_type'];
        $d['field.outcome_reason']['body']['slug']=$config['bindings']['field.outcome_reason'];return $d;
    }
    public static function validate(array $defs,array $config): void {
        $base=self::defaults($config);
        if(array_diff(array_keys($base),array_keys($defs)) || count($defs)>128)throw new RuntimeException('required_dependency_missing');
        foreach($defs as $key=>$d){
            if(array_diff(array_keys($d),['kind','body'])||!in_array($d['kind']??null,['field','list','tag'],true)||!str_starts_with($key,$d['kind'].'.'))throw new RuntimeException('unsupported_dependency_kind');
            $b=$d['body'];$allowed=match($d['kind']){'field'=>['slug','type','field_key','label','options','group'],'list'=>['slug','title','description','is_public'],'tag'=>['slug','title','description']};
            if(array_diff(array_keys($b),$allowed)||!preg_match('/^[a-z][a-z0-9_-]{0,100}$/D',$b['slug']??''))throw new RuntimeException('dependency_shape_invalid');
            if($d['kind']==='field'){
                if(!str_starts_with($b['slug'],'se_crm_')&&!in_array($key,['field.service_type','field.outcome_reason'],true))throw new RuntimeException('external_field_definition_refused');
                if(!in_array($b['type']??null,['text','textarea','number','date','date_time','select-one'],true)||!is_string($b['label']??null))throw new RuntimeException('incompatible_field_type');
                if(($b['field_key']??null)!==(($b['type']==='select-one')?'single-select':$b['type']))throw new RuntimeException('incompatible_field_key');
                if($b['type']==='select-one'&&(!is_array($b['options']??null)||!array_is_list($b['options'])||!$b['options']))throw new RuntimeException('field_options_required');
                if($key==='field.service_type'&&!in_array($b['type'],['text','select-one'],true))throw new RuntimeException('incompatible_service_field_type');
                if(isset($base[$key])&&!in_array($key,['field.service_type','field.outcome_reason'],true)&&$b['type']!==$base[$key]['body']['type'])throw new RuntimeException('incompatible_runtime_field_type');
                if($key==='field.outcome_reason'&&($b['type']!=='select-one'||$b['options']!==$base[$key]['body']['options']))throw new RuntimeException('shared_outcome_definition_conflict');
            }else if(!is_string($b['title']??null)||($d['kind']==='list'&&!in_array($b['is_public']??null,[0,1],true)))throw new RuntimeException('term_definition_invalid');
        }
    }
}
