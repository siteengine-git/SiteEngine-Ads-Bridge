<?php
/** Four-context corrections only; no application, browser, database or server execution. */
define( 'JX_LIBRARY_ONLY', true ); define( 'SEAB_STARTUP_LIBRARY_ONLY', true ); define( 'ARRAY_A', 'ARRAY_A' );
require dirname( __DIR__ ) . '/tools/acceptance/joint-ops.php';
require dirname( __DIR__ ) . '/tools/acceptance/joint-startup.php';
$count=0; function cx( $v, $m ) { global $count; if ( ! $v ) { throw new RuntimeException( $m ); } $count++; }
function cx_refuse( callable $f, $m ) { try { $f(); } catch ( Throwable $e ) { cx( true, $m ); return; } throw new RuntimeException( 'did not refuse: '.$m ); }
$target=array( 'server_hostname'=>'local.siteengine.cloud','owner'=>'almas9110','owner_uid'=>5004,'document_root'=>'/home/alma.siteengine.space/public_html',
    'prefix'=>'wp_','database_name_sha256'=>str_repeat('a',64),'database_server_hostname'=>'local.siteengine.cloud',
    'stored_home'=>'http://alma.siteengine.space','stored_siteurl'=>'http://alma.siteengine.space','effective_home'=>'http://alma.siteengine.space/','browser_origin'=>'https://alma.siteengine.space' );
cx( seab_joint_target_compare($target,$target), 'verified HTTP storage / HTTPS browser positive identity' );
foreach(array_keys($target) as $key){$foreign=$target;$foreign[$key]=$key==='owner_uid'?5005:'foreign';cx_refuse(function()use($target,$foreign){seab_joint_target_compare($target,$foreign);},'foreign '.$key);}
$swapped=$target;$swapped['stored_home']='https://alma.siteengine.space';cx_refuse(function()use($swapped){seab_joint_target_compare($swapped,$swapped);},'no broad scheme relaxation');
$dir=sys_get_temp_dir().'/joint-context-'.bin2hex(random_bytes(8));mkdir($dir,0700);$GLOBALS['jx_path']=$dir.'/journal.json';
$guard=$argv[1]??''; $raw=file_get_contents($guard); $sha='f3702f4570a67ccdfb0973e3d3b0e4bc9411bf5b5fb33550e569382c562dde8f';
cx(hash('sha256',$raw)===$sha,'actual retained guard source pin');$copy=$dir.'/cli-control.php';file_put_contents($copy,$raw);
cx(seab_joint_verify_startup_guard($copy,$copy,$sha),'startup verifier accepts actual bytes');
foreach(array($dir.'/missing.php',null)as$file)cx_refuse(function()use($file,$copy,$sha){seab_joint_verify_startup_guard($file,$copy,$sha);},'missing/unreadable guard stops before bootstrap');
cx_refuse(function()use($copy){seab_joint_verify_startup_guard($copy,$copy,str_repeat('0',64));},'mismatched guard hash');
// WP-CLI startup shim: only the absolute plugin.php filesystem include is doubled.
// The retained hook/filter/cron body is the actual hash-checked source, unchanged on disk.
define('WP_CLI',true);class WP_CLI{public static $hooks=[];public static function add_hook($n,$f){self::$hooks[$n][]=$f;}}
$GLOBALS['cx_filters']=[];function add_filter($n,$f,...$unused){$GLOBALS['cx_filters'][$n][]=$f;}
function has_filter($n){return isset($GLOBALS['cx_filters'][$n])?PHP_INT_MAX:false;}
class WP_Error{public $code;public function __construct($code,...$unused){$this->code=$code;}}
$fixture=str_replace("require_once '/home/alma.siteengine.space/public_html/wp-includes/plugin.php';",'/* filesystem include doubled by local fixture filter API */',$raw);
cx($fixture!==$raw,'one explicit filesystem boundary replacement');eval(substr($fixture,5));
cx(!defined('DISABLE_WP_CRON') && !$GLOBALS['cx_filters'],'guard registered before simulated application');
foreach(WP_CLI::$hooks['before_wp_load']as$hook)$hook();
cx(DISABLE_WP_CRON===true && has_filter('pre_http_request')!==false && has_filter('pre_wp_mail')!==false,'guard effective before bootstrap sentinel');
cx($GLOBALS['cx_filters']['pre_http_request'][0]() instanceof WP_Error && $GLOBALS['cx_filters']['pre_wp_mail'][0]()===false,'retained HTTP/mail denial body');
class CX_DB{public $last_error='',$rows=[],$writes=0;public function prepare($q,...$args){return str_replace('%d',(string)$args[0],$q);}public function get_results($q,$format=null){return $this->rows;}}
$wpdb=new CX_DB();$a=['prefix'=>'fixture_'];$base=['entries'=>['A'=>['id'=>101]],'pending'=>['operation'=>'close']];$j=$base;
cx(jx_submission_meta($a,$j)['submission_meta']==='VERIFIED_ABSENT','empty metadata positive, zero delete authority');
$wpdb->rows=[['id'=>9,'response_id'=>101]];$j=$base;
cx_refuse(function()use($a,&$j){jx_submission_meta($a,$j);},'owned metadata explicitly retained');
cx($j['closure_disposition']==='RETAINED_NOT_CLEAN' && jx_json($GLOBALS['jx_path'])['retained_resources']['submission_meta']['rows'][9]['response_id']===101,'durable exact-ID retained-resource receipt');
$j['closed']=true;cx_refuse(function()use($a,&$j){jx_stage('J7',$a,$j);},'retained rows can never claim clean J7');
foreach([ [['id'=>9,'response_id'=>999]], [['id'=>9,'response_id'=>101],['id'=>9,'response_id'=>101]], array_fill(0,513,['id'=>9,'response_id'=>101]) ] as $rows){
 $wpdb->rows=$rows;$j=$base;cx_refuse(function()use($a,&$j){jx_submission_meta($a,$j);},'foreign/duplicate/exhausted metadata');cx($j['closure_disposition']==='UNRESOLVED_NOT_CLEAN'&&!empty(jx_json($GLOBALS['jx_path'])['pending']),'unknown rows retain pending state');
}
cx($wpdb->writes===0,'no metadata or foreign-resource write');unlink($copy);unlink($GLOBALS['jx_path']);rmdir($dir);
echo "PASS {$count} context/guard/metadata checks; WP-CLI/filesystem/SQL shims only, no bootstrap\n";
