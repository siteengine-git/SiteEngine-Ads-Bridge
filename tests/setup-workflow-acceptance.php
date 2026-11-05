<?php
/** Production form detection and fresh defaults; WordPress DB is doubled. */
define('ABSPATH',__DIR__.'/');require dirname(__DIR__).'/includes/class-siteengine-ads-bridge-settings.php';require dirname(__DIR__).'/includes/class-siteengine-ads-bridge-setup.php';
$wpdb=new class {public $prefix='fixture_',$last_error='',$raw='';function prepare($q,...$a){return $q;}function get_var($q){return $this->raw;}};
$n=0;function ok($v,$m){global $n;if(!$v)throw new RuntimeException($m);$n++;echo 'PASS '.$m."\n";}
function definition($fields){$GLOBALS['wpdb']->raw=json_encode(['fields'=>$fields]);}
function field($name,$type='input_text'){return ['element'=>$type,'attributes'=>['name'=>$name],'settings'=>['label'=>$name]];}
$map=SiteEngine_Ads_Bridge_Settings::default_field_map();$fields=[];foreach(SiteEngine_Ads_Bridge_Setup::required_form_keys()as$key)$fields[]=field($map[$key],$key==='email'?'input_email':'input_text');definition($fields);
$v=SiteEngine_Ads_Bridge_Setup::form_connection(4,$map);ok($v['ready']&&$v['mapping']===$map,'Standard unique form preserves default mapping');
foreach($fields as&$f)if($f['attributes']['name']==='email')$f['attributes']['name']='email_one';unset($f);definition($fields);$v=SiteEngine_Ads_Bridge_Setup::form_connection(4,$map);ok($v['ready']&&$v['mapping']['email']==='email_one','One typed email candidate detected');
$fields[]=field('email_two','input_email');definition($fields);$v=SiteEngine_Ads_Bridge_Setup::form_connection(4,$map);ok(!$v['ready']&&count($v['needs']['email'])===2,'Ambiguous email requires explicit selection');
$r=SiteEngine_Ads_Bridge_Setup::resolve_form_mapping(4,$map,['email'=>'email_two']);ok($r['email']==='email_two','Explicit candidate resolves ambiguity');
$r=SiteEngine_Ads_Bridge_Setup::resolve_form_mapping(4,$map,['email'=>'']);ok($r===$map,'Incomplete choice remains saveable without inventing a selector');
try{SiteEngine_Ads_Bridge_Setup::resolve_form_mapping(4,$map,['email'=>'foreign']);throw new LogicException('must refuse');}catch(RuntimeException$e){ok(str_contains($e->getMessage(),'absent or ambiguous'),'Foreign selector refused');}
$custom=$map;$custom['email']='email_two';ok(SiteEngine_Ads_Bridge_Setup::form_connection(4,$custom)['mapping']===$custom,'Existing valid custom mapping preserved');
$custom['email']='removed_custom';ok(isset(SiteEngine_Ads_Bridge_Setup::form_connection(4,$custom)['needs']['email']),'Missing custom mapping requires explicit choice');
$fields[]=field('email_two','input_email');definition($fields);ok(isset(SiteEngine_Ads_Bridge_Setup::form_connection(4,$custom)['needs']['email']),'Duplicate selector not treated as unique');
$fields[]=field('se_consent_receipt_id');definition($fields);ok(!isset(SiteEngine_Ads_Bridge_Setup::form_connection(4,$map)['fields']['se_consent_receipt_id']),'Consent authority fields excluded from mapping choices');
$wpdb->raw='invalid';ok(!SiteEngine_Ads_Bridge_Setup::form_connection(4,$map)['ready'],'Malformed form fails readiness');
ok(!SiteEngine_Ads_Bridge_Setup::form_connection(0,$map)['ready'],'Unselected form remains incomplete');
ok(array_filter(SiteEngine_Ads_Bridge_Settings::defaults()['fluentcrm']['tags']['sources'])===[],'Fresh optional sources all Unused');
function admin_url($s){return 'https://example.test/wp-admin/'.$s;}
function sanitize_key($s){return preg_replace('/[^a-z0-9_]/','',strtolower($s));}
function acf_get_field_groups($args){return [['key'=>'base_business']];}
function acf_get_fields($g){return [['name'=>'bd_timezone','key'=>'field_legacy_timezone'],['name'=>'bd_business_name','key'=>'field_legacy_business']];}
require dirname(__DIR__).'/includes/class-siteengine-ads-bridge-admin.php';$m=new ReflectionMethod('SiteEngine_Ads_Bridge_Admin','business_data_url');
ok($m->invoke(null,'timezone')==='https://example.test/wp-admin/admin.php?page=business-data#acf-field_legacy_timezone','Business Data link resolves actual legacy field identity');
ok($m->invoke(null,'currency')==='https://example.test/wp-admin/admin.php?page=business-data','Missing field identity does not invent an anchor');
ok($m->invoke(null)==='https://example.test/wp-admin/admin.php?page=business-data','General Business Data destination preserved');
echo "SUMMARY $n PASS;production detector;synthetic DB boundary\n";
