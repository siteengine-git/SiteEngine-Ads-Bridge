<?php
namespace SiteEngine\AdsBridge\Import;
use RuntimeException;
use Throwable;
/** Same wpdb/physical connection for every native save. No user-supplied profile. */
final class Session {
    private object $db;private string $connection;
    public function __construct(private array $kinds) {
        if(!defined('FLUENTCRM_PLUGIN_VERSION')||!in_array(FLUENTCRM_PLUGIN_VERSION,['3.2.6','3.3.1'],true))throw new RuntimeException('This importer supports FluentCRM 3.2.6 / 3.3.1. Installed adapter support must be updated for this version.');
        if(array_intersect($kinds,['email_sequence','email_sequence_mail'])&&(!defined('FLUENTCAMPAIGN_PLUGIN_VERSION')||FLUENTCAMPAIGN_PLUGIN_VERSION!=='3.3.1'))throw new RuntimeException('This package contains Pro email sequences and requires FluentCRM Pro 3.3.1.');
        $this->db=$GLOBALS['wpdb'];$this->connection=$this->physical();$this->check();
    }
    private function physical():string {
        if($GLOBALS['wpdb']!==$this->db||!($this->db->dbh instanceof \mysqli))throw new RuntimeException('Import database connection unavailable.');
        try{$r=mysqli_query($this->db->dbh,'SELECT CONNECTION_ID() id,DATABASE() db,@@autocommit ac,@@in_transaction tx');$v=$r?mysqli_fetch_assoc($r):null;}
        catch(Throwable $e){throw new RuntimeException('Import database connection lost.');}
        if(!$v||$v['db']!==DB_NAME||(int)$v['ac']!==1||(int)$v['tx']!==0)throw new RuntimeException('Import requires the primary autocommit connection outside a transaction.');
        return (string)$v['id'];
    }
    public function check():void {
        if($this->physical()!==$this->connection)throw new RuntimeException('Import database session changed.');
        \SiteEngine_Ads_Bridge_Coordination::assert_owner();
        $classes=['FluentCrm\\App\\Models\\Meta','FluentCrm\\App\\Models\\Tag','FluentCrm\\App\\Models\\Lists'];
        foreach($this->kinds as $kind){if(in_array($kind,['field','list','tag'],true))continue;$classes[]=Native::model($kind);}
        foreach(array_unique($classes) as $class){if(!class_exists($class))throw new RuntimeException('Required FluentCRM capability is missing: '.$class);
            $m=new $class();$c=$m->getConnection();
            if(get_class($c)!=='FluentCrm\\Framework\\Database\\Query\\WPDBConnection')throw new RuntimeException('Unsupported FluentCRM database adapter.');
            $p=new \ReflectionProperty($c,'wpdb');if($p->getValue($c)!==$this->db||$m->newQuery()->getQuery()->getConnection()!==$c)throw new RuntimeException('FluentCRM is not using the protected WordPress connection.');
        }
    }
}
