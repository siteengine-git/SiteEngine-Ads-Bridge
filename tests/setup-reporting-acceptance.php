<?php
/** Actual Ads resolver/admin, synthetic WordPress/Base/Doctor boundaries; no writes. */
define('ABSPATH',__DIR__.'/');define('SEAB_CONFIG_IS_EXTERNAL',false);define('SEAB_CONFIG_FILE',__DIR__.'/no-client-configuration.php');
$options=[];$base=['business_name'=>'Fixture Business','timezone'=>'America/Chicago','currency'=>'USD','google_ads_customer_id'=>'123-456-7890'];$present=array_fill_keys(array_keys($base),true);$writes=0;
function get_option($k,$d=false){return $GLOBALS['options'][$k]??$d;}
function update_option(...$a){$GLOBALS['writes']++;}function delete_option(...$a){$GLOBALS['writes']++;}
function siteengine_get($k,$d=''){return $GLOBALS['base'][$k]??$d;}function siteengine_has($k){return $GLOBALS['present'][$k]??false;}
function sanitize_key($s){return preg_replace('/[^a-z0-9_\-]/','',strtolower($s));}function sanitize_text_field($s){return trim((string)$s);}function absint($v){return abs((int)$v);}
function admin_url($p){return 'https://example.test/wp-admin/'.$p;}function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}function esc_url($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
require dirname(__DIR__).'/includes/class-siteengine-ads-bridge-settings.php';require dirname(__DIR__).'/includes/class-siteengine-ads-bridge-config.php';require dirname(__DIR__).'/includes/class-siteengine-ads-bridge-admin.php';
class SiteEngine_Ads_Bridge_Doctor{public static $fail=0,$rows=null;function __construct($c){}function run(){return self::$rows??array_fill(0,self::$fail,['status'=>'FAIL','check'=>'Fixture check','message'=>'Fixture explanation']);}function summary(){return ['FAIL'=>self::$fail,'WARN'=>0];}}
$n=0;function ok($v,$m){global$n;if(!$v)throw new RuntimeException($m);$n++;echo "PASS $m\n";}
function reset_config(){ $p=new ReflectionProperty('SiteEngine_Ads_Bridge_Config','instance');$p->setAccessible(true);$p->setValue(null,null);return SiteEngine_Ads_Bridge_Config::instance(); }
function source_as($c,$v){$p=new ReflectionProperty($c,'source');$p->setAccessible(true);$p->setValue($c,$v);}
$valid=SiteEngine_Ads_Bridge_Settings::defaults();$valid['client']['slug']='fixture';$valid['client']['short_name']='Fixture';$valid['consent']['required_policy_version']='fixture-policy';
$c=reset_config();$errors=$c->validate();$issues=SiteEngine_Ads_Bridge_Admin::setup_issues($c);
ok(count($issues)===count($errors)+1,'unsaved setup retains every validation error plus save action');
ok($issues[0]['key']==='setup' && strpos($issues[0]['url'],'siteengine-ads-bridge')!==false,'unsaved action links Ads setup');
$status=SiteEngine_Ads_Bridge_Admin::module_status();ok($status['status']==='setup_required' && count($status['setup_errors'])===count($issues),'module exposes all unsaved errors');
$options['seab_settings_v2']=$valid;$c=reset_config();ok($c->validate()===[],'complete actual resolver remains valid');
ok(SiteEngine_Ads_Bridge_Admin::setup_issues($c)===[],'valid stored Base and Ads values have no manufactured failures');
ok(SiteEngine_Ads_Bridge_Admin::module_status()['status']==='ready','valid config retains Ready');
$present['timezone']=false;$c=reset_config();$issues=SiteEngine_Ads_Bridge_Admin::setup_issues($c);ok($c->get('client.timezone')==='America/Chicago' && $c->validate()===[],'runtime fallback remains distinct from stored presence');
ok(count($issues)===1 && $issues[0]['key']==='client.timezone' && strpos($issues[0]['url'],'page=business-data')!==false,'missing saved Base timezone links Business Data even with nonempty fallback');
$present['timezone']=true;
foreach(['new','qualified','unqualified']as$role)$options['seab_settings_v2']['fluentcrm']['tags']['lifecycle'][$role]='';
$c=reset_config();source_as($c,'external');$issues=SiteEngine_Ads_Bridge_Admin::setup_issues($c);$status=SiteEngine_Ads_Bridge_Admin::module_status();
ok(count($issues)===3 && count($c->validate())===3,'all three installed-counterexample lifecycle errors retained');
foreach(['new','qualified','unqualified']as$index=>$role){ok($issues[$index]['key']==='fluentcrm.tags.lifecycle.'.$role,'exact '.$role.' key retained');ok(strpos($issues[$index]['label'],'lifecycle tag')!==false && strpos($issues[$index]['url'],'tab=lifecycle')!==false,'readable '.$role.' label and Ads lifecycle destination');ok(strpos($status['message'],$issues[$index]['label'])!==false,'module message includes '.$role.' failure');}
ok(strpos($status['message'],'UI saves do not replace SEAB_CONFIG_FILE')!==false,'external override does not suggest ineffective resaves');
$options['seab_settings_v2']=$valid;$options['seab_settings_v2']['client']['slug']=false;$options['seab_settings_v2']['client']['short_name']='';$base['google_ads_customer_id']='';$c=reset_config();$issues=SiteEngine_Ads_Bridge_Admin::setup_issues($c);
ok(count($issues)===3,'invalid/missing mixed-owner scalar errors all retained');
foreach($issues as$i){ok(strpos($i['url'],$i['key']==='client.google_ads_customer_id'?'page=business-data':'page=siteengine-ads-bridge')!==false,'correct destination '.$i['key']);}
$fake=new class{function configuration_source(){return'external';}function validate(){return['Missing or empty required config key: timezone','Unexpected <script>alert(1)</script> configuration'];}};
$issues=SiteEngine_Ads_Bridge_Admin::setup_issues($fake);ok($issues[0]['label']==='Business Timezone' && strpos($issues[0]['url'],'page=business-data')!==false,'legacy shared key routes to Business Data');
ob_start();SiteEngine_Ads_Bridge_Admin::render_setup_issues($fake);$html=ob_get_clean();ok(strpos($html,'<script>')===false && strpos($html,'&lt;script&gt;')!==false,'all reported error HTML is escaped');
ok(substr_count($html,'<li>')===2 && strpos($html,'Open Business Data')!==false && strpos($html,'Open Ads setup')!==false,'complete actionable list renders both destinations');
$base['google_ads_customer_id']='123-456-7890';$options['seab_settings_v2']=$valid;$c=reset_config();source_as($c,'external');ok(SiteEngine_Ads_Bridge_Admin::module_status()['label']==='External Config Active','valid external authority remains a warning, not falsely Ready');
source_as($c,'local_file');ok(SiteEngine_Ads_Bridge_Admin::module_status()['label']==='Migration Available','valid local-file migration behavior preserved');
source_as($c,'settings');SiteEngine_Ads_Bridge_Doctor::$fail=2;ok(SiteEngine_Ads_Bridge_Admin::module_status()['status']==='error','Doctor failures still prevent Ready');
ok($writes===0,'reporting never saves configuration or Base data');
echo "SUMMARY: $n PASS; actual resolver/reporting, synthetic WordPress/Base/Doctor\n";
