<?php
/** Destination-wide cooperating-writer protocol. Migration requires explicit operator apply. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Trusted driver extension for isolated PDO tests/custom driver integration. */
interface SiteEngine_Ads_Bridge_Coordination_Driver {
    /** Disable reconnect/retry until returned restoration closure is called. */
    public function seab_coordination_pin_session();
}

final class SiteEngine_Ads_Bridge_Coordination {
    const VERSION = '1';
    const SCOPE = 'destination_shared_configuration';
    private static $held = null;
    private static $poisoned = false;
    private static $unpin = null;

    public static function capability() {
        global $wpdb;
        $identity = ( defined('DB_HOST') ? DB_HOST : '' ) . '|' . ( defined('DB_NAME') ? DB_NAME : '' ) . '|' . $wpdb->prefix;
        return array('contract_version'=>self::VERSION,'destination'=>hash('sha256',$identity),
            'scope'=>self::SCOPE,'inquiry_schema'=>'2','coordination_schema'=>'1',
            'session_model'=>'single_php_process_single_wpdb_session','max_steps'=>64);
    }
    public static function table() { global $wpdb; return $wpdb->prefix . 'se_ads_configuration_guard'; }
    public static function schema_sql() {
        $t=self::table();
        return "CREATE TABLE `{$t}` (slot tinyint unsigned NOT NULL PRIMARY KEY, schema_version varchar(8) NOT NULL, destination char(64) NOT NULL, state varchar(16) NOT NULL, journal longtext NOT NULL) ENGINE=InnoDB";
    }
    /** Additive only: never repair, reset or overwrite existing storage/journals. */
    public static function migrate($apply=false) {
        $out=self::capability()+array('status'=>'blocked','reason_code'=>'coordination_operation_failed',
            'applied'=>false,'created'=>false,'creation_state'=>'not_attempted','exists_before'=>null,'ddl_attempted'=>false,'sql'=>self::schema_sql());
        $acquired=false;
        try {
            if (!is_bool($apply)) self::fail('coordination_request_invalid');
            self::acquire('configuration'); $acquired=true;
            $table=self::table();
            if (!preg_match('/^[A-Za-z0-9_]+$/D',$table)) self::fail('coordination_identifier_unsupported');
            // Validate inquiry schema/option/states, without changing pending work.
            self::pending_inquiries();
            global $wpdb;
            $tables=self::rows($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$table));
            if (count($tables)>1) self::fail('coordination_schema_unsupported');
            $out['exists_before']=count($tables)===1;
            if ($out['exists_before']) {
                $row=self::guard_row();
                if ($row && $row['state']!=='complete') self::fail('coordination_configuration_recovery_required');
            }
            if (!$apply) {
                $out['status']='preview'; $out['reason_code']=$out['exists_before']?'coordination_schema_verified':'coordination_migration_required';
            } else {
                if (!$out['exists_before']) {
                    self::assert_owner(); $out['ddl_attempted']=true; $out['creation_state']='unknown';
                    self::sql(self::schema_sql());
                    if (self::guard_row()!==null) self::fail('coordination_state_unsupported');
                    $out['created']=true; $out['creation_state']='verified';
                }
                self::assert_owner(); $out['status']='complete'; $out['reason_code']='coordination_schema_verified'; $out['applied']=true;
            }
        } catch (Throwable $e) { $out['status']='blocked'; $out['reason_code']=self::reason($e); $out['applied']=false; }
        if ($acquired && !self::release()) { $out['status']='blocked'; $out['applied']=false; $out['reason_code']='coordination_release_failed'; }
        return $out;
    }
    private static function fail($reason) { throw new RuntimeException($reason); }
    private static function checked($method,$sql) {
        global $wpdb;
        // MariaDB statement deadline, not a client/network hard deadline.
        $sql='/*seab-coordination*/ SET STATEMENT max_statement_time=2 FOR '.$sql;
        $value=$method==='get_var' ? $wpdb->$method($sql) : $wpdb->$method($sql,ARRAY_A);
        $error=(string)$wpdb->last_error; // Capture BEFORE any other query.
        if ($error!=='' || $value===false || $value===null) self::fail('coordination_read_failed');
        return $value;
    }
    private static function scalar($sql) { return self::checked('get_var',$sql); }
    private static function rows($sql) {
        $rows=self::checked('get_results',$sql);
        if (!is_array($rows)) self::fail('coordination_read_failed');
        return $rows;
    }
    private static function sql($sql) {
        global $wpdb;
        $result=$wpdb->query('/*seab-coordination*/ SET STATEMENT max_statement_time=2 FOR '.$sql);
        $error=(string)$wpdb->last_error;
        if ($result===false || $error!=='') self::fail('coordination_persistence_failed');
        return $result;
    }
    private static function matches($request) {
        $cap=self::capability();
        foreach (array('contract_version','destination','scope','inquiry_schema','coordination_schema') as $key) {
            if (!isset($request[$key]) || $request[$key]!==$cap[$key]) self::fail('coordination_identity_mismatch');
        }
    }
    private static function name() { return 'seab_c_'.substr(self::capability()['destination'],0,48); }
    private static function poison() {
        self::$poisoned=true;
        global $wpdb;
        $drivers=array($wpdb);
        if (self::$held!==null && self::$held['wpdb']!==$wpdb) $drivers[]=self::$held['wpdb'];
        foreach ($drivers as $driver) {
            if (method_exists($driver,'close')) { try { $driver->close(); } catch (Throwable $e) {} }
            if (get_class($driver)==='wpdb') $driver->ready=false;
        }
        self::$unpin=null; // Never re-enable a discarded request's retry path.
    }
    private static function pin() {
        global $wpdb;
        if ($wpdb instanceof SiteEngine_Ads_Bridge_Coordination_Driver) {
            self::$unpin=$wpdb->seab_coordination_pin_session();
        } elseif (get_class($wpdb)==='wpdb' && PHP_VERSION_ID>=80300 && extension_loaded('mysqli')) {
            // Keep the SAME wpdb object (including references held by vendor ORM).
            // Stock wpdb's error-2006 retry calls check_connection(); zero retries
            // forbids reconnecting/replaying the failed mutation in that path.
            $property=new ReflectionProperty('wpdb','reconnect_retries');
            $property->setAccessible(true); $prior=$property->getValue($wpdb);
            $property->setValue($wpdb,0);
            if ($property->getValue($wpdb)!==0) self::fail('coordination_driver_unsupported');
            self::$unpin=static function() use($property,$prior,$wpdb) { $property->setValue($wpdb,$prior); };
        } else self::fail('coordination_driver_unsupported');
        if (!is_callable(self::$unpin)) self::fail('coordination_driver_unsupported');
        if (method_exists($wpdb,'suppress_errors')) {
            $prior=$wpdb->suppress_errors(true); $restore=self::$unpin;
            self::$unpin=static function() use($wpdb,$prior,$restore) { $restore(); $wpdb->suppress_errors($prior); };
        }
    }
    private static function unpin() {
        if (self::$unpin!==null) { $restore=self::$unpin; self::$unpin=null; $restore(); }
    }
    private static function acquire($mode) {
        global $wpdb;
        if (self::$poisoned) self::fail('coordination_request_poisoned');
        if (self::$held!==null) self::fail('coordination_reentrant');
        self::pin();
        $attempted=false; $got=null;
        try {
        // No caller transaction: a rollback must not erase a durable reservation.
        if ((string)self::scalar('SELECT @@in_transaction')!=='0' || (string)self::scalar('SELECT @@autocommit')!=='1') self::fail('coordination_caller_transaction');
        if ((string)self::scalar('SELECT @@innodb_flush_log_at_trx_commit')!=='1' || (string)self::scalar('SELECT @@read_only')!=='0') self::fail('coordination_durability_unsupported');
        $session=(string)self::scalar('SELECT CONNECTION_ID()');
        $database=(string)self::scalar('SELECT DATABASE()');
        if (!defined('DB_NAME') || $database!==DB_NAME || !ctype_digit($session)) self::fail('coordination_destination_unverified');
        $attempted=true;
        $got=(string)self::scalar($wpdb->prepare('SELECT GET_LOCK(%s, 0)',self::name()));
        if ($got==='0') self::fail('coordination_busy');
        if ($got!=='1') self::fail('coordination_read_failed');
        self::$held=array('mode'=>$mode,'session'=>$session,'wpdb'=>$wpdb,'prefix'=>$wpdb->prefix,'cap'=>self::capability());
        try { self::assert_owner(); } catch (Throwable $e) { self::poison(); throw $e; }
        } catch (Throwable $e) {
            if ($attempted && $got!=='0' && self::$held===null) self::poison();
            if (!self::$poisoned && self::$held===null) self::unpin();
            throw $e;
        }
    }
    public static function assert_owner() {
        global $wpdb;
        try {
            if (self::$poisoned || self::$held===null || self::$held['wpdb']!==$wpdb || self::$held['prefix']!==$wpdb->prefix || self::$held['cap']!==self::capability()) self::fail('coordination_ownership_lost');
            $session=(string)self::scalar('SELECT CONNECTION_ID()');
            if ((string)self::scalar('SELECT DATABASE()')!==DB_NAME) self::fail('coordination_ownership_lost');
            $owner=(string)self::scalar($wpdb->prepare('SELECT IS_USED_LOCK(%s)',self::name()));
            if ($session!==self::$held['session'] || $owner!==$session) self::fail('coordination_ownership_lost');
            if (get_class($wpdb)==='wpdb') {
                $retry=new ReflectionProperty('wpdb','reconnect_retries'); $retry->setAccessible(true);
                if ($retry->getValue($wpdb)!==0) self::fail('coordination_ownership_lost');
            }
            if (self::$held['mode']==='configuration' && ((string)self::scalar('SELECT @@in_transaction')!=='0' || (string)self::scalar('SELECT @@autocommit')!=='1')) self::fail('coordination_ownership_lost');
        } catch (Throwable $e) { self::poison(); self::fail('coordination_ownership_lost'); }
    }
    public static function release() {
        global $wpdb;
        if (self::$held===null) return false;
        try {
            self::assert_owner();
            $released=(string)self::scalar($wpdb->prepare('SELECT RELEASE_LOCK(%s)',self::name()));
            if ($released!=='1') self::fail('coordination_release_failed');
            self::$held=null;
            self::unpin();
            return true;
        } catch (Throwable $e) { self::$held=null; self::poison(); return false; }
    }
    private static function physical($table,$required,$primary=false) {
        global $wpdb;
        if (!preg_match('/^[A-Za-z0-9_]+$/',$table)) self::fail('coordination_identifier_unsupported');
        $engine=self::rows($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$table));
        if (count($engine)!==1 || strtolower($engine[0]['ENGINE'])!=='innodb') self::fail('coordination_schema_unsupported');
        $columns=self::rows("SHOW COLUMNS FROM `{$table}`");
        foreach ($columns as $column) {
            $type=preg_replace('/\b(tinyint|bigint|int)\([0-9]+\)/','$1',strtolower($column['Type']));
            if (isset($required[$column['Field']]) && $required[$column['Field']]===array($type,$column['Null'])) unset($required[$column['Field']]);
        }
        if ($required) self::fail('coordination_schema_unsupported');
        if ($primary) {
            $indexes=self::rows("SHOW INDEX FROM `{$table}` WHERE Key_name='PRIMARY'");
            if (count($indexes)!==1 || $indexes[0]['Column_name']!=='slot' || (int)$indexes[0]['Non_unique']!==0) self::fail('coordination_schema_unsupported');
        }
    }
    private static function guard_row() {
        self::physical(self::table(),array('slot'=>array('tinyint unsigned','NO'),'schema_version'=>array('varchar(8)','NO'),
            'destination'=>array('char(64)','NO'),'state'=>array('varchar(16)','NO'),'journal'=>array('longtext','NO')),true);
        $rows=self::rows('SELECT * FROM `'.self::table().'` LIMIT 2');
        if (count($rows)>1) self::fail('coordination_state_unsupported');
        if (!$rows) return null;
        $r=$rows[0];
        if ((string)$r['slot']!=='1' || $r['schema_version']!=='1' || $r['destination']!==self::capability()['destination'] || !in_array($r['state'],array('reserved','inflight','complete'),true) || strlen($r['journal'])>65536) self::fail('coordination_state_unsupported');
        $journal=json_decode($r['journal'],true);
        if (!is_array($journal) || !isset($journal['binding'],$journal['manifest'],$journal['receipts'],$journal['operation_id'],$journal['plan_hash'],$journal['history']) || !is_array($journal['history']) || count($journal['history'])>128 || !array_key_exists('inflight',$journal) || !array_key_exists('final_hash',$journal) || !self::hash($journal['binding']) || !self::id($journal['operation_id']) || !self::hash($journal['plan_hash']) || !is_array($journal['manifest']) || !$journal['manifest'] || !is_array($journal['receipts']) || count($journal['manifest'])>64) self::fail('coordination_state_unsupported');
        foreach ($journal['history'] as $id=>$hash) if (!self::id($id) || !self::hash($hash)) self::fail('coordination_state_unsupported');
        foreach ($journal['manifest'] as $id=>$step) {
            if (!self::id($id) || !is_array($step) || !isset($step['kind'],$step['intent_hash']) || !in_array($step['kind'],array('create','update','adopt','reuse'),true) || !self::hash($step['intent_hash'])) self::fail('coordination_state_unsupported');
        }
        foreach ($journal['receipts'] as $id=>$hash) if (!isset($journal['manifest'][$id]) || !self::hash($hash)) self::fail('coordination_state_unsupported');
        $expected=hash('sha256',json_encode(array(self::capability(),$journal['operation_id'],$journal['plan_hash'],$journal['manifest'])));
        if ($expected!==$journal['binding'] || ($journal['inflight']!==null && !isset($journal['manifest'][$journal['inflight']])) || (($r['state']==='inflight')!==($journal['inflight']!==null)) || ($r['state']==='complete' && (!self::hash($journal['final_hash']) || count($journal['manifest'])!==count($journal['receipts'])))) self::fail('coordination_state_unsupported');
        if (isset($journal['history'][$journal['operation_id']]) || ($r['state']!=='complete' && $journal['final_hash']!==null) || ($journal['inflight']!==null && isset($journal['receipts'][$journal['inflight']]))) self::fail('coordination_state_unsupported');
        $r['decoded']=$journal;
        return $r;
    }
    private static function pending_inquiries() {
        global $wpdb;
        $table=SiteEngine_Ads_Bridge_Inquiries::table_name();
        $required=array();
        foreach (explode("\n",SiteEngine_Ads_Bridge_Inquiries::schema_sql()) as $line) {
            if (preg_match('/^\s*([a-z_]+)\s+(bigint\(20\) unsigned|varchar\([0-9]+\)|char\([0-9]+\)|longtext|datetime|date|decimal\(20,6\))\s+(NOT NULL|NULL)/',$line,$m)) {
                $type=preg_replace('/bigint\(20\)/','bigint',$m[2]);
                $required[$m[1]]=array($type,$m[3]==='NULL'?'YES':'NO');
            }
        }
        self::physical($table,$required);
        $options=$wpdb->prefix.'options';
        if ((string)self::scalar("SELECT option_value FROM `{$options}` WHERE option_name='seab_inquiry_schema_version' LIMIT 1")!=='2') self::fail('coordination_schema_unsupported');
        $rows=self::rows("SELECT state, contract_version, COUNT(*) AS n FROM `{$table}` GROUP BY state, contract_version");
        $pending=false;
        foreach ($rows as $row) {
            if (!isset($row['state'],$row['contract_version'],$row['n']) || !ctype_digit((string)$row['n']) || !in_array($row['state'],array('active','archived','prepared','activating'),true) || $row['contract_version']!=='1') self::fail('coordination_state_unsupported');
            if (in_array($row['state'],array('prepared','activating'),true) && (int)$row['n']>0) $pending=true;
        }
        return $pending;
    }
    public static function inspect($request) {
        $out=self::capability()+array('state'=>'unknown','complete'=>false,'reason_code'=>'coordination_inspection_failed','write_permission'=>false);
        $acquired=false;
        try {
            self::matches($request); self::acquire('inspect'); $acquired=true;
            $row=self::guard_row();
            $pending=self::pending_inquiries();
            self::assert_owner();
            $out['state']=$pending || ($row && $row['state']!=='complete')?'present':'none';
            $out['complete']=true; $out['reason_code']=$out['state']==='none'?'complete_empty':'pending_reconciliation_or_configuration';
        } catch (Throwable $e) { $out['reason_code']=self::reason($e); }
        if ($acquired && !self::release()) { $out['state']='unknown'; $out['complete']=false; $out['reason_code']='coordination_release_failed'; }
        return $out;
    }
    private static function reason($e) {
        $reason=$e->getMessage();
        $allowed=array('coordination_read_failed','coordination_persistence_failed','coordination_identity_mismatch','coordination_request_poisoned','coordination_reentrant','coordination_caller_transaction','coordination_durability_unsupported','coordination_destination_unverified','coordination_busy','coordination_ownership_lost','coordination_release_failed','coordination_identifier_unsupported','coordination_schema_unsupported','coordination_state_unsupported','coordination_configuration_recovery_required','coordination_journal_limit','coordination_journal_unconfirmed','coordination_request_invalid','coordination_pending_inquiry','coordination_binding_mismatch','coordination_batch_incomplete','coordination_wrong_owner','coordination_step_invalid','coordination_inflight_requires_readback','coordination_readback_drift','coordination_uncertain_save','coordination_final_readback_failed','coordination_driver_unsupported','coordination_operation_retired','coordination_operation_conflict');
        return in_array($reason,$allowed,true)?$reason:'coordination_operation_failed';
    }
    public static function enter_reconciliation() {
        $acquired=false;
        try {
            self::acquire('reconciliation'); $acquired=true;
            $row=self::guard_row();
            if ($row && $row['state']!=='complete') self::fail('coordination_configuration_recovery_required');
            self::assert_owner();
            return array('ok'=>true,'reason'=>'');
        } catch (Throwable $e) {
            $reason=self::reason($e);
            if ($acquired && !self::release()) $reason='coordination_release_failed';
            return array('ok'=>false,'reason'=>$reason);
        }
    }
    private static function persist($state,$journal) {
        global $wpdb;
        self::assert_owner();
        $json=json_encode($journal,JSON_UNESCAPED_SLASHES);
        if ($json===false || strlen($json)>65536) self::fail('coordination_journal_limit');
        $table=self::table(); $cap=self::capability();
        self::sql($wpdb->prepare("INSERT INTO `{$table}` (slot,schema_version,destination,state,journal) VALUES (1,'1',%s,%s,%s) ON DUPLICATE KEY UPDATE state=VALUES(state),journal=VALUES(journal)",$cap['destination'],$state,$json));
        $row=self::guard_row();
        if (!$row || $row['state']!==$state || $row['journal']!==$json) self::fail('coordination_journal_unconfirmed');
        self::assert_owner();
    }
    public static function batch($request,$callback) {
        $capability=self::capability();
        $acquired=false; $result=array('status'=>'blocked','reason_code'=>'coordination_operation_failed');
        try {
            self::matches($request);
            if (!is_callable($callback) || !isset($request['operation_id'],$request['plan_hash'],$request['steps']) || !self::id($request['operation_id']) || !self::hash($request['plan_hash']) || !is_array($request['steps']) || !$request['steps'] || count($request['steps'])>64) self::fail('coordination_request_invalid');
            $manifest=$request['steps']; ksort($manifest,SORT_STRING);
            foreach ($manifest as $id=>$step) {
                if (!self::id($id) || !is_array($step) || count($step)!==2 || !isset($step['kind'],$step['intent_hash']) || !in_array($step['kind'],array('create','update','adopt','reuse'),true) || !self::hash($step['intent_hash'])) self::fail('coordination_request_invalid');
                $manifest[$id]=array('kind'=>$step['kind'],'intent_hash'=>$step['intent_hash']);
            }
            $binding=hash('sha256',json_encode(array(self::capability(),$request['operation_id'],$request['plan_hash'],$manifest)));
            self::acquire('configuration'); $acquired=true;
            $row=self::guard_row();
            if (self::pending_inquiries()) self::fail('coordination_pending_inquiry');
            if ($row && $row['decoded']['binding']!==$binding && $row['state']!=='complete') self::fail('coordination_configuration_recovery_required');
            if ($row && $row['decoded']['operation_id']===$request['operation_id'] && $row['decoded']['binding']!==$binding) self::fail('coordination_operation_conflict');
            $history=$row?$row['decoded']['history']:array();
            if (isset($history[$request['operation_id']])) self::fail('coordination_operation_retired');
            if ($row && $row['decoded']['binding']!==$binding) $history[$row['decoded']['operation_id']]=$row['decoded']['binding'];
            if (count($history)>128) self::fail('coordination_journal_limit');
            $journal=$row && $row['decoded']['binding']===$binding ? $row['decoded'] : array('binding'=>$binding,'operation_id'=>$request['operation_id'],'plan_hash'=>$request['plan_hash'],'manifest'=>$manifest,'receipts'=>array(),'inflight'=>null,'final_hash'=>null,'history'=>$history);
            if ($journal['manifest']!==$manifest) self::fail('coordination_binding_mismatch');
            $batch=new SiteEngine_Ads_Bridge_Configuration_Batch($binding);
            // Even completed replay must repeat final readback under protection.
            if (!$row || $row['decoded']['binding']!==$binding) self::persist('reserved',$journal);
            call_user_func($callback,$batch);
            $row=self::guard_row();
            if (!$row || $row['decoded']['binding']!==$binding || $row['state']!=='complete' || !$batch->finished()) self::fail('coordination_batch_incomplete');
            self::assert_owner();
            $result=array('status'=>'complete','reason_code'=>'coordination_readback_checkpointed');
        } catch (Throwable $e) { $result=array('status'=>'blocked','reason_code'=>self::reason($e)); }
        if ($acquired && !self::release()) $result=array('status'=>'blocked','reason_code'=>'coordination_release_failed');
        return $capability+$result;
    }
    private static function id($id) { return is_string($id) && !ctype_digit($id) && preg_match('/^[A-Za-z0-9_.:-]{1,80}$/D',$id); }
    private static function hash($hash) { return is_string($hash) && preg_match('/^[a-f0-9]{64}$/D',$hash); }
    private static function current($binding) {
        self::assert_owner();
        if (self::$held['mode']!=='configuration') self::fail('coordination_wrong_owner');
        $row=self::guard_row();
        if (!$row || $row['decoded']['binding']!==$binding) self::fail('coordination_binding_mismatch');
        return $row['decoded'];
    }
    public static function step($binding,$id,$write,$readback) {
        $j=self::current($binding);
        if (!isset($j['manifest'][$id]) || !is_callable($write) || !is_callable($readback)) self::fail('coordination_step_invalid');
        // Completed earlier steps may be verified while a later step is uncertain.
        // Their receipt still requires exact readback; no write or intent is cleared.
        if ($j['inflight']!==null && $j['inflight']!==$id && !isset($j['receipts'][$id])) self::fail('coordination_inflight_requires_readback');
        if (isset($j['receipts'][$id]) || $j['inflight']===$id) {
            self::assert_owner(); $observed=call_user_func($readback); self::assert_owner();
            if (is_array($observed) && ($observed['state']??'')==='verified' && self::hash($observed['hash']??null)) {
                if (isset($j['receipts'][$id]) && $j['receipts'][$id]!==$observed['hash']) self::fail('coordination_readback_drift');
                if (isset($j['receipts'][$id])) return;
                $j['receipts'][$id]=$observed['hash']; $j['inflight']=null;
                self::persist('reserved',$j); return;
            }
            // Only exact update + proven unchanged-before state can be resaved.
            // Unknown create/adopt/reuse outcomes are never submitted again.
            if (isset($j['receipts'][$id]) || $j['manifest'][$id]['kind']!=='update' || !is_array($observed) || ($observed['state']??'')!=='before') self::fail('coordination_uncertain_save');
        }
        $j['inflight']=$id; self::persist('inflight',$j);
        self::assert_owner(); call_user_func($write); self::assert_owner();
        $observed=call_user_func($readback); self::assert_owner();
        if (!is_array($observed) || ($observed['state']??'')!=='verified' || !self::hash($observed['hash']??null)) self::fail('coordination_uncertain_save');
        $j['receipts'][$id]=$observed['hash']; $j['inflight']=null;
        self::persist('reserved',$j);
    }
    public static function finish($binding,$readback) {
        $j=self::current($binding);
        if ($j['inflight']!==null || count($j['receipts'])!==count($j['manifest']) || !is_callable($readback)) self::fail('coordination_batch_incomplete');
        foreach ($j['manifest'] as $id=>$ignored) if (!isset($j['receipts'][$id])) self::fail('coordination_batch_incomplete');
        self::assert_owner(); $hash=call_user_func($readback); self::assert_owner();
        if (!self::hash($hash) || ($j['final_hash']!==null && $j['final_hash']!==$hash)) self::fail('coordination_final_readback_failed');
        $j['final_hash']=$hash; self::persist('complete',$j);
    }
}

/** Request-local handle: never serialize or reuse in another WP-CLI invocation. */
final class SiteEngine_Ads_Bridge_Configuration_Batch {
    private $binding;
    private $finished=false;
    public function __construct($binding) { $this->binding=$binding; }
    public function step($id,$write,$readback) {
        if ($this->finished) throw new RuntimeException('coordination_batch_finished');
        SiteEngine_Ads_Bridge_Coordination::step($this->binding,$id,$write,$readback);
    }
    public function finish($readback) {
        SiteEngine_Ads_Bridge_Coordination::finish($this->binding,$readback); $this->finished=true;
    }
    public function finished() { return $this->finished; }
}
