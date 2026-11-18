<?php
use function SCJTargetFixture\scj_inspect;
use function SCJTargetFixture\scj_owned;
use function SCJTargetFixture\scj_with_fault;
/** Focused local tooling tests. Synthetic SQL boundary; no WordPress/network/site execution. */
define('ABSPATH', '/home/alma.siteengine.space/public_html/'); define('ARRAY_A', 'ARRAY_A'); define('DAY_IN_SECONDS', 86400);
define('SCJ_LIBRARY_ONLY', true); define('SE_CONSENT_POLICY_VERSION', '2026-05-08-v1');
function home_url($path = '') { return 'http://alma.siteengine.space' . $path; }
function wp_json_encode($x) { return json_encode($x, JSON_UNESCAPED_SLASHES); }
function wp_salt($type) { return 'synthetic-joint-test-only-' . $type; }
function sanitize_text_field($s) { return trim(strip_tags($s)); }
function esc_url_raw($s) { return $s; }
class WP_Error {
    public $code;
    public function __construct($code, $message, $data = []) { $this->code = $code; }
}
function is_wp_error($v) { return $v instanceof WP_Error; }
set_error_handler(function ($level,$message) { throw new ErrorException($message,0,$level); });
require __DIR__ . '/fixtures/continuity-db.php';
require dirname(__DIR__) . '/includes/class-siteengine-consent-continuity.php';
require dirname(__DIR__) . '/includes/class-siteengine-consent-authority.php';
require dirname(__DIR__) . '/tools/acceptance/consent-joint.php';
$ads = $argv[1] ?? '';
scj_require(is_file($ads) && hash_file('sha256',$ads) === 'd8e10f7cdb0a1239cd0711b72ca0a163843d5d2e216b93de63061796f197c8a0', 'provide pinned Ads 2846305 consent helper');
require $ads;
$wpdb = new Receipt_Fixture_DB();
$wpdb->prefix = 'wp_'; // Synthetic fixture target, not an installed database connection.
$authority = new SiteEngine_Consent_Authority(['cookie_days'=>390,'policy_version'=>SE_CONSENT_POLICY_VERSION,'banner_version'=>'synthetic-banner']);
$stamps = []; $ack = null;
foreach (['A'=>true,'B'=>false,'C'=>true] as $label=>$grant) {
    $n = count($stamps)+1;
    $ack = $authority->save(['event_id'=>sprintf('12345678-1234-4123-8123-%012d',$n), 'continuity_key'=>hash('sha256','synthetic-one-browser'),
        'continuity_token'=>$ack['continuity']['token'] ?? '', 'policy_version'=>SE_CONSENT_POLICY_VERSION,'banner_version'=>'synthetic-banner',
        'consent'=>['analytics'=>false,'advertising'=>$grant,'personalization'=>$label==='C']], '', '');
    scj_require(!is_wp_error($ack),'seed capture failed'); $stamps[$label] = SiteEngine_Consent_Authority::seal_stamp($authority->resolve($ack),3);
}
final class Joint_Read_DB {
    public $prefix='wp_', $last_error='', $entries=[], $seed, $queries=[];
    public function __construct($seed) { $this->seed=$seed; }
    public function prepare($sql,...$args) {
        foreach($args as $arg) $sql=preg_replace_callback('/%[sd]/',function($m)use($arg){return $m[0]==='%d'?(string)(int)$arg:"'".str_replace("'","''",(string)$arg)."'";},$sql,1);
        return $sql;
    }
    public function get_row($sql,$format=null) {
        $this->queries[]=$sql; $this->last_error='';
        if(strpos($sql,'se_consent_chains')!==false) return $this->seed->heads[array_key_first($this->seed->heads)];
        preg_match('/(?:receipt_row_id|id)\s*=\s*(\d+)/',$sql,$m); $id=(int)($m[1]??0);
        if(strpos($sql,'fluentform_submissions')!==false)return $this->entries[$id]??null;
        if(strpos($sql,'se_consent_events')!==false)return $this->seed->links[$id]??null;
        if(strpos($sql,'se_consent_receipts')!==false)return $this->seed->rows[$id]??null;
        throw new RuntimeException('unexpected local row SQL');
    }
    public function get_results($sql,$format=null) {
        $this->queries[]=$sql; $this->last_error='';
        preg_match("/receipt_id\s*=\s*'([^']+)'/",$sql,$m); preg_match('/OR id\s*=\s*(\d+)/',$sql,$n);
        return array_values(array_filter($this->seed->rows,function($r)use($m,$n){return $r['receipt_id']===($m[1]??'')||$r['id']===(int)($n[1]??0);}));
    }
    public function get_col($sql) { return strpos($sql,'receipts')!==false?SiteEngine_Ads_Bridge_Consent::RECEIPT_COLUMNS:['id','form_id','response']; }
    public function get_var($sql) { return strpos($sql,'@@autocommit')!==false?'1':'0'; }
}
$db = new Joint_Read_DB($wpdb); $wpdb=$db;
$a=['assignment'=>'ALMA-JOINT-CONSENT-ADS-20261031','site'=>home_url('/'),'prefix'=>'wp_','form_id'=>3,
    'marker'=>'jct_'.str_repeat('a',32),'run_dir'=>'/home/alma.siteengine.space/.se-joint-acceptance/joint-consent-ads-20261031-01','_sha256'=>str_repeat('e',64),
    'tables'=>['submissions'=>'wp_fluentform_submissions','receipts'=>'wp_se_consent_receipts','events'=>'wp_se_consent_events','chains'=>'wp_se_consent_chains']];
$j=['assignment'=>$a['assignment'],'marker'=>$a['marker'],'allocation_sha256'=>$a['_sha256'],'chain_id'=>$stamps['A']['chain_id'],'entries'=>[],'audit'=>[],'contacts'=>[]];
foreach($stamps as $label=>$stamp){$id=$stamp['receipt_row_id'];$response=wp_json_encode(['se_joint_marker'=>$a['marker'],'se_joint_case'=>$label,'business'=>'unchanged','_se_consent_server'=>$stamp]);
    $db->entries[$id]=['id'=>$id,'form_id'=>3,'response'=>$response];$j['entries'][$label]=['id'=>$id,'contact'=>'A','response_sha256'=>hash('sha256',$response)];}
foreach($db->seed->rows as $id=>$row)$j['audit'][(string)$id]=['receipt_id'=>$row['receipt_id'],'sha256'=>hash('sha256',wp_json_encode($row)),'link_sha256'=>hash('sha256',wp_json_encode($db->seed->links[$id]))];
$j['head']=['revision'=>3,'sha256'=>hash('sha256',wp_json_encode($db->seed->heads[$j['chain_id']]))];
define('WP_CLI', true); define('SE_CONSENT_VERSION', '0.6.1-rc.2');
require __DIR__ . '/fixtures/joint-target-boundary.php';
$count=0;
function pass($condition,$name){global$count;if(!$condition)throw new RuntimeException($name);$count++;echo "PASS $name\n";}
function refused($fn,$contains){try{$fn();}catch(Throwable$e){return strpos($e->getMessage(),$contains)!==false;}return false;}
pass(!scj_inspect($a,$j,'A')['current']['allowed']&&!scj_inspect($a,$j,'B')['current']['allowed']&&scj_inspect($a,$j,'C')['current']['allowed'],'tool observes real immutable withdrawal/regrant authority');
$original=serialize([$db->entries,$db->seed->rows,$db->seed->links,$db->seed->heads]);
foreach(['conflicting-flat','null','malformed','unknown','unsigned','tampered','pending','failed','unavailable-head','failed-current-read']as$mode){$label=$mode==='conflicting-flat'?'B':'C';
    $result=scj_with_fault($a,$j,$label,$mode,function($projected)use($a,$j,$label){global$wpdb;return SiteEngine_Ads_Bridge_Consent::current($j['entries'][$label]['id'],3,$a['tables']['submissions'],$wpdb,SE_CONSENT_POLICY_VERSION);});
    pass($result['result']['allowed']===false&&$result['projection_hits']>0&&$result['restoration']==='VERIFIED',$mode.' denies through real Ads/Consent with verified restoration');
    if(in_array($mode,['unavailable-head','failed-current-read'],true))pass($result['result']['verified']===true,$mode.' fails current read after intact historical verification');
    pass($wpdb===$db&&scj_inspect($a,$j,'C')['current']['allowed'],'original global DB and real current permission restored');}
$bad=$j;$bad['entries']['C']['id']=999;
pass(refused(function()use($a,$bad){scj_with_fault($a,$bad,'C','failed',function(){throw new LogicException('callback must not run');});},'foreign/changed entry'),'foreign ID refused before injection');
$bad=$j;$bad['chain_id']=str_repeat('0',64);
pass(refused(function()use($a,$bad){scj_owned($a,$bad,'C');},'owned evidence'),'foreign chain refused');
$bad=$a;$bad['prefix']='foreign_';
pass(refused(function()use($bad,$j){scj_owned($bad,$j,'C');},'table map'),'foreign table map refused');
$bad=$j;$bad['entries']['C']['response_sha256']=str_repeat('0',64);
pass(refused(function()use($a,$bad){scj_owned($a,$bad,'C');},'foreign/changed'),'changed original evidence refused');
pass(refused(function()use($a,$j){scj_with_fault($a,$j,'C','failed-current-read',function(){throw new RuntimeException('injected callback failure');});},'RESTORATION_VERIFIED'),'callback exception runs restoration and positive readback');
pass($wpdb===$db&&scj_inspect($a,$j,'C')['current']['allowed'],'failure path restores real positive baseline');
pass(refused(function()use($a,$j){scj_with_fault($a,$j,'C','failed',function(){global$wpdb;$wpdb->query('DELETE FROM wp_se_consent_receipts');});},'RESTORATION_VERIFIED'),'fault view refuses write and restores on refusal');
pass($original===serialize([$db->entries,$db->seed->rows,$db->seed->links,$db->seed->heads]),'all original responses receipts links heads and barriers unchanged');
$head_key=array_key_first($db->seed->heads);$old_head=$db->seed->heads[$head_key];
pass(refused(function()use($a,$j,$db,$head_key){scj_with_fault($a,$j,'C','failed',function()use($db,$head_key){$db->seed->heads[$head_key]['revision']++;return false;});},'restoration/readback failed'),'restoration drift stops instead of claiming cleanup');
$db->seed->heads[$head_key]=$old_head; // Local fixture object only; never a server operation.
echo "Tooling checks: {$count} passed; real pinned Consent/Ads algorithms, synthetic SQL only. Installed operations NOT RUN.\n";
