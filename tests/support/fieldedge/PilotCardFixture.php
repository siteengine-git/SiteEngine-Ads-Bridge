<?php
/** Caller-owned canonical context; synthetic request only, no inquiry/storage writes. */
namespace SiteEngine\AdsBridge\Tests\FieldEdge;
require_once __DIR__ . '/../../../includes/connectors/fieldedge/Foundation.php';
use SiteEngine\AdsBridge\Connectors\FieldEdge\Foundation;
final class PilotCardFixture {
	public static function card( array $context ): string {
		$s=$context['source']??array();
		if(($s['type']??null)!=='fluent_form'||!is_int($s['form_id']??null)||$s['form_id']<1||!is_int($s['entry_id']??null)||$s['entry_id']<1||($s['transaction_id']??null)!=='ff:'.$s['form_id'].':'.$s['entry_id']){throw new \InvalidArgumentException('canonical_form_context_required');}
		$tenant=Foundation::id($context['tenant_id']??null);
		if(!str_starts_with($tenant,'synthetic-')||!is_string($context['credential_reference_sha256']??null)||!preg_match('/\A[a-f0-9]{64}\z/D',$context['credential_reference_sha256'])){throw new \InvalidArgumentException('synthetic_target_reference_required');}
		$body='{"source":"siteengine","customer":{"phone":"2025550101"},"isExistingCustomer":false,"appointment":{},"serviceRequest":{},"preferredContactMethod":"Phone"}';
		$intent=array('contract_version'=>'1','provider'=>'fieldedge','tenant_id'=>$tenant,'operation_id'=>Foundation::id($context['operation_id']??null),'inquiry_id'=>Foundation::id($context['inquiry_id']??null),'source'=>$s,'request'=>array('method'=>'POST','endpoint'=>'https://api.fieldedge.com/open-api/v1/leads','body'=>$body,'body_sha256'=>hash('sha256',$body)),'synthetic'=>true);
		return json_encode(array('contract_version'=>'1','mode'=>'review_only','card_id'=>Foundation::id($context['card_id']??null),
			'target'=>array('provider'=>'fieldedge','environment'=>'Synthetic_Climatrol_Review','tenant_id'=>$tenant,'credential_reference_sha256'=>$context['credential_reference_sha256']),
			'intent_json'=>json_encode($intent,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),'request_budget'=>array('oauth_token_posts'=>1,'lead_posts'=>1,'automatic_retries'=>0),'unresolved'=>array(),'subject_kind'=>'synthetic'),JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
	}
}
