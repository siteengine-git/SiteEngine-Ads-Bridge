<?php
/** Actual Engine preview/one/readback with storage/vendor doubles, NOT native SQL. */
namespace SiteEngine\AdsBridge\Import {
    class Session { public function __construct($kinds=[]){} public function check():void{} }
    class Native {
        static array $rows=[];static int $writes=0,$next=10000;
        public function __construct($config=[]){}
        static function encode($body,$registry){
            foreach(['funnel_ref','parent_ref','sequence_ref'] as $k)if(!empty($body[$k]))$body[$k]=$registry['objects'][$body[$k]]['id'];
            if(isset($body['settings']['campaign_ref']))$body['settings']['campaign_ref']=$registry['objects'][$body['settings']['campaign_ref']]['id'];
            if(isset($body['conditions']))$body['conditions']=Format::conditionMap($body['conditions'],$registry,true);
            return $body;
        }
        static function decode($body,$registry){
            $ref=static function($id)use($registry){foreach($registry['objects'] as$key=>$r)if((string)$r['id']===(string)$id)return $key;return 'unmapped:'.$id;};
            foreach(['funnel_ref','parent_ref','sequence_ref'] as $k)if(!empty($body[$k]))$body[$k]=$ref($body[$k]);
            if(isset($body['settings']['campaign_ref']))$body['settings']['campaign_ref']=$ref($body['settings']['campaign_ref']);
            if(isset($body['conditions']))$body['conditions']=Format::conditionMap($body['conditions'],$registry,false);
            return $body;
        }
        function read($kind,$id,$registry){return isset(self::$rows[$kind][$id])?self::decode(self::$rows[$kind][$id],$registry):null;}
        function candidates($kind,$body,$registry){
            $out=[];foreach(self::$rows[$kind]??[] as$id=>$b){
                $match=$kind==='field'?$b['slug']===$body['slug']:(in_array($kind,['tag','list'],true)?($b['title']===$body['title']||$b['slug']===$body['slug']):$b['title']===$body['title']);
                foreach(['funnel_ref','sequence_ref']as$k)if(isset($body[$k]))$match=$match&&($b[$k]??null)===($registry['objects'][$body[$k]]['id']??-1);
                if($match)$out[]=$id;
            }return array_slice($out,0,3);
        }
        function save($kind,$body,$id,$registry){self::$writes++;$id??=++self::$next;self::$rows[$kind][$id]=self::encode($body,$registry);return $id;}
        function saveDependency($kind,$body,$id){self::$writes++;$id??=$kind==='field'?$body['slug']:++self::$next;self::$rows[$kind][$id]=$body;return (string)$id;}
    }
}
namespace {
    define('ABSPATH',__DIR__.'/');define('ARRAY_A','ARRAY_A');$options=[];$n=0;
    function current_user_can($c){return true;}function get_current_user_id(){return 1;}
    function wp_cache_delete(...$a){}function maybe_unserialize($v){return unserialize($v);}function update_option($k,$v,$a=false){$GLOBALS['options'][$k]=serialize($v);}
    function seab_configuration_capability(){return ['destination'=>'synthetic-fixture'];}
    class SiteEngine_Ads_Bridge_Setup {static function with_executor($f){return $f();}}
    class SiteEngine_Ads_Bridge_Coordination {static function enter_setup($v){return ['ok'=>true];}static function release(){return true;}}
    class SiteEngine_Ads_Bridge_Settings {
        static function get(){return \SiteEngine\AdsBridge\Import\Store::read('seab_settings_v2',[]);}static function defaults(){return [];}
        static function merge($a,$b){return array_replace_recursive($a,$b);}static function sanitize_config($b){return $b;}static function save($b){update_option('seab_settings_v2',$b);}
    }
    class SiteEngine_Ads_Bridge_Config {static function instance(){return new self();}function external_override_active(){return false;}function all(){return [];}}
    $wpdb=new class {
        public $prefix='fixture_',$options='options',$last_error='';
        function prepare($q,...$v){return str_contains($q,'option_value')?$v[0]:$q;}
        function get_var($k){return $GLOBALS['options'][$k]??null;}
        function get_row($q,$mode){return ['id'=>'4','form_fields'=>'{"fields":[{"attributes":{"name":"service"}}]}'];}
    };
    foreach(['common','sequences','dependencies','format','engine']as$f)require dirname(__DIR__).'/includes/crm-import/'.$f.'.php';
    require dirname(__DIR__).'/includes/class-siteengine-ads-bridge-fields.php';
    use SiteEngine\AdsBridge\Import\{Engine,Native,Session,Store,JSON};
    function ok($v,$label){global $n;if(!$v)throw new \LogicException($label);$n++;echo "PASS $label\n";}
    function refuses($f,$needle){try{$f();}catch(\RuntimeException $e){ok(str_contains($e->getMessage(),$needle),'Refuses '.$needle);return;}throw new \LogicException('Expected '.$needle);}
    $package=json_decode(file_get_contents($argv[1]),true,128,JSON_THROW_ON_ERROR);
    $map=['form'=>'4','form_service'=>'service','service_field'=>'service_type','outcome_field'=>'customer_rating','service_label'=>'Synthetic fixture service'];
    $definitions=new \ReflectionMethod(Engine::class,'definitions');$one=new \ReflectionMethod(Engine::class,'one');$range=new \ReflectionMethod(Engine::class,'verifyRange');
    function seed(){global $options,$package,$map,$definitions;
        $options=[];Native::$rows=[];Native::$writes=0;Native::$next=10000;
        $j=['id'=>str_repeat('a',32),'revision'=>0,'actor'=>1,'destination'=>'synthetic-fixture','source_pin'=>Engine::sourcePin(),'upload_sha256'=>str_repeat('b',64),'package'=>$package,'status'=>'uploaded','mappings'=>array_replace($map,['form'=>4]),'actions'=>[],'choices'=>[],'cursor'=>0,'pending'=>null,'receipts'=>[],'writes'=>0];
        $defs=$definitions->invoke(null,$j);$reg=['objects'=>[]];$id=100;
        foreach($defs as$key=>$d)$reg['objects'][$key]=['kind'=>$d['kind'],'id'=>$d['kind']==='field'?$d['body']['slug']:++$id,'body'=>$d['body'],'source_hash'=>JSON::hash($d['body'])];
        foreach($defs as$key=>$d)Native::$rows[$d['kind']][$reg['objects'][$key]['id']]=Native::encode($d['body'],$reg);
        update_option(Store::REGISTRY,$reg);Store::save($j);update_option(Store::CURRENT,$j['id']);update_option('seab_crm_import_job_historical',['status'=>'complete','receipts'=>['original'=>'immutable']]);return [$defs,$reg];
    }
    function advance(&$j){global $one;$one->invokeArgs(null,[&$j,new Native(),new Session()]);}
    function single($j,$a){$j['actions']=[$a];$j['cursor']=0;$j['receipts']=[];$j['pending']=null;$j['revision']=Store::read('seab_crm_import_job_'.$j['id'])['revision'];Store::save($j);return $j;}
    [$defs,$original]=seed();$deleted=[];foreach(['tag'=>8,'automation'=>7,'sequence_email'=>19,'step'=>22]as$kind=>$max){$count=0;foreach($original['objects']as$key=>$r)if($r['kind']===$kind&&$count++<$max){unset(Native::$rows[$kind][$r['id']]);$deleted[$key]=$r;}}
    $template=array_key_first(array_filter($defs,fn($d)=>$d['kind']==='template'));$id=$original['objects'][$template]['id'];Native::$rows['template'][$id]['html']='Retained destination edit';$history=$options['seab_crm_import_job_historical'];$registry=$options[Store::REGISTRY];
    $v=Engine::preview($map);ok(count($deleted)===56&&count(array_filter($v['actions'],fn($a)=>$a['replaces_id']!==null))===56,'Actual preview reviews all56 deleted managed identities as recreation');
    ok(Native::$writes===0&&$options[Store::REGISTRY]===$registry&&$options['seab_crm_import_job_historical']===$history,'Preview writes no native object and preserves registry/completed journal');
    ok(count(array_filter($v['actions'],fn($a)=>$a['decision']==='conflict'))===1,'Surviving destination edit still requires explicit choice');
    Engine::preview($map,[$template=>'keep']);$j=Store::job();while($j['cursor']<count($j['actions']))advance($j);
    ok(count($j['receipts'])===126&&$j['pending']===null,'Actual one() completes126 receipts including settings with doubles');
    foreach($deleted as$key=>$old)ok((string)$j['registry']['objects'][$key]['id']!==(string)$old['id'],'Recreated identity gets new native ID '.$key);
    ok(Native::$writes===56,'Only56 missing native definitions saved; reused objects not rewritten');
    ok(Native::$rows['template'][$id]['html']==='Retained destination edit','Keep preserves surviving content edit exactly');
    ok($options['seab_crm_import_job_historical']===$history,'Original completed receipts remain byte-identical after execution');
    ok(is_string($range->invoke(null,$j,new Native(),0,count($j['actions']))),'Complete-range readback verifies rebuilt portable relationships');
    [$defs,$reg]=seed();$tag=array_key_first(array_filter($defs,fn($d)=>$d['kind']==='tag'));$r=$reg['objects'][$tag];unset(Native::$rows['tag'][$r['id']]);Native::$rows['tag'][9000]=$defs[$tag]['body'];
    refuses(fn()=>Engine::preview($map),'Multiple native objects match');ok(Native::$writes===0,'Alternate matching identity never silently replaces deleted managed identity');
    [$defs,$reg]=seed();$key=array_key_first(array_filter($defs,fn($d)=>$d['kind']==='tag'));$reg['objects'][$key]['id']=null;update_option(Store::REGISTRY,$reg);
    refuses(fn()=>Engine::preview($map),'Managed native identity is invalid');ok(Native::$writes===0,'Unknown managed ID is not evidence of a deleted object');
    [$defs,$reg]=seed();$tag=array_key_first(array_filter($defs,fn($d)=>$d['kind']==='tag'));unset(Native::$rows['tag'][$reg['objects'][$tag]['id']]);Engine::preview($map);$j=Store::job();$a=current(array_filter($j['actions'],fn($a)=>$a['key']===$tag));Native::$rows['tag'][9000]=$defs[$tag]['body'];$j=single($j,$a);
    refuses(function()use(&$j){advance($j);},'Native identity collision after review');ok(Native::$writes===0,'A new matching object after recreation review blocks before saving');
    [$defs,$reg]=seed();$key=array_key_first(array_filter($defs,fn($d)=>$d['kind']==='template'));Engine::preview($map);$j=Store::job();$a=current(array_filter($j['actions'],fn($a)=>$a['key']===$key));unset(Native::$rows['template'][$a['id']]);$j=single($j,$a);
    refuses(function()use(&$j){advance($j);},'Destination changed since review');ok(Native::$writes===0&&$j['pending']===null,'Deletion after review remains blocked before vendor action');
    [$defs,$reg]=seed();$tag=array_key_first(array_filter($defs,fn($d)=>$d['kind']==='tag'));$r=$reg['objects'][$tag];unset(Native::$rows['tag'][$r['id']]);Engine::preview($map);$j=Store::job();$a=current(array_filter($j['actions'],fn($a)=>$a['key']===$tag));Native::$rows['tag'][$r['id']]=['title'=>'Different foreign title','slug'=>'different','description'=>''];$j=single($j,$a);
    refuses(function()use(&$j){advance($j);},'Deleted managed identity reappeared');ok(Native::$writes===0,'Reappearing old ID is not ignored even with a different title');
    [$defs,$reg]=seed();$parent=array_key_first(array_filter($defs,fn($d)=>$d['kind']==='automation'));unset(Native::$rows['automation'][$reg['objects'][$parent]['id']]);Engine::preview($map);$j=Store::job();$relocations=array_filter($j['actions'],fn($a)=>!empty($a['rebind_refs']));
    ok(count($relocations)>0&&count(array_filter($relocations,fn($a)=>$a['decision']==='relink'))===count($relocations),'Surviving child relationships explicitly review as relink');
    while($j['cursor']<count($j['actions']))advance($j);
    ok(is_string($range->invoke(null,$j,new Native(),0,count($j['actions']))),'Recreated parent plus surviving children pass final portable readback');
    foreach($relocations as$a){$raw=Native::$rows[$a['kind']][$a['id']];ok(($raw['funnel_ref']??null)===$j['registry']['objects'][$parent]['id'],'Surviving child uses new parent ID '.$a['key']);}
    // Interruption before and after a relink must use exact before/current identities.
    [$defs,$reg]=seed();$parent=array_key_first(array_filter($defs,fn($d)=>$d['kind']==='automation'));unset(Native::$rows['automation'][$reg['objects'][$parent]['id']]);Engine::preview($map);$j=Store::job();$parentAction=current(array_filter($j['actions'],fn($a)=>$a['key']===$parent));$child=current(array_filter($j['actions'],fn($a)=>!empty($a['rebind_refs'])));$j=single($j,$parentAction);advance($j);$j=single($j,$child);$j['pending']=$child;Store::save($j);$writes=Native::$writes;advance($j);
    ok(Native::$writes===$writes+1&&$j['cursor']===1,'Pending relink with intact old bindings safely completes once');
    $j=single($j,$child);$j['pending']=$child;Store::save($j);$writes=Native::$writes;advance($j);
    ok(Native::$writes===$writes&&$j['cursor']===1,'Pending relink already persisted is read back without duplicate save');
    [$defs,$reg]=seed();$parent=array_key_first(array_filter($defs,fn($d)=>$d['kind']==='automation'));unset(Native::$rows['automation'][$reg['objects'][$parent]['id']]);Engine::preview($map);$j=Store::job();$a=current(array_filter($j['actions'],fn($a)=>$a['key']===$parent));$child=current(array_filter($j['actions'],fn($a)=>!empty($a['rebind_refs'])));$j=single($j,$a);advance($j);$j=single($j,$child);$j['pending']=$child;Store::save($j);Native::$rows[$child['kind']][$child['id']]['title']='Unreviewed edit';$writes=Native::$writes;
    refuses(function()use(&$j){advance($j);},'Uncertain native operation requires exact readback');ok(Native::$writes===$writes&&$j['pending']!==null,'Pending relink preserves foreign edit and uncertain intent');
    [$defs,$reg]=seed();$tag=array_key_first(array_filter($defs,fn($d)=>$d['kind']==='tag'));$auto=array_key_first(array_filter($defs,fn($d)=>$d['kind']==='automation'));
    $body=$defs[$auto]['body'];$body['conditions']=[[['source'=>['subscriber','tags'],'operator'=>'in','value'=>[$tag]]]];
    Native::$rows['automation'][$reg['objects'][$auto]['id']]=Native::encode($body,$reg);unset(Native::$rows['tag'][$reg['objects'][$tag]['id']]);
    Engine::preview($map,[$auto=>'keep']);$j=Store::job();$tagAction=current(array_filter($j['actions'],fn($a)=>$a['key']===$tag));$autoAction=current(array_filter($j['actions'],fn($a)=>$a['key']===$auto));
    ok($autoAction['decision']==='relink'&&$autoAction['body']['conditions']===$body['conditions'],'Keep retains edited conditions while reviewing deleted-tag rebind');
    $j=single($j,$tagAction);advance($j);$j=single($j,$autoAction);$j['pending']=$autoAction;Store::save($j);$writes=Native::$writes;advance($j);
    ok(Native::$writes===$writes+1&&Native::$rows['automation'][$autoAction['id']]['conditions'][0][0]['value']===[(int)$j['registry']['objects'][$tag]['id']],'Unmapped old condition IDs safely recover via exact frozen-before readback');
    $j['status']='complete';Store::save($j);update_option(Store::CURRENT,$j['id']);$writes=Native::$writes;Engine::run();ok(Native::$writes===$writes,'Completed execution replay performs no additional vendor save');
    // Partial graph: parent missing; surviving edited child; collision in either scope.
    [$defs,$reg]=seed();$parent=array_key_first(array_filter($defs,fn($d)=>$d['kind']==='automation'));
    $childKey=array_key_first(array_filter($defs,fn($d)=>$d['kind']==='sequence_email'&&($d['body']['funnel_ref']??null)===$parent));
    $childID=$reg['objects'][$childKey]['id'];$oldChild=Native::$rows['sequence_email'][$childID];$oldChild['html']='Keep this surviving edited email';Native::$rows['sequence_email'][$childID]=$oldChild;
    unset(Native::$rows['automation'][$reg['objects'][$parent]['id']]);Engine::preview($map,[$childKey=>'keep']);$j=Store::job();
    $parentAction=current(array_filter($j['actions'],fn($a)=>$a['key']===$parent));$childAction=current(array_filter($j['actions'],fn($a)=>$a['key']===$childKey));
    ok($childAction['decision']==='relink'&&$childAction['body']['html']===$oldChild['html'],'Partial graph review retains edited child body while relinking');
    $j=single($j,$parentAction);advance($j);$j=single($j,$childAction);Native::$rows['sequence_email'][9000]=$oldChild;$writes=Native::$writes;
    refuses(function()use(&$j){advance($j);},'Native identity collision after review');
    ok(Native::$writes===$writes&&$j['pending']===null,'New child in retired-parent scope blocks before write');
    unset(Native::$rows['sequence_email'][9000]);Native::$rows['sequence_email'][9001]=Native::encode($childAction['body'],$j['registry']);
    refuses(function()use(&$j){advance($j);},'Native identity collision after review');
    ok(Native::$writes===$writes,'New child in current-parent scope also blocks');unset(Native::$rows['sequence_email'][9001]);
    advance($j);ok(Native::$rows['sequence_email'][$childID]['html']===$oldChild['html']&&Native::$rows['sequence_email'][$childID]['funnel_ref']===$j['registry']['objects'][$parent]['id'],'Only reviewed relationship changes; surviving edited HTML intact');
    $j=single($j,$childAction);$j['pending']=$childAction;Store::save($j);Native::$rows['sequence_email'][9000]=$oldChild;$writes=Native::$writes;
    refuses(function()use(&$j){advance($j);},'Native identity collision after review');
    ok(Native::$writes===$writes&&$j['pending']!==null,'Pending successful write cannot conceal newly ambiguous old-scope child');
    unset(Native::$rows['sequence_email'][9000]);advance($j);Native::$rows['sequence_email'][9000]=$oldChild;
    refuses(fn()=>$range->invoke(null,$j,new Native(),0,1),'Native identity collision after review');
    ok(Native::$writes===$writes,'Final receipt verification rejects late old-scope collision without restoration/deletion');
    [$defs,$reg]=seed();$stepKey=array_key_first(array_filter($defs,fn($d)=>$d['kind']==='step'&&isset($d['body']['settings']['campaign_ref'])));$campaign=$defs[$stepKey]['body']['settings']['campaign_ref'];$stepID=$reg['objects'][$stepKey]['id'];
    Native::$rows['step'][$stepID]['settings']['editor_note']='Retain campaign-step edit';unset(Native::$rows['sequence_email'][$reg['objects'][$campaign]['id']]);Engine::preview($map,[$stepKey=>'keep']);$j=Store::job();$action=current(array_filter($j['actions'],fn($a)=>$a['key']===$stepKey));
    ok($action['decision']==='relink'&&$action['body']['settings']['editor_note']==='Retain campaign-step edit','Missing campaign explicitly relinks an edited surviving step');
    while($j['cursor']<count($j['actions']))advance($j);
    ok(Native::$rows['step'][$stepID]['settings']['campaign_ref']===$j['registry']['objects'][$campaign]['id']&&Native::$rows['step'][$stepID]['settings']['editor_note']==='Retain campaign-step edit','Campaign ID rebuilt with edited settings preserved');
    ok(is_string($range->invoke(null,$j,new Native(),0,count($j['actions']))),'Partial campaign graph final receipts read back exactly');
    [$defs,$reg]=seed();$sequence=array_key_first(array_filter($defs,fn($d)=>$d['kind']==='email_sequence'));$mail=array_key_first(array_filter($defs,fn($d)=>$d['kind']==='email_sequence_mail'&&$d['body']['sequence_ref']===$sequence));$mailID=$reg['objects'][$mail]['id'];$oldMail=Native::$rows['email_sequence_mail'][$mailID];
    unset(Native::$rows['email_sequence'][$reg['objects'][$sequence]['id']]);Engine::preview($map);$j=Store::job();$a=current(array_filter($j['actions'],fn($a)=>$a['key']===$sequence));$child=current(array_filter($j['actions'],fn($a)=>$a['key']===$mail));$j=single($j,$a);advance($j);$j=single($j,$child);Native::$rows['email_sequence_mail'][9000]=$oldMail;$writes=Native::$writes;
    refuses(function()use(&$j){advance($j);},'Native identity collision after review');ok(Native::$writes===$writes,'Independent email-sequence old-parent scope also rejects new children');
    echo json_encode(['assertions'=>$n,'status'=>'PASS','native_SQL'=>false,'transport'=>'none','evidence'=>'Actual supervisor methods; simulated vendor/storage only'])."\n";
}
