<?php
/** Local real contract/verdict checks. No WordPress/native acceptance claim. */
foreach(['common','sequences','dependencies','format','engine'] as $file)require dirname(__DIR__).'/includes/crm-import/'.$file.'.php';
use SiteEngine\AdsBridge\Import\{JSON,Contract,Format,Engine};
$n=0;
function ok($v,$why){global $n;if(!$v)throw new LogicException($why);$n++;echo "PASS $why\n";}
function reject($f,$why){try{$f();}catch(Throwable $e){ok(true,$why);return;}throw new LogicException('Expected refusal: '.$why);}
$v=json_decode(file_get_contents($argv[1]),true,128,JSON_THROW_ON_ERROR);
Contract::validate($v);ok(true,'Actual producer v2 package validates with the extracted contract');
$ordered=Contract::order($v['objects']);$seen=[];
foreach($ordered as $key=>$d){foreach(['funnel_ref','parent_ref','sequence_ref'] as $f)if(!empty($d['body'][$f]))ok(isset($seen[$d['body'][$f]]),'Parent precedes '.$key);$seen[$key]=true;}
foreach(['execution_enabled'=>true,'format'=>'unknown','version'=>'999','contacts'=>[],'runtime_profile'=>[]] as $k=>$x){$b=$v;$b[$k]=$x;reject(fn()=>Contract::validate($b),'Refuse '.$k);}
foreach(['password','credentials','contact_id','receipt','ledger','queue','api_key'] as $key){$b=$v;$b['objects'][array_key_first($b['objects'])]['body'][$key]='forbidden';reject(fn()=>Contract::validate($b),'Refuse operational/private '.$key);}
$b=$v;$key=array_key_first($b['objects']);$b['objects'][$key]['body']['status']='published';reject(fn()=>Contract::validate($b),'Refuse active content');
$b=$v;$b['configuration_revision']='cfg-invalid';reject(fn()=>Contract::validate($b),'Reject changed revision');
$b=$v;$b['dependency_bindings']['form.primary']['id']=99;reject(fn()=>Contract::validate($b),'Reject source runtime binding');
$b=$v;$k=array_key_first($b['objects']);$b['objects'][$k]['body']['title']='{{foreign.secret_value}}';$b['configuration_revision']=Format::revision($b['objects'],$b['dependencies'],$b['dependency_bindings']);reject(fn()=>Contract::validate($b),'Reject unbound template consumer');
$b=$v;foreach($b['objects'] as $k=>$d)if($d['kind']==='step'&&isset($d['body']['settings']['campaign_ref'])){foreach($b['objects'] as $ck=>$candidate)if($candidate['kind']==='sequence_email'&&$candidate['body']['funnel_ref']!==$d['body']['funnel_ref']){$b['objects'][$k]['body']['settings']['campaign_ref']=$ck;break;}break;}$b['configuration_revision']=Format::revision($b['objects'],$b['dependencies'],$b['dependency_bindings']);reject(fn()=>Contract::validate($b),'Reject cross-funnel campaign relationship');
reject(fn()=>Contract::order(['a'=>['body'=>['parent_ref'=>'b']],'b'=>['body'=>['parent_ref'=>'a']]]),'Reject relationship cycle');
$d=['kind'=>'template','body'=>['title'=>'Message','html'=>'original','status'=>'draft']];$actual=$d['body'];$known=['body'=>$actual,'source_hash'=>JSON::hash($actual)];
ok(Engine::decision('template.test',$d,null,null)['decision']==='create','Missing object creates');
ok(Engine::decision('template.test',$d,$actual,$known)['decision']==='reuse','Identical object has no vendor save');
$edited=$actual;$edited['html']='destination edit';
ok(Engine::decision('template.test',$d,$edited,$known)['decision']==='conflict','Destination editor change requires choice');
$a=Engine::decision('template.test',$d,$edited,$known,'keep');ok($a['body']===$edited&&$a['decision']==='reuse','Keep preserves editable destination body');
ok(Engine::decision('template.test',$d,$edited,$known,'replace')['body']===$actual,'Deliberate Replace restores package body');
$known=['body'=>$edited,'source_hash'=>JSON::hash($actual)];ok(Engine::decision('template.test',$d,$edited,$known)['decision']==='reuse','Repeat after Keep does not overwrite or save');
$d2=$d;$d2['body']['html']='producer revision';$known=['body'=>$actual,'source_hash'=>JSON::hash($actual)];
ok(Engine::decision('template.test',$d2,$actual,$known)['decision']==='update','Unedited destination accepts source update');
$known=['body'=>$edited,'source_hash'=>JSON::hash($actual)];ok(Engine::decision('template.test',$d2,$edited,$known)['decision']==='conflict','A later producer revision cannot silently undo Keep');
$active=$actual;$active['status']='active';reject(fn()=>Engine::decision('template.test',$d,$active,null,'replace'),'No automatic deactivation of active objects');
$d=['kind'=>'field','body'=>['slug'=>'customer_rating','type'=>'select-one','options'=>['Other'],'label'=>'Outcome']];$wrong=$d['body'];$wrong['type']='text';
reject(fn()=>Engine::decision('field.outcome_reason',$d,$wrong,null,'keep'),'Keep cannot hide incompatible shared field');
$d=['kind'=>'tag','body'=>['title'=>'New','slug'=>'new','description'=>'']];$actual=$d['body'];$actual['description']='local description';
ok(Engine::decision('tag.new',$d,$actual,null)['body']===$actual,'Compatible existing tag is adopted without rewriting');
ok(JSON::hash(['b'=>2,'a'=>1])===JSON::hash(['a'=>1,'b'=>2]),'Stable identity hashes ignore object key order');
echo json_encode(['assertions'=>$n,'native_sql'=>false,'status'=>'PASS'])."\n";
