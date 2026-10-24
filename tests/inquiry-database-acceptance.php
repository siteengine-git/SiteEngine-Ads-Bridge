<?php
/**
 * Opt-in MariaDB/MySQL integration. NEVER point this at a WordPress database.
 * Requires PDO mysql and a dedicated database named seab_inquiry_acceptance.
 * Set SEAB_TEST_DSN, SEAB_TEST_DB_USER, SEAB_TEST_DB_PASSWORD and
 * SEAB_TEST_ALLOW_DB_WRITES=1. Without opt-in this test exits without connecting.
 * Subscriber/CRM calls remain simulated; inquiry storage and locks use SQL.
 */
if ('cli' !== PHP_SAPI || '1' !== getenv('SEAB_TEST_ALLOW_DB_WRITES') || !getenv('SEAB_TEST_DSN')) {
    echo "SKIP: explicit dedicated-database opt-in is required.\n";
    exit(0);
}
if (!class_exists('PDO') || !in_array('mysql',PDO::getAvailableDrivers(),true)) {
    fwrite(STDERR,"PDO mysql is required.\n"); exit(1);
}
define('DB_NAME','seab_inquiry_acceptance');
$dsn=getenv('SEAB_TEST_DSN'); $user=getenv('SEAB_TEST_DB_USER') ?: ''; $password=getenv('SEAB_TEST_DB_PASSWORD') ?: '';
$pdo=new PDO($dsn,$user,$password,array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
$observer=new PDO($dsn,$user,$password,array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
if ('seab_inquiry_acceptance' !== $pdo->query('SELECT DATABASE()')->fetchColumn() || 'seab_inquiry_acceptance' !== $observer->query('SELECT DATABASE()')->fetchColumn()) {
    fwrite(STDERR,"Refusing a database other than seab_inquiry_acceptance.\n"); exit(1);
}
define('SEAB_INQUIRY_TEST_INCLUDE',true);
ob_start(); require __DIR__.'/inquiry-acceptance.php'; $bootstrap_log=ob_get_clean();
if ($fails) { echo $bootstrap_log; exit(1); }
$passes=0; $fails=0;

final class SEAB_SQL_Test_WPDB {
    public $prefix;
    public $pdo;
    public $submissions=array();
    public $last_error='';
    public $fail_activation=false;
    public $fail_checkpoint=false;
    public function __construct($pdo,$prefix) { $this->pdo=$pdo; $this->prefix=$prefix; }
    public function get_charset_collate() { return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'; }
    public function prepare($query,...$args) {
        if (1===count($args) && is_array($args[0])) $args=$args[0];
        $i=0;
        return preg_replace_callback('/%[dsf]/',function($m)use(&$i,$args){
            $v=$args[$i++];
            return '%s'===$m[0] ? $this->pdo->quote((string)$v) : ( '%d'===$m[0] ? (string)(int)$v : (string)(float)$v );
        },$query);
    }
    public function get_var($sql) {
        $this->last_error='';
        try { $value=$this->pdo->query($sql)->fetchColumn(); return false===$value?null:$value; }
        catch(Throwable $e) { $this->last_error=$e->getMessage(); return null; }
    }
    public function get_row($sql,$format=null) {
        $this->last_error='';
        try { $row=$this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC); return false===$row?null:$row; }
        catch(Throwable $e) { $this->last_error=$e->getMessage(); return null; }
    }
    public function get_results($sql,$format=null) {
        $this->last_error='';
        try { return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC); }
        catch(Throwable $e) { $this->last_error=$e->getMessage(); return null; }
    }
    public function insert($table,$data,$formats=null) {
        $this->last_error='';
        try {
            $columns=implode(',',array_map(fn($key)=>'`'.$key.'`',array_keys($data)));
            $statement=$this->pdo->prepare('INSERT INTO `'.$table.'` ('.$columns.') VALUES ('.implode(',',array_fill(0,count($data),'?')).')');
            $statement->execute(array_values($data)); return $statement->rowCount();
        } catch(Throwable $e) { $this->last_error=$e->getMessage(); return false; }
    }
    public function update($table,$data,$where,$formats=null,$where_formats=null) {
        if ($this->fail_activation && 'active'===($data['state']??'')) return 0;
        if ($this->fail_checkpoint && !empty($data['projection_verified_at_utc'])) return 0;
        $this->last_error='';
        try {
            $set=implode(',',array_map(fn($key)=>'`'.$key.'`=?',array_keys($data)));
            $filter=implode(' AND ',array_map(fn($key)=>'`'.$key.'`'.(null===$where[$key]?' IS NULL':'=?'),array_keys($where)));
            $where_values=array_values(array_filter($where,fn($v)=>null!==$v));
            $statement=$this->pdo->prepare('UPDATE `'.$table.'` SET '.$set.' WHERE '.$filter);
            $statement->execute(array_merge(array_values($data),$where_values)); return $statement->rowCount();
        } catch(Throwable $e) { $this->last_error=$e->getMessage(); return false; }
    }
    public function query($sql) {
        $this->last_error='';
        try { return $this->pdo->exec($sql); }
        catch(Throwable $e) { $this->last_error=$e->getMessage(); return false; }
    }
    public function close() { $this->pdo=null; return true; }
}

$prefix='seab_acceptance_'.bin2hex(random_bytes(6)).'_';
$tables=array($prefix.'se_ads_inquiries',$prefix.'fluentform_submissions',$prefix.'fc_tags');
$GLOBALS['wpdb']=new SEAB_SQL_Test_WPDB($pdo,$prefix);
function native_case($id) {
    global $wpdb;
    $entry=$id*10;
    $wpdb->query('INSERT INTO `'.$wpdb->prefix.'fluentform_submissions` (id,form_id) VALUES ('.$entry.',3),('.($entry+1).',3)');
    return reset_case($id);
}
function native_lock_name($id) {
    global $wpdb;
    return 'seab_i_'.substr(hash('sha256',DB_NAME.'|'.$wpdb->prefix.'|'.$id),0,48);
}
try {
    $pdo->exec('SET SESSION default_storage_engine=InnoDB');
    // Exercise the additive SQL column upgrade independently of WordPress dbDelta.
    $legacy_ddl=preg_replace('/^\s*projection_(?:plan longtext|plan_hash char\(64\)|verified_at_utc datetime) NULL,\n/m','',SiteEngine_Ads_Bridge_Inquiries::schema_sql());
    $pdo->exec($legacy_ddl);
    $legacy_id=uuid_for(999999);
    $pdo->exec('INSERT INTO `'.$tables[0].'` (inquiry_id,contact_id,state,transaction_id,invoiced_amount,created_at_utc,updated_at_utc) VALUES ('.$pdo->quote($legacy_id).",9999,'archived','ff:3:99999',4500,'2026-10-20 12:00:00','2026-10-20 12:00:00')");
    check_inq(!SiteEngine_Ads_Bridge_Inquiries::schema_ready(),'native schema-one table is rejected despite a newer recorded option');
    $pdo->exec('ALTER TABLE `'.$tables[0].'` ADD projection_plan LONGTEXT NULL, ADD projection_plan_hash CHAR(64) NULL, ADD projection_verified_at_utc DATETIME NULL');
    $legacy=$pdo->query('SELECT * FROM `'.$tables[0].'` WHERE inquiry_id='.$pdo->quote($legacy_id))->fetch(PDO::FETCH_ASSOC);
    check_inq(SiteEngine_Ads_Bridge_Inquiries::schema_ready() && 'archived'===$legacy['state'] && 'ff:3:99999'===$legacy['transaction_id'] && 4500===(int)$legacy['invoiced_amount'] && null===$legacy['projection_plan'] && null===$legacy['projection_verified_at_utc'],'native additive column upgrade preserves prior attribution rows and initializes empty checkpoints');
    $pdo->exec('DELETE FROM `'.$tables[0].'` WHERE inquiry_id='.$pdo->quote($legacy_id));
    $pdo->exec('CREATE TABLE `'.$tables[1].'` (id BIGINT UNSIGNED PRIMARY KEY, form_id BIGINT UNSIGNED NOT NULL) ENGINE=InnoDB');
    $pdo->exec('CREATE TABLE `'.$tables[2].'` (id BIGINT UNSIGNED PRIMARY KEY, title VARCHAR(191) NOT NULL) ENGINE=InnoDB');
    $pdo->exec('INSERT INTO `'.$tables[2]."` (id,title) VALUES (101,'New'),(102,'Qualified'),(103,'Unqualified'),(104,'Won')");

    [$contact,$old,$new,$activation]=native_case(1000);
    check_inq('prepared'===SiteEngine_Ads_Bridge_Inquiries::get($new)['state'],'native SQL stores adoption and prepared inquiry with canonical unique bindings');
    $lock=native_lock_name(1000);
    check_inq(1===(int)$observer->query('SELECT GET_LOCK('.$observer->quote($lock).',0)')->fetchColumn(),'an independent connection can hold the contact lock');
    $calls=$contact->field_write_calls;
    $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
    check_inq('retry'===$result['status'] && 'contact_transition_busy'===$result['reason_code'] && $calls===$contact->field_write_calls,'actual cross-connection contention prevents source API activation and CRM reset');
    $observer->query('SELECT RELEASE_LOCK('.$observer->quote($lock).')');
    $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
    check_inq('committed'===$result['status'] && 'archived'===SiteEngine_Ads_Bridge_Inquiries::get($old)['state'] && 'active'===SiteEngine_Ads_Bridge_Inquiries::get($new)['state'],'source API commits the old/new inquiry switch through real InnoDB');
    check_inq(null===$observer->query('SELECT IS_USED_LOCK('.$observer->quote($lock).')')->fetchColumn(),'source API releases its native contact lock after commit');

    [$contact,$old,$new,$activation]=native_case(1001);
    $other=prep_for_native(1001,$old,uuid_for(990001));
    $result=SiteEngine_Ads_Bridge_Inquiries::prepare_reconciliation($other);
    check_inq('retry'===$result['status'] && 'pending_reconciliation_exists'===$result['reason_code'],'native pending reservation rejects a competing prepare');
    $GLOBALS['wpdb']->fail_activation=true;
    $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
    $old_state=$observer->query('SELECT state FROM `'.$tables[0].'` WHERE inquiry_id='.$observer->quote($old))->fetchColumn();
    $new_state=$observer->query('SELECT state FROM `'.$tables[0].'` WHERE inquiry_id='.$observer->quote($new))->fetchColumn();
    check_inq('retry'===$result['status'] && 'active'===$old_state && 'activating'===$new_state,'real SQL rollback restores the old pointer after injected new-pointer failure');
    $saved_plan=$observer->query('SELECT projection_plan,projection_plan_hash,projection_verified_at_utc FROM `'.$tables[0].'` WHERE inquiry_id='.$observer->quote($new))->fetch(PDO::FETCH_ASSOC);
    check_inq(!empty($saved_plan['projection_verified_at_utc']) && hash('sha256',$saved_plan['projection_plan'])===$saved_plan['projection_plan_hash'],'an independent SQL connection observes the committed reset plan and completion checkpoint after pointer rollback');
    $fields=$contact->field_write_calls; $tags=$contact->tag_write_calls;
    $GLOBALS['wpdb']->fail_activation=false;
    $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
    check_inq('committed'===$result['status'] && $fields===$contact->field_write_calls && $tags===$contact->tag_write_calls,'native retry completes the rolled-back pointer transition without repeating the CRM reset');

    [$contact,$old,$new,$activation]=native_case(1004);
    $GLOBALS['wpdb']->fail_checkpoint=true;
    $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
    $fields=$contact->field_write_calls; $tags=$contact->tag_write_calls;
    $pending=$observer->query('SELECT state,projection_plan,projection_verified_at_utc FROM `'.$tables[0].'` WHERE inquiry_id='.$observer->quote($new))->fetch(PDO::FETCH_ASSOC);
    check_inq('retry'===$result['status'] && 'activating'===$pending['state'] && !empty($pending['projection_plan']) && null===$pending['projection_verified_at_utc'],'native failed checkpoint leaves the durable original plan and an uncommitted inquiry head');
    $GLOBALS['wpdb']->fail_checkpoint=false;
    $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
    check_inq('committed'===$result['status'] && $fields===$contact->field_write_calls && $tags===$contact->tag_write_calls,'native retry reconstructs reset completion without repeating CRM writes');

    [$contact,$old,$new,$activation]=native_case(1005);
    $contact->throw_after_field_keys=3;
    $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
    $contact->throw_after_field_keys=0;
    $checkpoint=$observer->query('SELECT projection_verified_at_utc FROM `'.$tables[0].'` WHERE inquiry_id='.$observer->quote($new))->fetchColumn();
    check_inq('retry'===$result['status'] && null===$checkpoint,'native storage preserves the unverified plan after a partial CRM interruption');
    $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
    check_inq('committed'===$result['status'],'native recovery completes the remaining partial reset and commits its checkpoint');

    [$contact,$old,$new,$activation]=native_case(1002);
    $lock=native_lock_name(1002);
    $observer->query('SELECT GET_LOCK('.$observer->quote($lock).',0)');
    [$independent,$i_old,$i_new,$i_activation]=native_case(1003);
    $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($i_activation);
    check_inq('committed'===$result['status'],'one contact lock does not block a different contact');
    $observer->query('SELECT RELEASE_LOCK('.$observer->quote($lock).')');

    // End the database session during projection. Its connection-scoped lock
    // must disappear and no committed acknowledgement may be returned.
    $connection=(int)$pdo->query('SELECT CONNECTION_ID()')->fetchColumn();
    $contact->on_field_write=function()use($observer,$connection){ $observer->exec('KILL CONNECTION '.$connection); };
    $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
    check_inq('retry'===$result['status'],'loss of the real lock-owning session cannot acknowledge activation');
    check_inq(null===$observer->query('SELECT IS_USED_LOCK('.$observer->quote($lock).')')->fetchColumn(),'terminating the owner session releases its native database lock');
    $old_state=$observer->query('SELECT state FROM `'.$tables[0].'` WHERE inquiry_id='.$observer->quote($old))->fetchColumn();
    check_inq('active'===$old_state,'owner-session loss preserves the prior durable inquiry history');
    $pending=$observer->query('SELECT projection_plan,projection_verified_at_utc FROM `'.$tables[0].'` WHERE inquiry_id='.$observer->quote($new))->fetch(PDO::FETCH_ASSOC);
    check_inq(!empty($pending['projection_plan']) && null===$pending['projection_verified_at_utc'],'the original reset plan survives loss of its owning SQL session');
    $replacement=new PDO($dsn,$user,$password,array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
    $GLOBALS['wpdb']=new SEAB_SQL_Test_WPDB($replacement,$prefix);
    $poison=new ReflectionProperty(SiteEngine_Ads_Bridge_Inquiries::class,'connection_unusable');
    $poison->setAccessible(true); $poison->setValue(null,false);
    $fields=$contact->field_write_calls;
    $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
    check_inq('committed'===$result['status'] && $fields===$contact->field_write_calls,'a replacement database session resumes the same activation without repeating completed field changes');
} finally {
    foreach (array_reverse($tables) as $table) $observer->exec('DROP TABLE IF EXISTS `'.$table.'`');
}
echo "\nSUMMARY: {$passes} PASS, {$fails} FAIL\n";
exit($fails?1:0);

function prep_for_native($id,$old,$new) {
    return array('contract_version'=>'1','event_id'=>'evt_compete_'.$id,'site_instance_id'=>'site-test','contact_id'=>$id,'previous_inquiry_id'=>$old,'proposed_inquiry_id'=>$new,'expected_head_version'=>1,'request_id'=>'req_compete_'.$id,'source'=>array('type'=>'fluent_form','form_id'=>3,'entry_id'=>$id*10+1));
}
