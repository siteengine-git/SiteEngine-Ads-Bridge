<?php
/** Deterministic SQL double. Never a native SQL/WordPress acceptance claim. */
trait SEAB_Coordination_Fake {
    public $last_error='';
    public $coord_row=null;
    public $coord_session=1;
    public $coord_busy=false;
    public $coord_missing=false;
    public $coord_missing_inquiry=false;
    public $coord_bad_column=false;
    public $coord_fail='';
    public $coord_fail_release=false;
    public $coord_pinned=false;
    public $coord_pin_supported=true;
    public $coord_transaction=0;
    public $coord_durable=1;
    public $coord_calls=array();
    public $coord_creates=0;
    public $coord_create_lost=false;
    public function seab_coordination_pin_session() {
        if (!$this->coord_pin_supported) return null;
        $this->coord_pinned=true;
        return function() { $this->coord_pinned=false; };
    }
    public function coord_query($method,$sql) {
        $this->last_error=''; $this->coord_calls[]=$sql;
        if ($this->coord_fail!=='' && strpos($sql,$this->coord_fail)!==false) {
            $this->last_error='secret raw injected timeout'; return null;
        }
        if (strpos($sql,'@@in_transaction')!==false) return $this->coord_transaction;
        if (strpos($sql,'@@autocommit')!==false) return 1;
        if (strpos($sql,'@@innodb_flush_log_at_trx_commit')!==false) return $this->coord_durable;
        if (strpos($sql,'@@read_only')!==false) return 0;
        if (strpos($sql,'SELECT DATABASE()')!==false) return DB_NAME;
        if (strpos($sql,'SELECT CONNECTION_ID()')!==false) return $this->coord_session;
        if (preg_match("/GET_LOCK\('([^']+)', 0\)/",$sql,$m)) {
            if ($this->coord_busy || (isset($this->locks[$m[1]]) && $this->locks[$m[1]]!==$this->coord_session)) return 0;
            $this->locks[$m[1]]=$this->coord_session; return 1;
        }
        if (preg_match("/IS_USED_LOCK\('([^']+)'\)/",$sql,$m)) return $this->locks[$m[1]]??null;
        if (preg_match("/RELEASE_LOCK\('([^']+)'\)/",$sql,$m)) {
            if ($this->coord_fail_release) return 0;
            if (($this->locks[$m[1]]??null)!==$this->coord_session) return 0;
            unset($this->locks[$m[1]]); return 1;
        }
        if (strpos($sql,'information_schema.TABLES')!==false) {
            if ($this->coord_missing && strpos($sql,'se_ads_configuration_guard')!==false) return array();
            if ($this->coord_missing_inquiry && strpos($sql,'se_ads_inquiries')!==false) return array();
            return array(array('ENGINE'=>strpos($sql,'se_ads_configuration_guard')!==false?'InnoDB':$this->storage_engine));
        }
        if (strpos($sql,'SHOW COLUMNS')!==false) {
            if (strpos($sql,'se_ads_configuration_guard')!==false) {
                $spec=array('slot'=>array('tinyint unsigned','NO'),'schema_version'=>array('varchar(8)','NO'),'destination'=>array('char(64)','NO'),'state'=>array('varchar(16)','NO'),'journal'=>array('longtext','NO'));
            } else {
                $spec=array();
                foreach (explode("\n",SiteEngine_Ads_Bridge_Inquiries::schema_sql()) as $line) {
                    if (preg_match('/^\s*([a-z_]+)\s+(bigint\(20\) unsigned|varchar\([0-9]+\)|char\([0-9]+\)|longtext|datetime|date|decimal\(20,6\))\s+(NOT NULL|NULL)/',$line,$m)) $spec[$m[1]]=array($m[2],$m[3]==='NULL'?'YES':'NO');
                }
            }
            $out=array(); foreach ($spec as $key=>$v) $out[]=array('Field'=>$key,'Type'=>$v[0],'Null'=>$v[1]);
            if ($this->coord_bad_column) array_pop($out);
            return $out;
        }
        if (strpos($sql,'SHOW INDEX')!==false) return array(array('Column_name'=>'slot','Non_unique'=>0));
        if (strpos($sql,'option_value')!==false) return $GLOBALS['seab_options']['seab_inquiry_schema_version']??null;
        if (strpos($sql,'GROUP BY state')!==false) {
            $groups=array();
            foreach ($this->rows as $r) {
                $key=$r['state'].'|'.($r['contract_version']??'1');
                if (!isset($groups[$key])) $groups[$key]=array('state'=>$r['state'],'contract_version'=>$r['contract_version']??'1','n'=>0);
                $groups[$key]['n']++;
            }
            return array_values($groups);
        }
        if (strpos($sql,'SELECT *')!==false && strpos($sql,'se_ads_configuration_guard')!==false) return $this->coord_row===null?array():array($this->coord_row);
        if (strpos($sql,'CREATE TABLE')!==false) {
            if (!$this->coord_missing) throw new RuntimeException('fake refuses existing-table CREATE');
            $this->coord_missing=false; $this->coord_creates++;
            if ($this->coord_create_lost) { $this->last_error='simulated lost DDL acknowledgement'; return false; }
            return 1;
        }
        if (strpos($sql,'INSERT INTO')!==false) {
            if (!preg_match("/VALUES \(1,'1','([^']+)','([^']+)','((?:''|[^'])*)'\)/s",$sql,$m)) throw new RuntimeException('fake cannot parse INSERT');
            $this->coord_row=array('slot'=>'1','schema_version'=>'1','destination'=>$m[1],'state'=>$m[2],'journal'=>str_replace("''","'",$m[3]));
            return 1;
        }
        throw new RuntimeException('unhandled fake coordination SQL');
    }
}

/** Simulate a fresh PHP request after old tests discard their session. */
function seab_test_reset_coordination_request() {
    foreach (array('held'=>null,'poisoned'=>false,'unpin'=>null) as $name=>$value) {
        $property=new ReflectionProperty('SiteEngine_Ads_Bridge_Coordination',$name);
        $property->setAccessible(true); $property->setValue(null,$value);
    }
}
