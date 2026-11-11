<?php
/** AB-owned isolated card review/simulation only; never native production dispatch. */
ini_set('display_errors','0');ini_set('log_errors','0');ini_set('zend.exception_ignore_args','1');
try {
 if(!defined('WP_CLI')||!WP_CLI||!current_user_can('manage_options')||!defined('SITEENGINE_FE_PILOT_REVIEW_ISOLATED')||SITEENGINE_FE_PILOT_REVIEW_ISOLATED!==true||!isset($args)||count($args)!==5||!hash_equals(home_url(),$args[3])||!hash_equals(realpath(ABSPATH),realpath($args[4])?:'')){throw new RuntimeException();}
 if(!in_array($args[0],array('review','simulate','disabled-execute'),true)||!is_file($args[1])||is_link($args[1])||!preg_match('/\A[a-f0-9]{64}\z/D',$args[2])){throw new RuntimeException();}
 $bytes=file_get_contents($args[1],false,null,0,32769);
 if(strlen($bytes)>32768||hash('sha256',$bytes)!==$args[2]){throw new RuntimeException();}
 require_once __DIR__.'/../../includes/connectors/fieldedge/PilotReview.php';
 $gate=new \SiteEngine\AdsBridge\Connectors\FieldEdge\PilotReview($bytes);
 $posts=0;$http=0;$mail=0;
 add_filter('pre_http_request',static function()use(&$http){++$http;return new WP_Error('pilot_review_no_network');},PHP_INT_MAX);
 add_filter('pre_wp_mail',static function()use(&$mail){++$mail;return false;},PHP_INT_MAX);
 if($args[0]==='review'){$result=$gate->review();}
 elseif($args[0]==='disabled-execute'){$result=array('status'=>'held','reason_code'=>'native_production_post_disabled');}
 else{$result=$gate->send(static function($method,$url,$body)use(&$posts):array{
  if(!\SiteEngine\AdsBridge\Connectors\FieldEdge\LeadResponse::allowed($method,$url)||++$posts>1){throw new RuntimeException();}
  return array('status'=>201,'content_type'=>'application/json','body'=>'{"data":{"leadId":"00000000-0000-4000-8000-000000000501","status":0}}');
 });}
 echo json_encode(array('verification'=>'actual AB card/persistence; synthetic transport only','card_sha256'=>$args[2],'result'=>$result,'token_doubles'=>0,'lead_doubles'=>$posts,'actual_http'=>$http,'mail'=>$mail,'production_post_enabled'=>false),JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
} catch(Throwable$error){echo '{"status":"held","reason_code":"pilot_review_execution_unconfirmed","production_post_enabled":false}'."\n";}
