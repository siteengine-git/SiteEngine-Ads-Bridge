<?php
/** Shared virtual target boundary for affected tooling tests only. */
$target_file = $argv[2] ?? '';
scj_require(is_file($target_file) && hash_file('sha256', $target_file) === '2b7a5bf897dd22c1ca7a629b88566b4c9f9589e0f9f3eb8a4e2d62cfe191f117', 'supply exact Ads R4 joint-target.php');
require $target_file;
// Namespace only: production algorithms are unchanged; native OS/WP/file boundaries are doubled.
$prefix = 'namespace SCJTargetFixture; use \RuntimeException; use \Throwable; use \ReflectionFunction; use \ReflectionClass; use \SiteEngine_Consent_Authority; use \SiteEngine_Consent_Continuity;';
eval($prefix . <<<'PHP'
function gethostname() { return $GLOBALS['observed']['server_hostname']; }
function posix_geteuid() { return $GLOBALS['observed']['owner_uid']; }
function posix_getpwuid($id) { return ['name' => $GLOBALS['observed']['owner']]; }
function get_option($key) { return $GLOBALS['observed']['stored_' . $key]; }
function home_url($path) { return $GLOBALS['observed']['effective_home']; }
function realpath($p) {
    if ($p === ABSPATH) return $GLOBALS['observed']['document_root'];
    if ($p === $GLOBALS['run_dir'] || isset($GLOBALS['private_files'][$p])) return $p;
    return \realpath($p);
}
function is_file($p) { return isset($GLOBALS['private_files'][$p]) || \is_file($p); }
function is_link($p) { return isset($GLOBALS['private_files'][$p]) ? ($GLOBALS['private_files'][$p]['link'] ?? false) : \is_link($p); }
function filesize($p) { return isset($GLOBALS['private_files'][$p]) ? strlen($GLOBALS['private_files'][$p]['bytes']) : \filesize($p); }
function fileperms($p) { return $p === $GLOBALS['run_dir'] ? 0700 : ($GLOBALS['private_files'][$p]['mode'] ?? \fileperms($p)); }
function fileowner($p) { return $GLOBALS['private_files'][$p]['uid'] ?? \fileowner($p); }
function file_get_contents($p) { return $GLOBALS['private_files'][$p]['bytes'] ?? \file_get_contents($p); }
PHP
);
eval($prefix . substr(file_get_contents($target_file), 5));
eval($prefix . substr(file_get_contents(dirname(__DIR__, 2) . '/tools/acceptance/consent-joint.php'), 5));
$observed = ['server_hostname'=>'local.siteengine.cloud','owner'=>'almas9110','owner_uid'=>12345,
    'document_root'=>'/home/alma.siteengine.space/public_html','database_server_hostname'=>'local.siteengine.cloud',
    'database_name_sha256'=>hash('sha256','synthetic-database'),'database_name'=>'synthetic-database','prefix'=>'wp_',
    'stored_home'=>'http://alma.siteengine.space','stored_siteurl'=>'http://alma.siteengine.space',
    'effective_home'=>'http://alma.siteengine.space/','browser_origin'=>'https://alma.siteengine.space'];
$allocated_target = $observed; unset($allocated_target['database_name']);
$original_db = $wpdb;
$wpdb = new class($original_db) {
    public $prefix='wp_', $last_error='', $entries, $seed; private $db;
    public function __construct($db) { $this->db=$db; $this->entries =& $db->entries; $this->seed=$db->seed; }
    public function get_row($sql,...$args) {
        if ($sql==='SELECT DATABASE() AS name, @@hostname AS host') return ['name'=>$GLOBALS['observed']['database_name'],'host'=>$GLOBALS['observed']['database_server_hostname']];
        return $this->db->get_row($sql,...$args);
    }
    public function __call($name,$args) { return $this->db->$name(...$args); }
};
$db=$wpdb;
$a['site']='https://alma.siteengine.space/'; $a['owner_uid']=12345;
unset($a['_sha256']); $allocation_bytes=wp_json_encode($a); $a['_sha256']=hash('sha256',$allocation_bytes);
$j['allocation_sha256']=$a['_sha256'];
$run_dir=$a['run_dir'];
$private_files=[];
$private_files[$run_dir.'/allocation.json']=['bytes'=>$allocation_bytes,'mode'=>0600,'uid'=>12345];
function repin_context(array $target): void {
    global $a,$j,$private_files,$run_dir;
    $context=['assignment'=>$a['assignment'],'allocation_sha256'=>$a['_sha256'],'target'=>$target];
    $bytes=wp_json_encode($context); $cpin=hash('sha256',$bytes);
    $release=wp_json_encode(['status'=>'RELEASED_J1_J6_J7','allocation_sha256'=>$a['_sha256'],'context_sha256'=>$cpin]);
    $private_files[$run_dir.'/context.json']=['bytes'=>$bytes,'mode'=>0600,'uid'=>12345];
    $private_files[$run_dir.'/fixture-release.json']=['bytes'=>$release,'mode'=>0600,'uid'=>12345];
    $a['_context']=$context; $a['_release_sha256']=hash('sha256',$release); $j['context_sha256']=$cpin;
}
repin_context($allocated_target); $baseline_a=$a; $baseline_j=$j; $baseline_files=$private_files; $baseline_observed=$observed;
