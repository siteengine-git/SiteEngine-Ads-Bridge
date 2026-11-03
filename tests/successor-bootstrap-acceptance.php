<?php
/** Actual plugin bootstrap + CLI entry points; WordPress/SQL boundaries doubled. */
namespace WP_CLI\Utils {function get_flag_value($a,$k,$d){return $a[$k]??$d;}}
namespace {
define('ABSPATH',__DIR__.'/fixture-wordpress/');define('WP_CLI',true);$writes=0;$hooks=[];
function plugin_dir_path($f){return dirname($f).'/';}function plugin_dir_url($f){return 'https://example.invalid/plugin/';}function plugin_basename($f){return basename($f);}
function register_activation_hook($file,$fn){$GLOBALS['hooks']['activation']=$fn;}function add_action(...$args){$GLOBALS['hooks'][]=$args;}
function update_option(...$args){$GLOBALS['writes']++;}function wp_json_encode($v){return json_encode($v);}
class WP_CLI{public static $commands=[],$lines=[];static function add_command($n,$c){self::$commands[$n]=$c;}static function line($v){self::$lines[]=$v;}static function error($v){throw new \RuntimeException($v);}}
$wpdb=(object)['prefix'=>'fixture_'];require dirname(__DIR__).'/siteengine-ads-bridge.php';$n=0;
function ok($v){global $n;if(!$v)throw new \RuntimeException('bootstrap assertion');$n++;}
ok(SEAB_VERSION==='0.6.4.2');ok(WP_CLI::$commands['se-ads']==='SiteEngine_Ads_Bridge_CLI');ok($writes===0&&$hooks['activation']==='seab_activate_plugin');
foreach(['seab_inquiry_adopt_current','seab_inquiry_prepare_reconciliation','seab_inquiry_commit_activation','seab_configuration_capability','seab_configuration_inspect_pending','seab_configuration_with_batch','seab_configuration_schema_sql','seab_configuration_migrate']as$f)ok(is_callable($f));
$cap=seab_configuration_capability();ok($cap['contract_version']==='1'&&$cap['inquiry_schema']==='2'&&$cap['coordination_schema']==='1'&&$cap['max_steps']===64);
ok(seab_configuration_schema_sql()===SiteEngine_Ads_Bridge_Coordination::schema_sql());ok(strpos(seab_configuration_schema_sql(),'ENGINE=InnoDB')!==false);
$cli=new SiteEngine_Ads_Bridge_CLI;foreach([[['inspect'],['apply'=>true]],[['repair'],[]],[['migrate','extra'],[]]]as$args){try{$cli->configuration_admin(...$args);throw new \LogicException('must refuse');}catch(\RuntimeException $e){ok(true);}}
ok($writes===0);ok(class_exists('SiteEngine_Ads_Bridge_Consent'));echo "PASS $n successor bootstrap/CLI checks; WordPress/native boundaries doubled\n";
}
