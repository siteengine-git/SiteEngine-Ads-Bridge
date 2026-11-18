<?php
/** AB executes real durable callables with explicitly synthetic OAuth/lead doubles. */
if ( ! defined('ABSPATH') || ! defined('WP_CLI') || ! WP_CLI || ! current_user_can('manage_options') ||
	! defined('SITEENGINE_FE_PROSPECTIVE_ISOLATED') || SITEENGINE_FE_PROSPECTIVE_ISOLATED !== true ||
	! isset($args) || count($args)!==4 || ! hash_equals(home_url(),$args[2]) || ! hash_equals(realpath(ABSPATH),realpath($args[3]) ?: '') ) { throw new RuntimeException('isolated_admin_context_required'); }
require_once __DIR__ . '/../../includes/connectors/fieldedge/LeadSubmission.php';
require_once __DIR__ . '/../../includes/connectors/fieldedge/OAuthClient.php';
require_once __DIR__ . '/../../tests/support/fieldedge/LeadFixture.php';
use SiteEngine\AdsBridge\Connectors\FieldEdge\LeadSubmission;
use SiteEngine\AdsBridge\Connectors\FieldEdge\LeadCreateDryRun;
use SiteEngine\AdsBridge\Connectors\FieldEdge\LeadResponse;
use SiteEngine\AdsBridge\Connectors\FieldEdge\OAuthClient;
use SiteEngine\AdsBridge\Tests\FieldEdge\LeadFixture;
foreach (array('seab_prospective_prepare','seab_prospective_claim','seab_prospective_observe','seab_prospective_read') as $function) { if (!function_exists($function)) { throw new RuntimeException('actual_ab_prospective_interface_required'); } }
$scenario=$args[0];
if (!in_array($scenario,array('success','validation','uncertain','conflict','malformed'),true) || !is_file($args[1]) || is_link($args[1])) { throw new RuntimeException('explicit_fixture_context_required'); }
$bytes=file_get_contents($args[1],false,null,0,8193);
if (strlen($bytes)>8192) { throw new RuntimeException('fixture_context_too_large'); }
$context=json_decode($bytes,true,8,JSON_THROW_ON_ERROR); unset($bytes);
if ($scenario==='conflict') {
	$existing_id=$context['existing_intent_id'] ?? '';
	if (!is_string($existing_id) || !preg_match('/\A[a-f0-9]{64}\z/D',$existing_id)) {throw new RuntimeException('existing_conflict_intent_required');}
	$prior=seab_prospective_read($existing_id);
	$prior_intent=json_decode($prior['intent_json'] ?? '',true,16,JSON_THROW_ON_ERROR);
	if (($prior_intent['inquiry_id'] ?? null)!==($context['inquiry_id'] ?? null) || ($prior_intent['source'] ?? null)!==($context['source'] ?? null)) {throw new RuntimeException('existing_conflict_source_mismatch');}
}
$input=LeadFixture::input();
$source=$context['source'] ?? array();
if (($source['type'] ?? null)==='fluent_form') { $input['inquiry']['source']=array('type'=>'fluent_form','form_id'=>$source['form_id'],'entry_id'=>$source['entry_id']); }
elseif (($source['type'] ?? null)==='call') { $input['inquiry']['source']=array('type'=>'call','evidence_id'=>$source['evidence_id']); }
else { throw new RuntimeException('canonical_source_required'); }
$input['inquiry']['inquiry_id']='synthetic-prospective-context';
if (isset($context['tenant_id'])) { $input['tenant_id']=$context['tenant_id']; }
if ($scenario==='conflict') { $input['service_request']['description']='Changed same-origin synthetic content must conflict'; }
$dry=LeadCreateDryRun::prepare($input);
$envelope=LeadSubmission::envelope($dry,$context,$context['operation_id']);
$http_attempts=0;$mail_attempts=0;$token_doubles=0;$lead_doubles=0;
add_filter('pre_http_request',static function()use(&$http_attempts){++$http_attempts;return new WP_Error('fe_prospective_no_network');},PHP_INT_MAX);
add_filter('pre_wp_mail',static function()use(&$mail_attempts){++$mail_attempts;return false;},PHP_INT_MAX);
$response=array('status'=>201,'content_type'=>'application/json','body'=>'{"data":{"leadId":"00000000-0000-4000-8000-000000000301","status":"Initiated","receivedAt":"2026-11-11T18:00:00Z"}}');
if ($scenario==='validation') { $response=array('status'=>400,'content_type'=>'application/json','body'=>'{"error":"synthetic validation"}'); }
if ($scenario==='malformed') { $response['body']='{"data":{}}'; }
$client=new OAuthClient(static function($method,$url,$headers,$body)use(&$token_doubles,&$lead_doubles,$scenario,$response){
	if ($method==='POST' && $url===OAuthClient::TOKEN_URL) {++$token_doubles;return array('status'=>200,'content_type'=>'application/json','body'=>'{"access_token":"synthetic-only.token","token_type":"Bearer","expires_in":3600}');}
	if (!LeadResponse::allowed($method,$url)) {throw new RuntimeException('unexpected_synthetic_route');}
	++$lead_doubles;
	if ($scenario==='uncertain') {throw new RuntimeException('synthetic_timeout');}
	return $response;
});
$credentials=array('client_id'=>'SYNTHETIC','client_secret'=>'SYNTHETIC-NOT-A-SECRET','target_id'=>$dry['tenant_id']);
$adapter=new LeadSubmission(null,null,null,null,static function($method,$url,$body)use($client,$credentials,$dry){
	if (!LeadResponse::allowed($method,$url) || $body!==$dry['body']) {throw new RuntimeException('exact_synthetic_request_required');}
	return $client->syntheticLead($credentials,$dry);
});
$result=$adapter->send($envelope);
echo json_encode(array('verification'=>'actual AB persistence; synthetic OAuth/lead transport only','scenario'=>$scenario,'result'=>$result,
	'token_doubles'=>$token_doubles,'lead_doubles'=>$lead_doubles,'http_attempts'=>$http_attempts,'mail_attempts'=>$mail_attempts,
	'envelope_sha256'=>hash('sha256',$envelope)),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
if ($http_attempts || $mail_attempts || $token_doubles>1 || $lead_doubles>1) {throw new RuntimeException('fixture_resource_ceiling_exceeded');}
