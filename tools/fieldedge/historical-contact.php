<?php
/** Separate from the closed form-entry journal; no provider values in stdout. */
ini_set('display_errors','0'); ini_set('log_errors','0'); ini_set('zend.exception_ignore_args','1');
require_once __DIR__.'/../../includes/connectors/fieldedge/HistoricalContactDiscovery.php';
use SiteEngine\AdsBridge\Connectors\FieldEdge\OAuthClient;
use SiteEngine\AdsBridge\Connectors\FieldEdge\SelectedCaseJournal;
use SiteEngine\AdsBridge\Connectors\FieldEdge\HistoricalContactDiscovery;
$stage='historical_input_unconfirmed';
try {
    $raw=stream_get_contents(STDIN,32769);
    if (PHP_SAPI!=='cli'||strlen($raw)>32768) {throw new RuntimeException();}
    $config=json_decode($raw,true,8,JSON_THROW_ON_ERROR);unset($raw);
    $stage='historical_evidence_unconfirmed';
    if(($config['environment']??null)!=='Production_ClimatrolAirLLC' ||
       !preg_match('/\A[a-f0-9]{64}\z/D',$config['evidence_sha256']??'')) {throw new RuntimeException();}
    $raw=file_get_contents($config['evidence_file'],false,null,0,8193);
    if(!is_string($raw)||strlen($raw)>8192||hash('sha256',$raw)!==$config['evidence_sha256']||
       file_exists($config['findings_file'])||is_link($config['findings_file'])) {throw new RuntimeException();}
    $candidates=json_decode($raw,true,12,JSON_THROW_ON_ERROR);unset($raw);
    if(!is_array($candidates)||!array_is_list($candidates)||count($candidates)<1||count($candidates)>2) {throw new RuntimeException();}
    $stage='historical_journal_unconfirmed';
    $journal=new SelectedCaseJournal($config['journal_file'],$config['evidence_sha256']);
    $stage='historical_execution_unconfirmed';
    $report=(new OAuthClient())->selectedCase($config,$journal,static fn($get,$j)=>HistoricalContactDiscovery::run($get,$j,$candidates));
    unset($candidates);
    $fh=fopen($config['findings_file'],'xb');
    if(!$fh) {throw new RuntimeException();}
    $private=json_encode($report,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
    if(fwrite($fh,$private)!==strlen($private)||!fflush($fh)||!fsync($fh)) {throw new RuntimeException();}
    fclose($fh);
    $safe=['reason_code'=>$report['reason_code'],
       'authentication_succeeded'=>$report['authentication_succeeded'],
       'token_http_status'=>$report['token_http_status'],
       'token_attempts'=>$report['journal']['token_attempts'],
       'business_get_attempts'=>count($report['journal']['attempts']),
       'attempt_statuses'=>array_map(static fn($a)=>array_intersect_key($a,array_flip(['number','operation','status','http_status'])),$report['journal']['attempts']),
       'candidate_results'=>array_map(static fn($f)=>array_intersect_key($f,array_flip(['crm_contact_id','reason_code','provider_customer_found','work_order_found','invoice_read_verified','lead_id_verified','website_inquiry_attributed','request_failure_stop'])),$report['findings']??[]),
       'private_evidence_retained'=>true];
    echo json_encode($safe,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)."\n";
    exit($report['reason_code']==='selected_case_completed'?0:2);
} catch(Throwable $e) {
    echo json_encode(['reason_code'=>$stage],JSON_THROW_ON_ERROR)."\n"; exit(2);
}
