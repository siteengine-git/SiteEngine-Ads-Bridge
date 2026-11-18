<?php
/** Fixture-only aggregate telemetry, synthetic transport and option storage. */
namespace WP_CLI\Utils {function get_flag_value($a,$k,$d=false){return $a[$k]??$d;}function format_items(...$a){}}
namespace {
if(PHP_SAPI!=='cli'||count($argv)!==3||!in_array($argv[1],['success','throw','row-failure'],true)||!is_file($argv[2]))exit(9);
define('ABSPATH',__DIR__.'/');define('WP_CLI',true);$store=$argv[2];$mode=$argv[1];
$options=[];
function get_option($k,$d=false){return $GLOBALS['options'][$k]??$d;}
function update_option($k,$v,$a=false){$GLOBALS['options'][$k]=$v;return true;}
function untrailingslashit($s){return rtrim($s,'/');}
class SiteEngine_Ads_Bridge_Config {static function instance(){return new self;}function get($k,$d=null){return $k==='exports.won_jobs.enabled'?true:$d;}}
class WP_CLI {static function line($v){}static function success($v){}static function error($v){throw new \RuntimeException($v);}}
require dirname(__DIR__).'/includes/class-siteengine-ads-bridge-cli.php';
class FixtureAggregate extends SiteEngine_Ads_Bridge_CLI {public function export_batch($a,$b){
 if($GLOBALS['mode']==='throw')throw new \RuntimeException('synthetic transport exception');
 if($GLOBALS['mode']==='row-failure'){$m=new \ReflectionMethod(SiteEngine_Ads_Bridge_CLI::class,'print_export_batch_summary');$m->invoke($this,[],['candidates'=>1,'exportable'=>1,'already_appended'=>0,'failed'=>1,'not_exportable'=>0],0,false);}
}}
$exit=0;try{(new FixtureAggregate)->export_enabled([],['apply'=>true,'limit'=>1]);}catch(\Throwable $e){$exit=2;}
register_shutdown_function(static function(){file_put_contents($GLOBALS['store'],json_encode($GLOBALS['options'],JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE));});exit($exit);
}
