<?php
require_once __DIR__ . '/../includes/connectors/fieldedge/LeadReference.php';
require_once __DIR__ . '/../includes/connectors/fieldedge/LeadResponse.php';
use SiteEngine\AdsBridge\Connectors\FieldEdge\LeadReference as R;
use SiteEngine\AdsBridge\Connectors\FieldEdge\LeadResponse;
$n=0;
function refCheck(bool $v,string $s):void {global $n;if(!$v){throw new RuntimeException('FAIL:'.$s);}++$n;echo "PASS: $s\n";}
$lead='00000000-0000-4000-8000-000000000301';$customer='00000000-0000-4000-8000-000000000302';
$body=json_encode(array('data'=>array('leadId'=>$lead,'trackingId'=>'synthetic-original-ref','status'=>0,'customer'=>array('customerId'=>$customer,'email'=>'PRIVATE-IGNORE'),'accountId'=>'00000000-0000-4000-8000-000000000303','workOrderId'=>'00000000-0000-4000-8000-000000000304')));
$r=R::detail('synthetic-tenant',$lead,$body);
refCheck($r['lead_id']===$lead && $r['customer_id']===$customer,'documented lead/customer identity extracted');
refCheck($r['provider_status']==='code:0','numeric status retained without invented label');
refCheck($r['tracking_reference']==='synthetic-original-ref','tracking reference retained without treating as idempotency');
refCheck($r['work_order_id']===null && !$r['attribution_established'],'undocumented injected workOrderId cannot create crosswalk or attribution');
refCheck(!str_contains(json_encode($r),'PRIVATE-IGNORE'),'unneeded customer fields not retained');
foreach(array(0,19,'Initiated','InProcess',null)as$v){refCheck(R::status($v)===(is_int($v)?'code:'.$v:$v),'schema/example status variants explicit');}
foreach(array(-1,20,true,'0',array())as$v){try{R::status($v);refCheck(false,'badstatus');}catch(InvalidArgumentException$e){refCheck(true,'unknown status held');}}
try{R::detail('synthetic-tenant',$customer,$body);refCheck(false,'wronglead');}catch(InvalidArgumentException$e){refCheck(true,'expected lead UUID mismatch held');}
$x=LeadResponse::classify(array('status'=>201,'content_type'=>'application/json','body'=>json_encode(array('data'=>array('leadId'=>$lead,'status'=>19)))));
refCheck($x['classification']==='success' && $x['provider_status']==='code:19' && $x['received_at']===null,'create response numeric enum normalized to AB string metadata');
echo "RESULT: $n PASS, 0 FAIL; documented synthetic provider identity fixtures only.\n";
