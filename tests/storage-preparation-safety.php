<?php
namespace SiteEngine\AdsBridge\Import {final class Store {static $job;static function job(){return self::$job;}}}
namespace {
define('ABSPATH',__DIR__.'/');$calls=0;
function se_crm_deployment_directory(){return null;}
class SiteEngine_Ads_Bridge_Coordination{static function enter_setup($s=false){$GLOBALS['calls']++;return ['ok'=>true];}static function release(){return true;}static function migrate($s){$GLOBALS['calls']++;return ['status'=>'complete'];}}
class SiteEngine_Ads_Bridge_Inquiries{static function table_exists(){return true;}static function schema_ready(){return true;}}
class SiteEngine_Ads_Bridge_Storage{static function prepare_ledger(){}}
require dirname(__DIR__).'/includes/crm-import/recovery.php';require dirname(__DIR__).'/includes/class-siteengine-ads-bridge-setup.php';$n=0;
foreach(['running','uncertain']as$state){\SiteEngine\AdsBridge\Import\Store::$job=['id'=>'retained-fixture','status'=>$state];try{SiteEngine_Ads_Bridge_Setup::initialize_storage();throw new \LogicException('must refuse');}catch(\RuntimeException$e){if(!str_contains($e->getMessage(),'retained-fixture')||$calls!==0)throw $e;$n++;}}
\SiteEngine\AdsBridge\Import\Store::$job=['id'=>'retained-fixture','status'=>'complete'];if(SiteEngine_Ads_Bridge_Setup::initialize_storage()!==true||$calls!==3)throw new \LogicException('healthy completed preparation');$n++;
echo "SUMMARY $n PASS;actual preparation admission;synthetic storage boundaries\n";
}
