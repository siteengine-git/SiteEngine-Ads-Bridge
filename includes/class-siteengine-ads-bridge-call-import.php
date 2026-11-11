<?php
/** Google calls-upload structural preview only. No authority, sink, schema or transport. */
if (!defined('ABSPATH')) exit;
final class SiteEngine_Ads_Bridge_Call_Import {
    const CONTRACT = 'google_calls_upload_preview_v1';
    private const KEYS = ['caller_id','call_start_date_time','conversion_name','conversion_date_time','conversion_value','currency_code'];
    private const HEADERS = ["Caller's Phone Number",'Call Start Time','Conversion Name','Conversion Time','Conversion Value','Conversion Currency','Ad User Data','Ad Personalization'];
    public static function requirements(): array {
        return ['contract'=>self::CONTRACT,'delivery_enabled'=>false,'existing_21_column_sheet_compatible'=>false,
            'required_source_facts'=>self::KEYS,'required_account_evidence'=>['google_forwarding_and_tracking_at_original_call','enabled_calls_upload_action_and_exact_name'],
            'unavailable_supported_cb_fields'=>['caller_id','provider_call_start_with_offset'],
            'authority'=>'call_bound_original_and_current_consent_contract_unavailable'];
    }
    private static function instant($value): DateTimeImmutable {
        if(!is_string($value)||!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}[+-](?:(?:0\d|1[0-3]):[0-5]\d|14:00)$/D',$value))throw new InvalidArgumentException('call_import_offset_required');
        $d=DateTimeImmutable::createFromFormat('!Y-m-d H:i:sP',$value);$errors=DateTimeImmutable::getLastErrors();
        if(!$d||($errors!==false&&($errors['warning_count']||$errors['error_count']))||$d->format('Y-m-d H:i:sP')!==$value)throw new InvalidArgumentException('call_import_instant_invalid');
        return $d;
    }
    /** Even a positive structural fixture cannot authorize advertising or a production export. */
    public static function preview(array $facts): array {
        $base=['contract'=>self::CONTRACT,'status'=>'held','delivery_enabled'=>false,'export_eligible'=>false];
        try {
            if(array_diff(array_keys($facts),self::KEYS)||array_diff(self::KEYS,array_keys($facts)))throw new InvalidArgumentException('call_import_exact_facts_required');
            if(!is_string($facts['caller_id'])||!preg_match('/^\+[1-9][0-9]{7,14}$/D',$facts['caller_id']))throw new InvalidArgumentException('call_import_caller_e164_required');
            $start=self::instant($facts['call_start_date_time']);$conversion=self::instant($facts['conversion_date_time']);
            if($conversion<=$start)throw new InvalidArgumentException('call_import_conversion_must_follow_call');
            if(!is_string($facts['conversion_name'])||trim($facts['conversion_name'])!==$facts['conversion_name']||$facts['conversion_name']===''||strlen($facts['conversion_name'])>200||preg_match('/[\x00-\x1f\x7f]/',$facts['conversion_name']))throw new InvalidArgumentException('call_import_exact_action_required');
            if(!is_string($facts['conversion_value'])||!preg_match('/^(0|[1-9][0-9]{0,11})(\.[0-9]{1,2})?$/D',$facts['conversion_value']))throw new InvalidArgumentException('call_import_value_invalid');
            if(!is_string($facts['currency_code'])||!preg_match('/^[A-Z]{3}$/D',$facts['currency_code']))throw new InvalidArgumentException('call_import_currency_invalid');
            // Exact official CSV has two additional Consent columns. They remain
            // blank: no supported call-bound authority exists, and arbitrary
            // caller-supplied flags cannot manufacture it.
            $values=array_merge(array_map(static fn($k)=>$facts[$k],self::KEYS),['','']);
            $values[1]=$start->format('Y-m-d H:i:sO');$values[3]=$conversion->format('Y-m-d H:i:sO');
            return $base+['reason_code'=>'call_consent_authority_contract_unavailable','structural_mapping'=>'PASS',
                'structural_preview'=>['headers'=>self::HEADERS,'values'=>$values],
                'missing_evidence'=>['authoritative_call_consent','google_account_call_tracking_and_action_verification'],
                'limits'=>'Caller/start and business outcome must be independently established; capture/lease times, duration, UTM or contact matching cannot substitute.'];
        }catch(InvalidArgumentException $e){return $base+['reason_code'=>$e->getMessage(),'structural_mapping'=>'FAIL'];}
    }
}
