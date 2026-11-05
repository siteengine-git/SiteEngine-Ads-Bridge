<?php
/** Actual time/setup/Doctor/field algorithms; synthetic WP, storage and vendor boundary. */
namespace FluentCrm\App\Models {
 class CustomContactField {
  public function getFieldTypes(){return array_fill_keys(['number','date','date_time','text','textarea'],[]);}
  public function getGlobalFields(){return ['fields'=>$GLOBALS['registry']];}
  public function saveGlobalFields($f){$GLOBALS['field_saves']++;$GLOBALS['registry']=$f;return $f;}
 }
}
namespace {
define('ABSPATH',__DIR__.'/');define('ARRAY_A','ARRAY_A');
$opts=[];$registry=[['slug'=>'unrelated','type'=>'text','label'=>'Preserve','extra'=>['a'=>1]]];$field_saves=0;$zone='UTC';require_once dirname(__DIR__).'/includes/crm-import/recovery.php';
$n=0;
function get_option($k,$d=false){return $GLOBALS['opts'][$k]??$d;}function update_option($k,$v,$a=false){$GLOBALS['opts'][$k]=$v;return true;}
function get_current_user_id(){return 7;}function wp_timezone_string(){return $GLOBALS['zone'];}
class SiteEngine_Ads_Bridge_Config {
 public static $instance;public $data=['client.google_ads_customer_id'=>'123-456-7890','client.timezone'=>'America/Chicago','fluent_forms.form_id'=>4,'fluent_forms.fields'=>['email'=>'email']];
 public static function instance(){return self::$instance??=new self;}
 public function get($k,$d=null){return $this->data[$k]??$d;}
}
class SiteEngine_Ads_Bridge_Coordination {public static function enter_setup($x){return ['ok'=>true];}public static function release(){return true;}}
class FakeWpdb {public $prefix='wp_',$last_error='',$form='form-A',$meta=[['meta_key'=>'crm_feed','value'=>'original']];public function prepare($q,...$v){return $q;}public function get_var($q){return $this->form;}public function get_results($q,$mode){return $this->meta;}}
$wpdb=new FakeWpdb;
require dirname(__DIR__).'/includes/class-siteengine-ads-bridge-time.php';
require dirname(__DIR__).'/includes/class-siteengine-ads-bridge-fields.php';
require dirname(__DIR__).'/includes/class-siteengine-ads-bridge-setup.php';
require dirname(__DIR__).'/includes/class-siteengine-ads-bridge-doctor.php';
function ck($v,$m){if(!$v)throw new \RuntimeException($m);$GLOBALS['n']++;echo 'PASS '.$m."\n";}
function refuses($f,$m){try{$f();}catch(\Throwable $e){ck(true,$m);return;}throw new \RuntimeException('Expected refusal: '.$m);}
$convert=static fn($r,$z='America/Chicago')=>SiteEngine_Ads_Bridge_Time::conversion($r,'12:00:00',$z);
ck($convert('2026-01-15 18:00:00')['value']==='2026-01-15 12:00:00-06:00','naive WP UTC event converts without changing instant');
ck($convert('2026-07-15 18:00:00')['value']==='2026-07-15 13:00:00-05:00','summer daylight offset');
ck($convert('2026-03-08 07:59:59Z')['value']==='2026-03-08 01:59:59-06:00','spring boundary before');
ck($convert('2026-03-08 08:00:00Z')['value']==='2026-03-08 03:00:00-05:00','spring boundary after');
$a=$convert('2026-11-01 01:30:00-05:00');$b=$convert('2026-11-01 01:30:00-06:00');ck(strtotime($b['value'])-strtotime($a['value'])===3600,'fold explicit offsets preserve distinct instants');
$zone='America/Chicago';ck($convert('2026-11-01 01:30:00')['error']!==''&&$convert('2026-03-08 02:30:00')['error']!=='','ambiguous/gap naive values blocked');
foreach(['2026-02-30','2026-01-01 garbage','2026-01-01T12:00:00+99:00']as$r)ck($convert($r)['error']!=='','invalid exact timestamp refused '.$r);
ck($convert('2026-07-15')['value']==='2026-07-15 12:00:00-05:00','date-only business date retains configured-zone noon');
$instant=strtotime('2026-11-01T06:30:00Z');$stamp=SiteEngine_Ads_Bridge_Time::event_stamp($instant);ck($stamp==='2026-11-01 01:30:00-05:00'&&strtotime($stamp)===$instant,'new lifecycle stamp has explicit offset');
$plan=SiteEngine_Ads_Bridge_Fields::provision(false);ck(count($plan['missing'])===8&&$field_saves===0,'fresh preview creates nothing');$original=$registry[0];
$r=SiteEngine_Ads_Bridge_Fields::provision(true);ck($r['created']===8&&$registry[0]===$original,'missing-only vendor save preserves existing exact definition');
$u=array_values(array_filter($registry,static fn($f)=>$f['slug']==='unqualified_at'));ck(count($u)===0,'unqualified_at is not provisioned');
SiteEngine_Ads_Bridge_Fields::provision(true);ck($field_saves===1,'repeat provisioning writes zero');$registry[1]['type']='text';refuses(static fn()=>SiteEngine_Ads_Bridge_Fields::provision(true),'existing type conflict requires owner review');
$registry[]=$registry[0];refuses(static fn()=>SiteEngine_Ads_Bridge_Fields::provision(true),'duplicate slug refuses write');array_pop($registry);
$c=SiteEngine_Ads_Bridge_Config::instance();$pin=SiteEngine_Ads_Bridge_Setup::verification_context('timezone');
refuses(static fn()=>SiteEngine_Ads_Bridge_Setup::record_verification('timezone','bad',[]),'stale/foreign verification pin refused');
refuses(static fn()=>SiteEngine_Ads_Bridge_Setup::record_verification('timezone',$pin,['account_timezone'=>'UTC']),'account timezone mismatch not falsely confirmed');
SiteEngine_Ads_Bridge_Setup::record_verification('timezone',$pin,['account_timezone'=>'America/Chicago']);ck(SiteEngine_Ads_Bridge_Setup::verification('timezone')['valid'],'manual timezone confirmation records actual context');
$c->data['client.google_ads_customer_id']='999-999-9999';ck(!SiteEngine_Ads_Bridge_Setup::verification('timezone')['valid'],'account change invalidates confirmation');$c->data['client.google_ads_customer_id']='123-456-7890';
$c->data['client.timezone']='UTC';ck(!SiteEngine_Ads_Bridge_Setup::verification('timezone')['valid'],'conversion zone change invalidates confirmation');$c->data['client.timezone']='America/Chicago';
$pin=SiteEngine_Ads_Bridge_Setup::verification_context('submission');SiteEngine_Ads_Bridge_Setup::record_verification('submission',$pin,[]);ck(SiteEngine_Ads_Bridge_Setup::verification('submission')['valid'],'manual submission mapping confirmed honestly');
$wpdb->meta[0]['value']='changed';ck(!SiteEngine_Ads_Bridge_Setup::verification('submission')['valid'],'feed metadata change invalidates confirmation');$wpdb->meta[0]['value']='original';$wpdb->form='changed';ck(!SiteEngine_Ads_Bridge_Setup::verification('submission')['valid'],'form structure change invalidates confirmation');$wpdb->form='form-A';
refuses(static fn()=>SiteEngine_Ads_Bridge_Setup::record_verification('submission',$pin,['contact_id'=>1]),'partial runtime pair refused');
$c->data['fluent_forms.form_id']=5;ck(!SiteEngine_Ads_Bridge_Setup::verification('submission')['valid'],'master form change invalidates confirmation');$c->data['fluent_forms.form_id']=4;
$d=new SiteEngine_Ads_Bridge_Doctor($c);$call=static function($name)use($d){$m=new \ReflectionMethod($d,$name);$m->setAccessible(true);$m->invoke($d);$p=new \ReflectionProperty($d,'results');$p->setAccessible(true);$r=$p->getValue($d);$p->setValue($d,[]);return $r;};
$c->data['google_sheets.enabled']=false;ck(str_contains($call('check_cron_health')[0]['message'],'Disabled:'),'disabled runtime distinct from never run');
$c->data['google_sheets.enabled']=true;$c->data['exports.won_jobs.enabled']=true;ck(str_contains($call('check_cron_health')[0]['message'],'No successful'),'enabled never-run distinguished');
$opts['seab_last_export_attempt']=['status'=>'failed'];ck(str_contains($call('check_cron_health')[0]['message'],'Failed'),'failed attempt distinguished');unset($opts['seab_last_export_attempt']);
$opts['seab_last_export_runtime']=['timestamp'=>time()-4000,'php_version'=>PHP_VERSION];ck(str_contains($call('check_cron_health')[0]['message'],'ago'),'stale successful attempt distinguished');
$c->data['google_sheets.credentials_path']='';$r=$call('check_google_sheets_config');ck(count(array_filter($r,static fn($v)=>str_contains($v['message'],'Missing path:')))===1,'missing credential path distinct');
$c->data['google_sheets.credentials_path']=__DIR__.'/definitely-absent.json';$r=$call('check_google_sheets_config');ck(count(array_filter($r,static fn($v)=>str_contains($v['message'],'file is missing')))===1,'missing file distinct');
$c->data['google_sheets.credentials_path']=__FILE__;$r=$call('check_google_sheets_config');ck(count(array_filter($r,static fn($v)=>str_contains($v['message'],'exists and is readable')))===1,'readable credentials checked without contents');
echo "SUMMARY $n PASS; synthetic storage/vendor boundaries; actual product algorithms\n";
}
