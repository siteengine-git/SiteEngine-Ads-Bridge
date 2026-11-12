<?php
/** Actual product algorithms, synthetic vendor/WordPress/HTTP boundaries. No installed claim. */
namespace FluentCrm\App\Models {
    class Query {
        public $class,$title,$slug;
        function __construct($c,$v){$this->class=$c;$this->title=$v;}
        function orWhere($k,$v){$this->slug=$v;return $this;}
        function limit($n){return $this;}
        function get(){return array_values(array_filter($GLOBALS['terms'][$this->class]??[],fn($r)=>$r->title===$this->title||$r->slug===$this->slug));}
        function count(){return count($this->get());}
    }
    class Lists {
        public $id,$title,$slug,$description,$is_public;
        static function where($k,$v){return new Query(static::class,$v);}
        function fill($b){foreach($b as$k=>$v)$this->$k=$v;}
        function save(){$this->id=++$GLOBALS['saves'];$GLOBALS['terms'][static::class][]=$this;return true;}
    }
    class Tag extends Lists {}
    class CustomContactField {
        function getFieldTypes(){return ['number'=>['type'=>'number'],'date'=>['type'=>'date'],'date_time'=>['type'=>'date_time'],'text'=>['type'=>'text'],'textarea'=>['type'=>'textarea'],'single-select'=>['type'=>'select-one']];}
        function getGlobalFields(){return ['fields'=>$GLOBALS['fields']];}
        function saveGlobalFields($f){$GLOBALS['field_saves']++;$GLOBALS['fields']=$f;}
    }
}
namespace {
define('ABSPATH',__DIR__.'/');define('ARRAY_A','ARRAY_A');define('SE_CONSENT_POLICY_VERSION','issuer-policy');
define('SEAB_PLUGIN_URL','https://example.test/plugin/');define('SEAB_VERSION','fixture');
class SiteEngine_Consent_Authority {static function current_eligibility(){throw new \LogicException('This test does not issue a grant.');}}
function sanitize_title($v){return trim(preg_replace('/[^a-z0-9]+/','-',strtolower($v)),'-');}
function sanitize_text_field($v){return strip_tags($v);}function wp_unslash($v){return $v;}
function sanitize_key($v){return preg_replace('/[^a-z0-9_]/','',strtolower($v));}function absint($v){return abs((int)$v);}
function home_url(){return 'https://example.test/';}function wp_parse_url($v,$p){return parse_url($v,$p);}
function admin_url($s){return 'https://example.test/wp-admin/'.$s;}
function esc_html($s){return htmlspecialchars((string)$s);}function esc_attr($s){return esc_html($s);}function esc_url($s){return esc_html($s);}
function selected($a,$b,$e=false){return $a==$b?'selected':'';}function checked($a,$b=true,$e=false){return $a==$b?'checked':'';}
function wp_nonce_field($x){echo '<input name="_wpnonce" value="fixture">';}function wp_create_nonce($x){return 'fixture';}
function submit_button($s,$t='primary',$n='submit',$wrap=true){echo '<button type="submit">'.$s.'</button>';}
function get_option($k,$d=false){return $GLOBALS['opts'][$k]??$d;}
function siteengine_get($k,$d=''){return ['business_name'=>'Example','timezone'=>'UTC','currency'=>'USD','google_ads_customer_id'=>'1234567890'][$k]??$d;}
function siteengine_has($k){return true;}function current_user_can($k){return true;}
class SiteEngine_Ads_Bridge_Config {
    static $c;public $data;function __construct(){$this->data=SiteEngine_Ads_Bridge_Settings::defaults();}
    static function instance(){return self::$c??=new self;}
    function all(){return $this->data;}function get($k,$d=null){$v=$this->data;foreach(explode('.',$k)as$p){if(!is_array($v)||!array_key_exists($p,$v))return $d;$v=$v[$p];}return $v;}
    function validate(){return [];}function configuration_source(){return 'settings';}function external_override_active(){return false;}
    static function lifecycle_roles(){return ['new','qualified','unqualified','won'];}
}
class DB {
    public $prefix='wp_',$last_error='',$meta=[];
    function prepare($s,...$a){return $s;}
    function get_results($s,$m){return $this->meta;}
    function get_var($s){return null;}
    function get_col($s){return [1];}
}
$wpdb=new DB;$opts=['seab_settings_v2'=>['existing'=>true]];$fields=[];$terms=[];$saves=0;$field_saves=0;$n=0;
require dirname(__DIR__).'/includes/class-siteengine-ads-bridge-settings.php';
require dirname(__DIR__).'/includes/class-siteengine-ads-bridge-lifecycle.php';
require dirname(__DIR__).'/includes/class-siteengine-ads-bridge-setup.php';
require dirname(__DIR__).'/includes/class-siteengine-ads-bridge-fields.php';
require dirname(__DIR__).'/includes/class-siteengine-ads-bridge-product-setup.php';
require dirname(__DIR__).'/includes/class-siteengine-ads-bridge-admin.php';
require dirname(__DIR__).'/includes/connectors/fieldedge/ConnectorDiagnostics.php';
function ck($v,$m){if(!$v)throw new \RuntimeException($m);$GLOBALS['n']++;echo 'PASS '.$m."\n";}
function refuses($f,$m){try{$f();}catch(\RuntimeException $e){ck(true,$m);return;}throw new \LogicException('Expected refusal: '.$m);}
ck(SiteEngine_Ads_Bridge_Product_Setup::policy()==='issuer-policy','policy comes from loaded issuer, not editable settings');
$plan=SiteEngine_Ads_Bridge_Product_Setup::crm();ck(count($plan['terms'])===7&&count($plan['fields']['missing'])===9&&$saves===0,'preview seven terms and nine fields without writes');
$fields=[['slug'=>'unrelated','label'=>'User edited','type'=>'text','private'=>'preserve']];$before=$fields;
$plan=SiteEngine_Ads_Bridge_Product_Setup::crm(true);ck($saves===7&&$field_saves===1&&$fields[0]===$before[0],'missing-only supported vendor saves preserve user definition');
$snapshot=serialize([$terms,$fields]);SiteEngine_Ads_Bridge_Product_Setup::crm(true);ck($saves===7&&$field_saves===1&&serialize([$terms,$fields])===$snapshot,'exact repeat no duplicate definitions or writes');
ck(!in_array('unqualified_at',array_column($fields,'slug'),true),'no unqualified_at provisioning');
$reason=array_values(array_filter($fields,fn($f)=>$f['slug']==='customer_rating'))[0];ck($reason['type']==='select-one'&&$reason['field_key']==='single-select'&&$reason['options']===SiteEngine_Ads_Bridge_Lifecycle::unqualified_reason_values(),'reason uses actual lifecycle vocabulary');
$fields[1]['type']='wrong';refuses(fn()=>SiteEngine_Ads_Bridge_Product_Setup::crm(true),'type conflict refuses before term creation');$fields[1]['type']='number';
$terms[\FluentCrm\App\Models\Tag::class][] = clone $terms[\FluentCrm\App\Models\Tag::class][0];
refuses(fn()=>SiteEngine_Ads_Bridge_Product_Setup::crm(true),'ambiguous tag identities refuse');array_pop($terms[\FluentCrm\App\Models\Tag::class]);
$d=SiteEngine_Ads_Bridge_Settings::defaults();$d['client']['slug']='retained-id';$d['client']['short_name']='Retained';$d['fluent_forms']['fields']['email']='custom_email';$d['exports']['won_jobs']['value_field']='custom_revenue';
$v=SiteEngine_Ads_Bridge_Product_Setup::values($d,['form_id'=>0,'credentials_path'=>'/private/new.json','account_timezone'=>'America/Chicago']);
ck($v['client']===$d['client']&&$v['fluent_forms']['fields']===$d['fluent_forms']['fields'],'incomplete save preserves internal identity/custom mappings');
ck($v['exports']['won_jobs']['value_field']==='custom_revenue','hidden custom revenue mapping preserved');
ck($v['google_sheets']['credentials_path']==='/private/new.json'&&!$v['google_sheets']['enabled'],'draft path saves separately from delivery enablement');
ck(!array_filter(array_column($v['exports'],'enabled')),'incomplete save keeps all four exports disabled');
$v=SiteEngine_Ads_Bridge_Settings::sanitize_config($v);ck($v['google_sheets']['account_timezone']==='America/Chicago','account timezone survives settings sanitizer separately from Base');
refuses(fn()=>SiteEngine_Ads_Bridge_Product_Setup::values($d,['account_timezone'=>'Imaginary/Zone']),'invalid account timezone refuses without replacing saved data');
$new=SiteEngine_Ads_Bridge_Product_Setup::values(SiteEngine_Ads_Bridge_Settings::defaults(),[]);ck($new['client']['slug']==='example-test'&&$new['client']['short_name']==='example-test','fresh internal defaults only when absent');
$wpdb->meta=[['value'=>json_encode(['enabled'=>true,'other_fields'=>[['label'=>'submission_id','item_value'=>'{submission.id}']]])]];
ck(SiteEngine_Ads_Bridge_Product_Setup::feed(4)['configured'],'actual vendor other_fields mapping recognized');
$wpdb->meta[]=$wpdb->meta[0];ck(!SiteEngine_Ads_Bridge_Product_Setup::feed(4)['configured'],'duplicate enabled mappings fail unambiguous configuration');
$wpdb->meta=[['value'=>json_encode(['enabled'=>false,'other_fields'=>[['label'=>'submission_id','item_value'=>'{submission.id}']]])]];ck(!SiteEngine_Ads_Bridge_Product_Setup::feed(4)['configured'],'disabled feed is not connected');
$wpdb->meta=[];ck(!SiteEngine_Ads_Bridge_Product_Setup::feed(4)['configured'],'missing feed not replaced by operator attestation');
foreach([['','id'],['relative.json','id'],['/definitely/absent.json','id']]as$args)refuses(fn()=>SiteEngine_Ads_Bridge_Product_Setup::test_connection(...$args),'connection refuses invalid/missing credentials before HTTP');
// Synthetic HTTP exercises only the read-only adapter, never real OAuth or Sheets.
class SiteEngine_Ads_Bridge_Google { static function token($c){return ['ok'=>true,'token'=>'fixture-secret-token','error'=>''];} }
class WP_Error {}
function is_wp_error($v){return $v instanceof WP_Error;}
function wp_remote_get($url,$args){$GLOBALS['requests'][]=[$url,$args];return $GLOBALS['response'];}
function wp_remote_retrieve_response_code($r){return $r['code'];}function wp_remote_retrieve_body($r){return $r['body'];}
$credential=tempnam(sys_get_temp_dir(),'seab-fixture-');file_put_contents($credential,json_encode(['type'=>'service_account','client_email'=>'fixture@example.invalid','private_key'=>'synthetic-not-a-key']));
try{
 $requests=[];$id='fixture_spreadsheet_123';$response=['code'=>200,'body'=>json_encode(['spreadsheetId'=>$id,'properties'=>['title'=>'Synthetic worksheet']])];
 $r=SiteEngine_Ads_Bridge_Product_Setup::test_connection($credential,$id);ck($r['write_verified']===false&&str_contains($r['message'],'Draft settings were not saved'),'read success never claims write or saved values');
 ck(count($requests)===1&&$requests[0][1]['timeout']===10&&$requests[0][1]['redirection']===0,'bounded exact metadata GET without redirect/retry');
 $response=['code'=>403,'body'=>'fixture-secret-token private contents'];try{SiteEngine_Ads_Bridge_Product_Setup::test_connection($credential,$id);throw new \LogicException('Expected error');}catch(\RuntimeException $e){ck(str_contains($e->getMessage(),'403')&&!str_contains($e->getMessage(),'fixture-secret'),'HTTP refusal preserves useful status and redacts remote body');}
 $response=new WP_Error;refuses(fn()=>SiteEngine_Ads_Bridge_Product_Setup::test_connection($credential,$id),'transport failure refuses');
 $response=['code'=>200,'body'=>json_encode(['spreadsheetId'=>'foreign','properties'=>['title'=>'Other']])];refuses(fn()=>SiteEngine_Ads_Bridge_Product_Setup::test_connection($credential,$id),'identity mismatch refuses');
 file_put_contents($credential,'{"private_key":"sensitive malformed');try{SiteEngine_Ads_Bridge_Product_Setup::test_connection($credential,$id);}catch(\RuntimeException $e){ck(!str_contains($e->getMessage(),'sensitive'),'malformed credential does not echo contents');}
}finally{unlink($credential);}
$m=new \ReflectionMethod(SiteEngine_Ads_Bridge_Admin::class,'render_setup_workflow');ob_start();$m->invoke(null,SiteEngine_Ads_Bridge_Config::instance(),'');$html=ob_get_clean();
ck(substr_count($html,'Save settings')===2&&substr_count($html,'type="submit">Save settings')===1,'one visible primary settings save and instruction');
foreach(['Import configuration','Client Slug','Short Name','Required Consent Policy','Record Operator Verification','Contact ID (optional)','<details','numbered steps']as$forbidden)ck(!str_contains($html,$forbidden),'Setup excludes '.$forbidden);
ck(str_contains($html,'Set up CRM requirements')&&str_contains($html,'Test connection'),'direct setup utility and draft test visible');
ck(substr_count($html,'<form ' )===2&&substr_count($html,'</form>')===2,'utility and single settings form are not nested');
ck(str_contains($html,'name="seab_tab" value="product"'),'ordinary authenticated save handler owns all ordinary controls');
$_GET=['tab'=>'setup'];ob_start();SiteEngine_Ads_Bridge_Admin::render();$page=ob_get_clean();
foreach(['>Setup</a>','>API Setup</a>','>Settings</a>']as$label)ck(substr_count($page,$label)===1,'primary navigation '.$label);
ck(str_contains($page,'position:sticky')&&str_contains($page,'id="seab-ordinary-settings"'),'single save remains reachable in long ordinary setup');
$_GET=['tab'=>'api'];ob_start();SiteEngine_Ads_Bridge_Admin::render();$api=ob_get_clean();
ck(str_contains($api,'FieldEdge')&&str_contains($api,'Disabled.')&&str_contains($api,'Create Missing FieldEdge Fields'),'API page defaults dormant without live action');
ck(str_contains($api,'FieldEdge connection')&&str_contains($api,'not_tested_by_diagnostics'),'API uses retained read-only connector facts, never a fake live PASS');
ck(hash_file('sha256',dirname(__DIR__).'/includes/connectors/fieldedge/ConnectorDiagnostics.php')==='78a7c9bc900dead2f30cfae6b7a47a23d5eb2e8933ffa3d649dcc0ca8b58d7bf','reviewed connector diagnostic bytes unchanged');
$_GET=['tab'=>'crm-import'];ob_start();SiteEngine_Ads_Bridge_Admin::render();$old=ob_get_clean();
ck(str_contains($old,'Set up CRM requirements')&&!str_contains($old,'Upload ZIP'),'legacy import URL does not retain a hidden normal wizard');
echo "SUMMARY $n PASS; actual algorithms/rendering, simulated vendor/DB; no installed/browser evidence\n";
}
