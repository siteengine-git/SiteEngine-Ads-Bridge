<?php
/** Opt-in LOCAL dedicated MariaDB only; two independent real PDO sessions.
 * Same opt-in variables as inquiry-database-acceptance.php. Never WordPress DB.
 */
if (PHP_SAPI!=='cli' || getenv('SEAB_TEST_ALLOW_DB_WRITES')!=='1' || !getenv('SEAB_TEST_DSN')) {
    echo "SKIP: dedicated local MariaDB seab_inquiry_acceptance opt-in unavailable.\n"; exit(0);
}
if (!extension_loaded('pdo_mysql')) { fwrite(STDERR,"PDO mysql required\n"); exit(1); }
define('ABSPATH',__DIR__.'/'); define('ARRAY_A','ARRAY_A'); define('DB_NAME','seab_inquiry_acceptance');
require dirname(__DIR__).'/includes/class-siteengine-ads-bridge-coordination.php';
require dirname(__DIR__).'/includes/class-siteengine-ads-bridge-inquiries.php';
final class SEAB_Coordination_PDO implements SiteEngine_Ads_Bridge_Coordination_Driver {
    public $prefix; public $last_error=''; public $pdo;
    public function __construct($pdo,$prefix) { $this->pdo=$pdo; $this->prefix=$prefix; }
    public function seab_coordination_pin_session() { return static function() {}; } // PDO has no reconnect/replay.
    public function prepare($sql,...$args) {
        $i=0; return preg_replace_callback('/%[sd]/',function($m)use(&$i,$args){$v=$args[$i++];return $m[0]==='%d'?(string)(int)$v:$this->pdo->quote((string)$v);},$sql);
    }
    public function get_charset_collate() { return 'ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'; }
    public function get_var($sql) {
        $this->last_error='';
        try { $value=$this->pdo->query($sql)->fetchColumn(); return $value===false?null:$value; }
        catch (Throwable $e) { $this->last_error='native query failed'; return null; }
    }
    public function get_results($sql,$format=null) {
        $this->last_error='';
        try { return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC); }
        catch (Throwable $e) { $this->last_error='native query failed'; return null; }
    }
    public function query($sql) {
        $this->last_error='';
        try { return $this->pdo->exec($sql); }
        catch (Throwable $e) { $this->last_error='native query failed'; return false; }
    }
    public function close() { $this->pdo=null; }
}
$dsn=getenv('SEAB_TEST_DSN');
// Localhost only, not an SSH/tunnel/website validation route.
if (!preg_match('/(?:^mysql:|;)host=(?:127\.0\.0\.1|localhost)(?:;|$)/',$dsn)) { fwrite(STDERR,"Local DSN required\n"); exit(1); }
try {
    $one=new PDO($dsn,getenv('SEAB_TEST_DB_USER')?:'',getenv('SEAB_TEST_DB_PASSWORD')?:'',array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
    $two=new PDO($dsn,getenv('SEAB_TEST_DB_USER')?:'',getenv('SEAB_TEST_DB_PASSWORD')?:'',array(PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION));
} catch (Throwable $e) { fwrite(STDERR,"Local dedicated database connection unavailable; details withheld.\n"); exit(1); }
foreach (array($one,$two) as $pdo) if ($pdo->query('SELECT DATABASE()')->fetchColumn()!==DB_NAME) { fwrite(STDERR,"Dedicated database mismatch\n");exit(1); }
$prefix='seab_coord_'.bin2hex(random_bytes(6)).'_'; $wpdb=new SEAB_Coordination_PDO($one,$prefix);
$tables=array($prefix.'se_ads_configuration_guard',$prefix.'se_ads_inquiries',$prefix.'options');
$pass=0;
function native_guard_check($condition,$message) { global $pass; if (!$condition) throw new RuntimeException($message); $pass++; echo "PASS: {$message}\n"; }
try {
    $one->exec(SiteEngine_Ads_Bridge_Coordination::schema_sql());
    $one->exec(SiteEngine_Ads_Bridge_Inquiries::schema_sql());
    $one->exec('CREATE TABLE `'.$prefix.'options` (option_name varchar(191) PRIMARY KEY, option_value longtext NOT NULL) ENGINE=InnoDB');
    $one->exec("INSERT INTO `{$prefix}options` VALUES ('seab_inquiry_schema_version','2')");
    $cap=SiteEngine_Ads_Bridge_Coordination::capability(); $lock='seab_c_'.substr($cap['destination'],0,48);
    native_guard_check(SiteEngine_Ads_Bridge_Coordination::inspect($cap)['state']==='none','physical SQL inspection of complete empty destination');
    $two->query('SELECT GET_LOCK('.$two->quote($lock).',0)')->fetchColumn();
    native_guard_check(!SiteEngine_Ads_Bridge_Coordination::inspect($cap)['complete'],'independent SQL owner prevents protected inspection');
    $two->query('SELECT RELEASE_LOCK('.$two->quote($lock).')')->fetchColumn();
    $request=$cap+array('operation_id'=>'native_operation','plan_hash'=>str_repeat('a',64),'steps'=>array('field.one'=>array('kind'=>'create','intent_hash'=>str_repeat('b',64))));
    $writes=0;
    $result=SiteEngine_Ads_Bridge_Coordination::batch($request,function($batch)use($two,$lock,&$writes){
        native_guard_check((string)$two->query('SELECT GET_LOCK('.$two->quote($lock).',0)')->fetchColumn()==='0','independent session excluded before vendor save');
        $batch->step('field.one',function()use(&$writes){$writes++;throw new RuntimeException('lost response');},static function(){return array('state'=>'verified','hash'=>str_repeat('c',64));});
    });
    native_guard_check($result['status']==='blocked' && $writes===1,'native durable inflight journal survives lost vendor response');
    native_guard_check(SiteEngine_Ads_Bridge_Coordination::inspect($cap)['state']==='present','persistent reservation remains after advisory lock release');
    native_guard_check(!SiteEngine_Ads_Bridge_Coordination::enter_reconciliation()['ok'],'reconciliation cannot resume against uncertain configuration');
    $result=SiteEngine_Ads_Bridge_Coordination::batch($request,function($batch)use($two,$lock,&$writes){
        $batch->step('field.one',function()use(&$writes){$writes++;},static function(){return array('state'=>'verified','hash'=>str_repeat('c',64));});
        $batch->finish(function()use($two,$lock){
            native_guard_check((string)$two->query('SELECT GET_LOCK('.$two->quote($lock).',0)')->fetchColumn()==='0','independent session excluded through final readback and checkpoint');
            return str_repeat('d',64);
        });
    });
    native_guard_check($result['status']==='complete' && $writes===1,'exact SQL recovery checkpoints without a duplicate vendor create');
    native_guard_check(SiteEngine_Ads_Bridge_Coordination::inspect($cap)['state']==='none','completed durable state clears pending observation');
    echo "SUMMARY: {$pass} native PASS\n";
} finally {
    // Only this run's random-prefix test tables, never a WordPress table.
    foreach ($tables as $table) $two->exec('DROP TABLE IF EXISTS `'.$table.'`');
}
