<?php
/** Actual Engine::one with native/database doubles; no SQL/native acceptance claim. */
namespace SiteEngine\AdsBridge\Import {
    class Native {
        public array $rows=[];public int $writes=0;public bool $fail=false;
        function read($kind,$id,$registry){return $this->rows[$id]??null;}
        function candidates($kind,$body,$registry){return [];}
        function save($kind,$body,$id,$registry){$this->writes++;if($this->fail)throw new \RuntimeException('injected save failure');$id??=7;$this->rows[$id]=$body;return $id;}
    }
    class Session {public function check():void{}}
}
namespace {
    require dirname(__DIR__).'/includes/crm-import/common.php';require dirname(__DIR__).'/includes/crm-import/engine.php';
    use SiteEngine\AdsBridge\Import\{Engine,Native,Session,Store,JSON};
    $options=[];
    function wp_cache_delete(...$args){}function maybe_unserialize($s){return unserialize($s);}
    function update_option($key,$value,$autoload=false){$GLOBALS['options'][$key]=serialize($value);}
    $wpdb=new class {public $options='options',$last_error='';function prepare($sql,$key){return $key;}function get_var($key){return $GLOBALS['options'][$key]??null;}};
    $n=0;function ok($v,$why){global $n;if(!$v)throw new \LogicException($why);$n++;echo "PASS $why\n";}
    $method=new ReflectionMethod(Engine::class,'one');$native=new Native();$session=new Session();
    function fixture($id=null,$before=null){return ['id'=>bin2hex(random_bytes(16)),'revision'=>0,'registry'=>['objects'=>[]],'cursor'=>0,'receipts'=>[],'pending'=>null,'writes'=>0,'actions'=>[['key'=>'template.test','kind'=>'template','decision'=>$id===null?'create':'update','id'=>$id,'body'=>['title'=>'Example','status'=>'draft','html'=>'desired'],'source_hash'=>'source','before'=>$before]]];}
    function step(&$j,$n,$s){global $method;$method->invokeArgs(null,[&$j,$n,$s]);}
    $j=fixture();Store::save($j);step($j,$native,$session);
    ok($j['cursor']===1&&$native->writes===1&&$j['pending']===null,'Successful native save produces persisted exact ID receipt');
    ok(Store::read('seab_crm_import_job_'.$j['id'])['receipts']['template.test']['id']===7,'Receipt survives independent journal read');
    $j=fixture();$j['pending']=$j['actions'][0];Store::save($j);$count=$native->writes;
    try{step($j,$native,$session);throw new LogicException('should refuse');}catch(RuntimeException $e){ok(str_contains($e->getMessage(),'no returned native ID'),'Interrupted create without ID refuses replay');}
    ok($native->writes===$count&&$j['pending']!==null,'Unknown create retains pending intent and performs no native action');
    $j=fixture(7,['title'=>'Example','html'=>'before','status'=>'draft']);$j['pending']=$j['actions'][0];Store::save($j);step($j,$native,$session);
    ok($j['cursor']===1&&$native->writes===$count,'Completed update readback advances without duplicate vendor save');
    $j=fixture(8,['title'=>'Example','html'=>'before','status'=>'draft']);$native->rows[8]=$j['actions'][0]['before'];$j['pending']=$j['actions'][0];Store::save($j);step($j,$native,$session);
    ok($j['cursor']===1&&$native->writes===$count+1,'Exact unchanged-before update can safely complete');
    $j=fixture(8,['title'=>'Example','html'=>'before','status'=>'draft']);$j['pending']=$j['actions'][0];$native->rows[8]=['html'=>'foreign edit'];Store::save($j);$count=$native->writes;
    try{step($j,$native,$session);throw new LogicException('should refuse');}catch(RuntimeException $e){ok(str_contains($e->getMessage(),'exact readback'),'Drifted interrupted update refuses');}
    ok($native->writes===$count&&$j['pending']!==null,'Drift preserves journal and native destination edit');
    $j=fixture();$native->fail=true;Store::save($j);
    try{step($j,$native,$session);throw new LogicException('should fail');}catch(RuntimeException $e){ok($e->getMessage()==='injected save failure','Primary native failure remains visible');}
    ok(Store::read('seab_crm_import_job_'.$j['id'])['pending']['key']==='template.test','Intent persisted before failed vendor invocation');
    $j=fixture(8,['html'=>'not current']);$native->fail=false;Store::save($j);$count=$native->writes;
    try{step($j,$native,$session);throw new LogicException('should fail');}catch(RuntimeException $e){ok(str_contains($e->getMessage(),'changed since review'),'Review-to-write drift stops');}
    ok($native->writes===$count&&$j['pending']===null,'No uncertain write is introduced by a pre-write conflict');
    $j=fixture();Store::save($j);$stale=$j;Store::save($j);
    try{Store::save($stale);throw new LogicException('should fail');}catch(RuntimeException $e){ok(str_contains($e->getMessage(),'state changed'),'Stale journal revisions refuse overwrite');}
    $review=new ReflectionMethod(Engine::class,'reviewIdentity');$old=fixture();$old['status']='complete';$old['cursor']=1;$old['receipts']=['kept'=>'receipt'];$old['package']=['retained'=>'upload'];$new=$review->invoke(null,$old);
    ok($new['id']!==$old['id']&&$new['previous_import']===$old['id']&&$new['receipts']===[]&&$new['revision']===0,'Repeat from the retained upload gets a fresh operation identity');
    ok($old['receipts']===['kept'=>'receipt']&&$new['package']===$old['package'],'Old completed receipts and validated upload are preserved');
    echo json_encode(['assertions'=>$n,'native_sql'=>false,'status'=>'PASS'])."\n";
}
