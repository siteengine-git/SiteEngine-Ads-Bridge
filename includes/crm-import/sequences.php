<?php
namespace SiteEngine\AdsBridge\Import;
use RuntimeException;
/** Adapted only the producer sequence validator, not its content generator. */
final class Sequences {
    public static function validate(string $kind,array $b,array $objects):void {
        if($b['design']!=='raw_html'||$b['status']!=='draft'||!is_array($b['settings']))throw new RuntimeException('inactive_sequence_required');
        $s=$b['settings'];$mailer=['from_name'=>'','from_email'=>'','reply_to_name'=>'','reply_to_email'=>'','is_custom'=>'no'];
        if(!is_array($s['mailer_settings']??null)||array_diff(array_keys($s['mailer_settings']),array_keys($mailer)))throw new RuntimeException('sequence_custom_mailer_unsupported');
        foreach($mailer as $key=>$value)if(($s['mailer_settings'][$key]??null)!==$value)throw new RuntimeException('sequence_custom_mailer_unsupported');
        if($kind==='email_sequence'){if(array_keys($s)!==['mailer_settings'])throw new RuntimeException('sequence_settings_unsupported');return;}
        if(($objects[$b['sequence_ref']]['kind']??null)!=='email_sequence'||array_diff(array_keys($s),['action_triggers','timings','template_config','mailer_settings','open_tracker','click_tracker'])||($s['action_triggers']??null)!==[])throw new RuntimeException('sequence_relationship_unsupported');
        foreach(['open_tracker','click_tracker'] as $key)if(($s[$key]??false)!==false)throw new RuntimeException('tracking_policy_unresolved');
        $t=$s['timings']??[];
        if(array_diff(array_keys($t),['delay_unit','delay','is_anytime','sending_time','selected_days_only','allowed_days'])||!in_array($t['delay_unit']??null,['minutes','hours','days','weeks'],true)||!ctype_digit((string)($t['delay']??'')))throw new RuntimeException('sequence_timing_unsupported');
        $seconds=(int)$t['delay']*['minutes'=>60,'hours'=>3600,'days'=>86400,'weeks'=>604800][$t['delay_unit']];
        if(!is_int($b['delay'])||$b['delay']!==$seconds||$seconds>31536000||!in_array($t['is_anytime']??null,['yes','no'],true)||!in_array($t['selected_days_only']??'no',['yes','no'],true))throw new RuntimeException('sequence_delay_invalid');
        if(!is_array($t['allowed_days']??[])||array_diff($t['allowed_days']??[],['Mon','Tue','Wed','Thu','Fri','Sat','Sun']))throw new RuntimeException('sequence_days_invalid');
        if(($t['selected_days_only']??'no')==='yes'&&empty($t['allowed_days']))throw new RuntimeException('sequence_days_required');
        $times=$t['sending_time']??[];if(!is_array($times)||count($times)>2)throw new RuntimeException('sequence_times_invalid');
        foreach($times as $time)if($time!==''&&(!is_string($time)||!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D',$time)))throw new RuntimeException('sequence_times_invalid');
        if($t['is_anytime']==='no'&&(!count($times)||in_array('',$times,true)))throw new RuntimeException('sequence_times_required');
        if(!is_array($s['template_config']??null))throw new RuntimeException('sequence_template_config_unsupported');
        foreach(['title','subject','preheader','html'] as $key)if(!is_string($b[$key])||($key!=='preheader'&&trim($b[$key])===''))throw new RuntimeException('sequence_content_required');
    }
}
