<?php
/** Offline bounded FieldEdge live snapshot tests; no provider requests. */
require_once __DIR__ . '/../includes/connectors/fieldedge/LiveSnapshotDiscovery.php';
use SiteEngine\AdsBridge\Connectors\FieldEdge\LiveSnapshotDiscovery as S;
use SiteEngine\AdsBridge\Connectors\FieldEdge\SelectedCaseJournal as J;
$n=0;
$check=static function(bool $ok,string $why)use(&$n){if(!$ok){throw new RuntimeException($why);}++$n;};
$c='bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb';
$t='aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa';
$job='cccccccc-cccc-4ccc-8ccc-cccccccccccc';
$i='dddddddd-dddd-4ddd-8ddd-dddddddddddd';
$case=['schema'=>1,'customer_id'=>$c,'tenant_id'=>$t,'contact_id'=>334,'form_id'=>39,'entry_id'=>889,'email_sha256'=>str_repeat('a',64)];
$path=sys_get_temp_dir().'/fe-live-snapshot-'.bin2hex(random_bytes(8)).'.json';
$j=new J($path,str_repeat('b',64));
$calls=[];
$get=static function(string $operation,array $params)use(&$calls,$c,$job,$i) {
 $calls[]=$operation;
 if($operation==='customer_work_orders')return ['total'=>1,'data'=>[['workOrderId'=>$job]]];
 if($operation==='work_order')return ['workOrderId'=>$job,'customerId'=>$c,'completed'=>true,'workOrderInformation'=>['completedDate'=>'2026-11-04T19:30:00Z'],'invoiceId'=>$i,'customerEmail'=>'PRIVATE_UNSHIPPED'];
 throw new RuntimeException('unlisted request');
};
$out=S::run($get,$j,$case);
$check($calls===['customer_work_orders','work_order'],'two exact GET operation names');
$check($out['contact_id']===334 && $out['form_id']===39 && $out['entry_id']===889,'original form anchor');
$check(count($out['jobs'])===1,'one work order summary');
$check($out['jobs'][0]['customer_id']===$c,'explicit provider customer');
$check($out['jobs'][0]['invoice_id']===$i,'explicit invoice relation');
$check($out['jobs'][0]['completed']===true,'provider completion boolean');
$check($out['jobs'][0]['completed_at']==='2026-11-04T19:30:00Z','provider zoned date');
$check(!str_contains(json_encode($out),'PRIVATE_UNSHIPPED'),'raw provider PII eliminated');
$check(array_keys($out['jobs'][0])===['work_order_id','customer_id','completed','completed_at','invoice_id','source_sha256'],'field allowlist');
$check(strlen($out['jobs'][0]['source_sha256'])===64,'raw snapshot digest preserved');
$bad=$case;$bad['contact_id']=0;
$check((function()use($get,$j,$bad){try{S::run($get,$j,$bad);return false;}catch(Throwable $e){return true;}})(),'invalid contact refused');
$badGet=static fn()=>['total'=>4,'data'=>[['workOrderId'=>$job]]];
$check((function()use($badGet,$j,$case){try{S::run($badGet,$j,$case);return false;}catch(Throwable $e){return true;}})(),'incomplete page refuses');
unset($j);unlink($path);
echo "PASS $n live snapshot assertions; actual network requests=0\n";
