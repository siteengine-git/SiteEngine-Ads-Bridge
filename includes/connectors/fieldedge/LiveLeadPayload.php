<?php
/** Exact operator-supplied pilot request; validates without rewriting its bytes. */
namespace SiteEngine\AdsBridge\Connectors\FieldEdge;
require_once __DIR__.'/PilotPayload.php';
final class LiveLeadPayload {
    public static function validate(string $body,string $guid): void {
        if(strlen($body)>16384)throw new \InvalidArgumentException('prospective_request_too_large');
        TargetedRead::id($guid);$v=json_decode($body,true,16,JSON_THROW_ON_ERROR);
        if(!is_array($v)||($v['trackingId']??null)!==$guid)throw new \InvalidArgumentException('prospective_tracking_guid_required');
        unset($v['trackingId']);$copy=json_decode($body,false,16,JSON_THROW_ON_ERROR);unset($copy->trackingId);
        // Documentation describes title-case methods; its example uses lowercase.
        // Accept either literal spelling, but never alter the actual retained body.
        $method=$v['preferredContactMethod']??null;
        if($method!==null&&!in_array($method,['Phone','Email','SMS','phone','email','sms'],true))throw new \InvalidArgumentException('prospective_contact_method_invalid');
        if($method!==null)$copy->preferredContactMethod=['phone'=>'Phone','email'=>'Email','sms'=>'SMS'][$method]??$method;
        $report=PilotPayload::inspect(json_encode($copy,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES));
        if(!$report['valid'])throw new \InvalidArgumentException('prospective_live_payload_invalid');
        if(($v['isExistingCustomer']??null)!==false||isset($v['customer']['existingCustomerId']))throw new \InvalidArgumentException('prospective_explicit_new_test_subject_required');
        if(empty($v['customer']['phone'])&&empty($v['customer']['email']))throw new \InvalidArgumentException('prospective_test_contact_required');
        if(isset($v['customer']['phone'])&&!preg_match('/^[+0-9() .-]{7,32}$/D',$v['customer']['phone']))throw new \InvalidArgumentException('prospective_phone_invalid');
        $a=$v['appointment']??[];
        if(isset($a['preferredDate'])){$d=\DateTimeImmutable::createFromFormat('!Y-m-d',$a['preferredDate']);if(!$d||$d->format('Y-m-d')!==$a['preferredDate'])throw new \InvalidArgumentException('prospective_preference_date_invalid');}
        if(isset($a['preferredTime'])&&!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D',$a['preferredTime']))throw new \InvalidArgumentException('prospective_preference_time_invalid');
        if(isset($a['timezone'])&&!in_array($a['timezone'],\DateTimeZone::listIdentifiers(),true))throw new \InvalidArgumentException('prospective_timezone_invalid');
        // appointment and serviceRequest are required OBJECTS. Their properties
        // (including location and issueDescription) are optional in the FE schema.
    }
    private static function text($v,int $limit):void {
        if(!is_string($v)||trim($v)===''||strlen($v)>$limit||preg_match('/[\x00-\x1f\x7f]/',$v))throw new \InvalidArgumentException('prospective_explicit_test_field_required');
    }
}
