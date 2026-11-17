<?php
/** Real pinned Base registry/crypto/service and AB resolver/JWT; synthetic storage/HTTP only. */
define('ABSPATH',__DIR__.'/');
class WP_Error { function __construct(public $code,public $message=''){} function get_error_code(){return $this->code;} }
function is_wp_error($r){return $r instanceof WP_Error;}
function __($s,$d=''){return $s;}
function current_user_can($s){return $GLOBALS['allowed'];}
function get_current_user_id(){return 7;}
function wp_verify_nonce($n,$a){return $n==='fixture' && $a==='siteengine-credentials-v1';}
function wp_generate_uuid4(){return sprintf('%s-%s-4%s-8%s-%s',bin2hex(random_bytes(4)),bin2hex(random_bytes(2)),substr(bin2hex(random_bytes(2)),1),substr(bin2hex(random_bytes(2)),1),bin2hex(random_bytes(6)));}
function wp_json_encode($v){return json_encode($v);}
function admin_url($s){return 'https://fixture.invalid/wp-admin/'.$s;}
function add_query_arg($a,$u){return $u.'?'.http_build_query($a);}
function sanitize_text_field($s){return trim((string)$s);} function sanitize_key($s){return $s;} function absint($v){return abs((int)$v);}
function get_option($k,$d=null){return $GLOBALS['options'][$k]??$d;}
function update_option($k,$v,$a=false){$GLOBALS['writes']++;$GLOBALS['options'][$k]=$v;return true;}
class SiteEngine_Ads_Bridge_Config { static $c; public $external=false; function __construct(public $data){} static function instance(){return self::$c;} function external_override_active(){return $this->external;} function get($k,$d=null){$v=$this->data;foreach(explode('.',$k)as$p){if(!is_array($v)||!array_key_exists($p,$v))return $d;$v=$v[$p];}return $v;} }
class SiteEngine_Ads_Bridge_Setup { static function with_configuration($f){if($GLOBALS['busy'])throw new RuntimeException('Synthetic active writer');return $f();} }
class MemoryStore {
    public $rows=[],$audit=[],$fail=false;
    function load($id){return $this->fail?new WP_Error('storage_unavailable'):$this->rows[$id]??null;}
    function save($id,$expected,$envelope,$audit){if(($this->rows[$id]['revision']??0)!==$expected)return new WP_Error('revision_conflict');$this->rows[$id]=['revision'=>$expected+1,'envelope'=>$envelope];$this->audit[]=$audit;return true;}
}
$n=0;$allowed=true;$writes=0;$busy=false;$requests=[];$options=[];$http='ok';$mutate=null;
function ck($v,$s){global $n;if(!$v)throw new RuntimeException('FAIL '.$s);$n++;echo 'PASS '.$s."\n";}
function refusal($f,$s){try{$f();}catch(RuntimeException $e){ck(!str_contains($e->getMessage(),'PRIVATE KEY')&&!str_contains($e->getMessage(),'CANARY'),$s);return;}throw new LogicException('Expected refusal '.$s);}
function wp_remote_post($u,$a){global $requests,$http,$mutate;$requests[]=[$u,$a];if($mutate){$f=$mutate;$mutate=null;$f();}return $http==='authfail'?['code'=>401,'body'=>'CANARY']:['code'=>200,'body'=>'{"access_token":"synthetic-token"}'];}
function wp_remote_get($u,$a){global $requests,$http;$requests[]=[$u,$a];return $http==='denied'?['code'=>403,'body'=>'CANARY']:($http==='transport'?new WP_Error('CANARY'):['code'=>200,'body'=>json_encode(['spreadsheetId'=>$http==='mismatch'?'foreign':'synthetic_sheet_123'])]);}
function wp_remote_retrieve_response_code($r){return $r['code'];}function wp_remote_retrieve_body($r){return $r['body'];}
require dirname(__DIR__).'/includes/class-siteengine-ads-bridge-credentials.php';
require dirname(__DIR__).'/includes/class-siteengine-ads-bridge-google.php';
require dirname(__DIR__).'/includes/class-siteengine-ads-bridge-settings.php';
$config=SiteEngine_Ads_Bridge_Config::$c=new SiteEngine_Ads_Bridge_Config(['google_sheets'=>['credentials_source'=>'base','spreadsheet_id'=>'synthetic_sheet_123','credentials_path'=>'/not-used']]);
if(in_array('--without-base',$argv,true)){refusal(fn()=>SiteEngine_Ads_Bridge_Credentials::resolve($config),'missing API refuses before file fallback');exit;}
$simple=in_array('--document-save',$argv,true);
$root=getenv('SEAB_BASE_SOURCE');$pin=json_decode(file_get_contents(__DIR__.($simple?'/base-261-dependency-pins.json':'/base-250-dependency-pins.json')),true);
foreach($pin['files']as$f=>$sha){$p=$root.'/includes/'.$f;ck(is_file($p)&&hash_file('sha256',$p)===$sha,'exact Base dependency '.$f);require $p;}
$store=new MemoryStore;$key=str_repeat(chr(37),32);$installation='11111111-1111-4111-8111-111111111111';
$service=new SiteEngine_Base_Credentials($store,fn()=>['installation'=>$installation,'active_key'=>'fixture','keys'=>['fixture'=>$key]]);
$prop=new ReflectionProperty(SiteEngine_Base_Credentials::class,'service');$prop->setValue(null,$service);
SiteEngine_Ads_Bridge_Credentials::register();SiteEngine_Ads_Bridge_Credentials::register();
$d=SiteEngine_Base_Credential_Registry::get('ads-bridge','google-sheets');
ck($d['kind']==='document'&&$d['purposes']===['sheets-auth']&&$d['authority']==='base','single stable document registration allows protected entry while file source remains selected');
ck(($d['admin_mode']??'staged')===($simple?'simple':'staged'),'ordinary Save opt-in only on exact supported document contract');
if($simple)ck($d['document_label']==='Google service-account JSON'&&str_contains(SiteEngine_Ads_Bridge_Credentials::setup_guidance(),'click Save credentials'),'translated document label and ordinary Save guidance');
ck(!$d['allow_remove']&&!$d['allow_rollback']&&$d['allow_replace'],'no implicit remove or rollback; protected replacement supported');
ck(str_contains(SiteEngine_Ads_Bridge_Credentials::admin_url(),'module=ads-bridge&credential=google-sheets'),'actual registered Base group link');
$allowed=false;ck(SiteEngine_Ads_Bridge_Credentials::admin_url()===null,'unauthorized link safely omitted');$allowed=true;
refusal(fn()=>SiteEngine_Ads_Bridge_Credentials::resolve($config),'missing active document fails closed');
$private=openssl_pkey_new(['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA,'config'=>getenv('OPENSSL_CONF')]);openssl_pkey_export($private,$pem,null,['config'=>getenv('OPENSSL_CONF')]);
$data=['type'=>'service_account','project_id'=>'synthetic','private_key_id'=>'fixture','client_id'=>'123','client_email'=>'fixture@synthetic.iam.gserviceaccount.com','private_key'=>$pem,'token_uri'=>'https://oauth2.googleapis.com/token'];
$raw=json_encode($data,JSON_PRETTY_PRINT);$file=tempnam(sys_get_temp_dir(),'seab-credential-test-');file_put_contents($file,$raw);$config->data['google_sheets']['credentials_path']=$file;
try {
    foreach(['[]','{bad',str_repeat('x',131073),json_encode(array_diff_key($data,['private_key'=>1])),json_encode(array_replace($data,['token_uri'=>'https://foreign.invalid'])),json_encode(array_replace($data,['private_key'=>'CANARY']))]as$i=>$v)refusal(fn()=>SiteEngine_Ads_Bridge_Credentials::document($v),'invalid document '.$i.' rejected/redacted');
    ck(SiteEngine_Ads_Bridge_Credentials::document($raw)['private_key']===$pem,'formatted JSON and multiline RSA key preserved');
    $_SERVER['REQUEST_METHOD']='POST';$context=['nonce'=>'fixture','installation'=>$installation];
    $cmd=function($op,$extra=[])use($context,$service){$m=$service->metadata('ads-bridge','google-sheets','default');$r=$service->command(array_merge(['module'=>'ads-bridge','credential'=>'google-sheets','resource'=>'default','operation'=>$op,'expected_revision'=>$m['revision'],'operation_id'=>wp_generate_uuid4()],$extra),$context);if(is_wp_error($r))throw new RuntimeException('Fixture Base command failed '.$r->code);return $r;};
    if($simple){$cmd('save',['candidate'=>$raw]);ck($service->metadata('ads-bridge','google-sheets','default')['staged_version']===null,'ordinary Save creates active document without intermediate stage');}
    else{$stage=$cmd('stage',['candidate'=>$raw]);refusal(fn()=>SiteEngine_Ads_Bridge_Credentials::resolve($config),'staged-only document not active');$cmd('activate',['version_id'=>$stage['staged_version']]);}
    ck(SiteEngine_Ads_Bridge_Credentials::resolve($config)['credentials']===$data,'actual encrypted Base service decrypts exact active document');
    ck(!str_contains(json_encode($store->rows),$pem)&&!str_contains(json_encode($store->audit),'private_key'),'encrypted store/audit contain no plaintext document');
    define('WP_CLI',true);require dirname(__DIR__).'/includes/class-siteengine-ads-bridge-cli.php';
    $config->data['google_sheets']['enabled']=true;
    $method=new ReflectionMethod(SiteEngine_Ads_Bridge_CLI::class,'prepare_family_sheet_context');
    $context=$method->invoke(new SiteEngine_Ads_Bridge_CLI(),$config,'Synthetic');
    ck($context['spreadsheet_id']==='synthetic_sheet_123'&&$context['token']==='synthetic-token','actual CLI family context reads active Base document through shared resolver');$requests=[];
    $config->data['google_sheets']['credentials_source']='file';
    $context=$method->invoke(new SiteEngine_Ads_Bridge_CLI(),$config,'Synthetic');
    ck($context['token']==='synthetic-token','actual CLI context supports preserved file credentials');$requests=[];
    ck(SiteEngine_Ads_Bridge_Credentials::resolve($config)['credentials']===$data,'file runtime uses identical validator/resolver');
    $state=SiteEngine_Ads_Bridge_Credentials::status($config);ck($state['configured']&&$state['locally_valid']&&!$state['authenticated']&&!$requests,'local status has no provider request or authentication claim');
    $selected=$config->data['google_sheets']['credentials_source'];
    foreach(['',null,[],true,'partial']as$bad){$config->data['google_sheets']['credentials_source']=$bad;refusal(fn()=>SiteEngine_Ads_Bridge_Credentials::resolve($config),'explicit malformed authoritative selector rejected');}
    $config->data['google_sheets']['credentials_source']=$selected;
    $config->data['google_sheets']['credentials_source']='partial';refusal(fn()=>SiteEngine_Ads_Bridge_Credentials::resolve($config),'unknown authoritative source never falls back');$config->data['google_sheets']['credentials_source']='file';
    $before=file_get_contents($file);$result=SiteEngine_Ads_Bridge_Credentials::test($config,'base',$file,'synthetic_sheet_123');
    ck($result['authenticated']&&$result['spreadsheet_readable']&&!$result['write_verified']&&count($requests)===2,'real JWT signer; synthetic token and exact metadata read; no append');
    foreach($requests as[$url,$a])ck($a['timeout']===10&&$a['redirection']===0&&$a['limit_response_size']===65536,'bounded Google request');
    ck($writes===0&&file_get_contents($file)===$before,'read-only test leaves settings and previous file exact');
    $options['seab_settings_v2']=$config->data;
    foreach(['authfail','denied','transport','mismatch']as$mode){$http=$mode;$w=$writes;refusal(fn()=>SiteEngine_Ads_Bridge_Credentials::test_and_select('base',$file,'synthetic_sheet_123'),'provider '.$mode.' blocks transition');ck($writes===$w,'failed test does not select source');}
    $http='ok';$mutate=function()use($config){$GLOBALS['options']['seab_settings_v2']['late']='preserve';};refusal(fn()=>SiteEngine_Ads_Bridge_Credentials::test_and_select('base',$file,'synthetic_sheet_123'),'concurrent settings change refuses transition');$options['seab_settings_v2']=$config->data;
    $mutate=function()use($cmd,$raw,$simple){if($simple){$cmd('save',['candidate'=>$raw]);}else{$stage=$cmd('stage',['candidate'=>$raw]);$cmd('activate',['version_id'=>$stage['staged_version']]);}};refusal(fn()=>SiteEngine_Ads_Bridge_Credentials::test_and_select('base',$file,'synthetic_sheet_123'),'credential change during test refuses transition');
    $busy=true;refusal(fn()=>SiteEngine_Ads_Bridge_Credentials::test_and_select('base',$file,'synthetic_sheet_123'),'active writer protects transition');$busy=false;
    $r=SiteEngine_Ads_Bridge_Credentials::test_and_select('base',$file,'synthetic_sheet_123');
    ck(!isset($r['identity'])&&$options['seab_settings_v2']['google_sheets']['credentials_source']==='base','explicit verified selection saves only source, not secret/fingerprint');
    ck($options['seab_settings_v2']['google_sheets']['credentials_path']===$file&&file_get_contents($file)===$before,'prior path/file preserved for deliberate rollback');
    $config->data=$options['seab_settings_v2'];
    $savedRows=$store->rows;foreach($store->rows as &$row){$row['envelope']='malformed CANARY';}unset($row);refusal(fn()=>SiteEngine_Ads_Bridge_Credentials::resolve($config),'malformed Base envelope never falls back');$store->rows=$savedRows;
    $store->fail=true;refusal(fn()=>SiteEngine_Ads_Bridge_Credentials::resolve($config),'Base storage failure does not use readable previous file');$store->fail=false;
    $r=SiteEngine_Ads_Bridge_Credentials::test_and_select('file',$file,'synthetic_sheet_123');ck($options['seab_settings_v2']['google_sheets']['credentials_source']==='file','deliberate verified rollback uses retained file');
    $config->external=true;refusal(fn()=>SiteEngine_Ads_Bridge_Credentials::test_and_select('base',$file,'synthetic_sheet_123'),'external configuration cannot be silently replaced');$config->external=false;
    ck(!str_contains(json_encode($options),'private_key')&&!str_contains(json_encode($r),'synthetic-token'),'ordinary settings/browser result exclude secrets and tokens');
    file_put_contents($file,'{bad');refusal(fn()=>SiteEngine_Ads_Bridge_Credentials::resolve($config,'file'),'malformed file no Base fallback');
} finally { unlink($file); }
echo "SUMMARY $n PASS; actual pinned Base crypto/AB code; storage, configuration lock and HTTP doubles, NOT installed Google acceptance\n";
