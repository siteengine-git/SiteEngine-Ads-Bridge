<?php
/** Fixed phases/codes only: no exception text, token, headers or customer body. */
namespace SiteEngine\AdsBridge\Connectors\FieldEdge;
final class LeadDiagnostics {
    private string $phase='input';
    private array $events=[];
    private array $attempts=['oauth'=>0,'post'=>0,'get'=>0];
    private ?array $creation=null;
    private ?\Closure $checkpoint;
    public function __construct(?callable $checkpoint=null){$this->checkpoint=$checkpoint===null?null:\Closure::fromCallable($checkpoint);}
    public function enter(string $phase):void {
        if(!in_array($phase,['input','binding','credentials','oauth','post','post_classification','get','complete','serialization'],true))throw new \LogicException('invalid_diagnostic_phase');
        $this->phase=$phase;$this->event(['phase'=>$phase,'event'=>'enter']);
    }
    public function attempt(string $phase):void {
        if(!array_key_exists($phase,$this->attempts))throw new \LogicException('invalid_diagnostic_attempt');
        $this->attempts[$phase]++;$this->event(['phase'=>$phase,'event'=>'transport_invoked']);
    }
    public function reply(string $phase,array $response):void {
        $http=$response['status']??null;$v=['phase'=>$phase,'event'=>'reply','http_status'=>is_int($http)&&$http>=100&&$http<=599?$http:null];
        // OAuth response contains a bearer token: do not retain even its digest.
        if($phase!=='oauth'&&is_string($response['body']??null)){$v['response_bytes']=strlen($response['body']);$v['response_sha256']=hash('sha256',$response['body']);}
        $this->event($v);
    }
    public function failure(\Throwable $error):string {
        $known=['worker_cli_required','worker_input_bound','worker_environment_refused','approved_binding_required','credential_input_invalid','oauth_http_rejected','oauth_response_invalid','token_expired_or_too_short_for_read','https_transport_unavailable','https_transport_failed','response_too_large','response_not_json','response_structure_invalid','approval_or_token_expired','prospective_request_too_large','prospective_tracking_guid_required','prospective_live_payload_invalid','prospective_explicit_new_test_subject_required','prospective_test_contact_required','prospective_phone_invalid','prospective_contact_method_invalid','prospective_preference_date_invalid','prospective_preference_time_invalid','prospective_timezone_invalid'];
        $known[]='worker_checkpoint_failed';
        $reason=in_array($error->getMessage(),$known,true)?$error->getMessage():($error instanceof \JsonException?'json_invalid':($error instanceof \TypeError?'type_mismatch':'unexpected_exception'));
        $type=$error instanceof \JsonException?'JsonException':($error instanceof \TypeError?'TypeError':($error instanceof \RuntimeException?'RuntimeException':($error instanceof \InvalidArgumentException?'InvalidArgumentException':'Throwable')));
        $this->event(['phase'=>$this->phase,'event'=>'failure','reason_code'=>$reason,'error_class'=>$type]);return $reason;
    }
    public function creation(array $value):void { $this->creation=$value;if($this->checkpoint!==null)($this->checkpoint)($value); }
    public function snapshot():array {return ['phase'=>$this->phase,'transport_invocations'=>$this->attempts,'events'=>$this->events,'creation_checkpoint'=>$this->creation,'counts_meaning'=>'Callable invocation, not proof that bytes reached the provider'];}
    private function event(array $value):void {if(count($this->events)<32)$this->events[]=$value;}
}
