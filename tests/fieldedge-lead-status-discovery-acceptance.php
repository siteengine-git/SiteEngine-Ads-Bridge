<?php
/** One exact lead GET; zero network in tests; no side effects. */
require_once __DIR__ . '/../includes/connectors/fieldedge/LeadStatusDiscovery.php';
use SiteEngine\AdsBridge\Connectors\FieldEdge\LeadStatusDiscovery as S;
use SiteEngine\AdsBridge\Connectors\FieldEdge\SelectedCaseJournal as J;
$n=0;
$assert=static function(bool $yes,string $why)use(&$n){if(!$yes){throw new RuntimeException('FAIL '.$why);}++$n;};
$tenant='22222222-2222-4222-8222-222222222222';
$lead='11111111-1111-4111-8111-111111111111';
$case=['schema'=>1,'tenant_id'=>$tenant,'inquiry_id'=>'ff:39:889','lead_id'=>$lead];
$path=sys_get_temp_dir().'/fe-exact-lead-'.bin2hex(random_bytes(8)).'.json';
$journal=new J($path,str_repeat('a',64));
$operations=[];
$get=static function($op,$args)use(&$operations,$lead){
 $operations[]=['operation'=>$op,'arguments'=>$args];
 return ['data'=>['leadId'=>$lead,'trackingId'=>$lead,'status'=>'JobFinalized',
     'customer'=>['email'=>'PRIVATE-NEVER-OUTPUT@example.invalid'],
     'workOrderId'=>'44444444-4444-4444-8444-444444444444',
     'invoiceId'=>'55555555-5555-4555-8555-555555555555']];
};
$result=S::run($get,$journal,$case);
$assert(count($operations)===1,'exactly one GET');
$assert($operations[0]['operation']==='lead','only lead operation');
$assert($operations[0]['arguments']===['id'=>$lead],'exact GUID');
$assert($result['status']==='JobFinalized','provider status');
$assert($result['outcome']==='job_finalized','known state mapped');
$assert($result['lead_id']===$lead && $result['inquiry_id']==='ff:39:889','saved original binding');
$assert($result['work_order_id']===null && $result['approved_revenue']===null,'no invoice association');
$assert(!$result['crm_lifecycle_write_allowed'] && !$result['advertising_export_allowed'],'no side effects');
$assert(!str_contains(json_encode($result),'PRIVATE-NEVER-OUTPUT'),'provider PII excluded');
$assert(strlen($result['observation_sha256'])===64,'receipt digest');
$assert($journal->remaining()===6,'double never reserves real network quota');
$bad=$case; $bad['lead_id']='not-uuid';
try{S::run($get,$journal,$bad);throw new RuntimeException('admitted invalid lead');}catch(RuntimeException $e){$assert($e->getMessage()==='targeted_reference_refused','invalid lead refused');}
$bad=$case; $bad['inquiry_id']='';
try{S::run($get,$journal,$bad);throw new RuntimeException('admitted invalid inquiry');}catch(RuntimeException $e){$assert($e->getMessage()==='lead_status_evidence_invalid','invalid inquiry refused');}
$wrong=static fn()=>['data'=>['leadId'=>'99999999-9999-4999-8999-999999999999','status'=>'Accepted']];
try{S::run($wrong,$journal,$case);throw new RuntimeException('wrong lead accepted');}catch(InvalidArgumentException $e){$assert($e->getMessage()==='lead_detail_identity_mismatch','wrong lead response refused');}
$bad=$case; $bad['extra']='PII';
try{S::run($get,$journal,$bad);throw new RuntimeException('admitted arbitrary data');}catch(RuntimeException $e){$assert($e->getMessage()==='lead_status_evidence_invalid','extra input refused');}
unset($journal);unlink($path);
echo "PASS $n exact-GET lead status tests; network=0; CRM writes=0; advertising=0\n";
