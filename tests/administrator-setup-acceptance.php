<?php
/** Actual administrator handlers/resolver; filesystem real, WP/vendor/SQL doubled. */
define('ABSPATH',__DIR__.'/');
$fixture=sys_get_temp_dir().'/seab-setup-'.bin2hex(random_bytes(6));mkdir($fixture);touch($fixture.'/executor.lock');
define('SEAB_CONFIG_IS_EXTERNAL',true);define('SEAB_CONFIG_FILE',$fixture.'/external.php');
$options=[];$allowed=true;$nonce=true;$calls=[];$directory=null;
function get_option($k,$d=false){return $GLOBALS['options'][$k]??$d;}
function update_option($k,$v,$autoload=false){$GLOBALS['options'][$k]=$v;return true;}
function sanitize_key($s){return preg_replace('/[^a-z0-9_\-]/','',strtolower($s));}
function sanitize_text_field($s){return trim((string)$s);}function absint($v){return abs((int)$v);}
function current_user_can($c){return $GLOBALS['allowed'];}function wp_unslash($v){return $v;}
function check_admin_referer($a){if(!$GLOBALS['nonce'])throw new RuntimeException('invalid nonce');}
function wp_die($s){throw new RuntimeException($s);}function esc_html($s){return htmlspecialchars((string)$s,ENT_QUOTES);}
function admin_url($s){return 'https://example.test/wp-admin/'.$s;}
class Redirect extends RuntimeException{}
function wp_safe_redirect($url){throw new Redirect($url);}
function se_crm_deployment_directory(){return $GLOBALS['directory'];}
class SiteEngine_Ads_Bridge_Inquiries {
 static $exists=true,$ready=true;
 static function table_exists(){return self::$exists;}static function schema_ready(){return self::$ready;}
 static function migrate($apply){$GLOBALS['calls'][]='inquiry migration';self::$ready=self::$exists=true;return ['applied'=>true];}
}
class SiteEngine_Ads_Bridge_Coordination {
 static $blocked=false;
 static function enter_setup($storage=false){$GLOBALS['calls'][]='destination lock';return ['ok'=>!self::$blocked,'reason'=>'pending'];}
 static function release(){$GLOBALS['calls'][]='release';return true;}
 static function migrate($apply){$GLOBALS['calls'][]='guard migration';return ['status'=>'complete'];}
}
$wpdb=new class {public $prefix='fixture_',$last_error='';function prepare($sql,$title){return $title;}function get_col($title){return $title==='Ambiguous'?[5,6]:($title==='Missing'?[]:[1]);}};
foreach(['settings','setup','config','admin']as$name)require dirname(__DIR__).'/includes/class-siteengine-ads-bridge-'.$name.'.php';
require dirname(__DIR__).'/includes/crm-import/recovery.php';
$directory=realpath($fixture);
$job=['format'=>'siteengine.crm.deployment-job/1','job_id'=>str_repeat('a',32),'state'=>'complete','inflight'=>null,'safe_checkpoint'=>'completed; native readback verified; owned maintenance restored'];file_put_contents($fixture.'/deployment-job.json',json_encode($job));
$data=SiteEngine_Ads_Bridge_Settings::defaults();$data['client']['timezone']='America/Chicago';$data['fluent_forms']['fields']['extra_preserved']='keep';$data['fluentcrm']['tags']['lifecycle']['new']='';
file_put_contents(SEAB_CONFIG_FILE,'<?php return '.var_export($data,true).';');
$n=0;function ok($v,$s){global$n;if(!$v)throw new LogicException($s);$n++;echo "PASS $s\n";}
function reset_config(){ $r=new ReflectionProperty('SiteEngine_Ads_Bridge_Config','instance');$r->setValue(null,null);return SiteEngine_Ads_Bridge_Config::instance(); }
function invoke($method,$error=null){try{SiteEngine_Ads_Bridge_Admin::$method();throw new LogicException('expected redirect or rejection');}catch(Redirect $e){if($error!==null)throw new LogicException('expected rejection');}catch(RuntimeException $e){if($error===null||strpos($e->getMessage(),$error)===false)throw $e;}}
try {
 ok(reset_config()->configuration_source()==='external','external authority default preserved');
 $allowed=false;invoke('handle_storage','Permission denied');$allowed=true;
 $nonce=false;invoke('handle_import','invalid nonce');$nonce=true;
 ok($options===[]&&$calls===[],'capability/nonce rejection precedes mutation');
 $_POST=[];invoke('handle_save','External SEAB_CONFIG_FILE');invoke('handle_import','Confirm');
 ok($options===[],'inert external settings saves and unconfirmed migration blocked');
 $_POST=['switch_authority'=>'1','external_sha256'=>str_repeat('0',64)];invoke('handle_import','Confirm');
 $_POST['external_sha256']=hash_file('sha256',SEAB_CONFIG_FILE);$old=file_get_contents(SEAB_CONFIG_FILE);invoke('handle_import');
 ok(reset_config()->configuration_source()==='settings','confirmed import switches actual resolver to settings');
 ok(file_get_contents(SEAB_CONFIG_FILE)===$old,'external PHP file untouched');
 ok($options['seab_settings_v2']['client']['timezone']==='America/Chicago'&&$options['seab_settings_v2']['fluent_forms']['fields']['extra_preserved']==='keep','valid timezone and unexposed configuration preserved');
 foreach(['google_sheets','exports']as$family)ok($options['seab_settings_v2'][$family]===$data[$family],'disabled '.$family.' flags preserved');
 $_POST=['seab_tab'=>'lifecycle','tag_new'=>'New','tag_qualified'=>'Qualified','tag_unqualified'=>'Unqualified','tag_won'=>'Won','list_customers'=>'Customers','list_future_customers'=>'Future Customers','list_dnc'=>'DNC','unqualified_reason_field'=>'customer_rating','unqualified_at_field'=>'unqualified_at','stale_new_days'=>'3'];
 $before=$options['seab_settings_v2'];invoke('handle_save');$expected=$before;$expected['fluentcrm']['tags']['lifecycle']['new']='New';
 ok($options['seab_settings_v2']===$expected,'lifecycle handler resolves missing mapping without unrelated changes');
 $_POST['tag_new']='';invoke('handle_save');ok($options['seab_settings_v2']['fluentcrm']['tags']['lifecycle']['new']==='','incomplete lifecycle saves while automation disabled');
 $_POST['tag_new']='New';invoke('handle_save');
 $_POST['lifecycle_automation_enabled']='1';
 foreach(['Missing','Ambiguous','Qualified']as$bad){$_POST['tag_new']=$bad;invoke('handle_save',$bad==='Qualified'?'distinct':'exactly one');ok($options['seab_settings_v2']===$expected,'invalid lifecycle identity blocked: '.$bad);}
 unset($_POST['lifecycle_automation_enabled']);
 $_POST['tag_new']='New';SiteEngine_Ads_Bridge_Coordination::$blocked=true;invoke('handle_save','pending');ok($options['seab_settings_v2']===$expected,'pending destination prevents settings write');SiteEngine_Ads_Bridge_Coordination::$blocked=false;
 $job['state']='active';file_put_contents($fixture.'/deployment-job.json',json_encode($job));invoke('handle_save','Retained/reserved deployment');ok($options['seab_settings_v2']===$expected,'durable active job blocks between invocations');
 $job['state']='complete';file_put_contents($fixture.'/deployment-job.json',json_encode($job));$foreign=fopen($fixture.'/executor.lock','c');flock($foreign,LOCK_EX);invoke('handle_save','executor.lock [busy]');flock($foreign,LOCK_UN);fclose($foreign);
 ok($options['seab_settings_v2']===$expected,'live executor blocks overlapping save');
 $_POST=['confirm_storage'=>'1'];$calls=[];SiteEngine_Ads_Bridge_Inquiries::$ready=SiteEngine_Ads_Bridge_Inquiries::$exists=false;invoke('handle_storage');
 ok($calls===['destination lock','inquiry migration','release','guard migration'],'fresh storage uses existing migrations in order');
 $calls=[];invoke('handle_storage');ok($calls===['destination lock','release','guard migration'],'ready inquiry storage never replays migration');
 file_put_contents(SEAB_CONFIG_FILE,$old."\n// changed by external authority\n");ok(reset_config()->configuration_source()==='external','external file drift visibly resumes external authority');
 invoke('handle_save','External SEAB_CONFIG_FILE');
 echo "SUMMARY: $n PASS; actual handlers/resolver; WP/vendor/SQL doubles\n";
} finally {foreach(glob($fixture.'/*')as$f)unlink($f);rmdir($fixture);}
