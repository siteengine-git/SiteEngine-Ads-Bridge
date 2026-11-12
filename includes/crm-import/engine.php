<?php
namespace SiteEngine\AdsBridge\Import;
use RuntimeException;
use Throwable;

/** Destination-local options, explicitly non-autoloaded; no public upload paths. */
final class Store {
    const CURRENT='seab_crm_import_current_v1';
    const REGISTRY='seab_crm_import_registry_v1';
    public static function read(string $key,$default=null) {
        global $wpdb;$v=$wpdb->get_var($wpdb->prepare("SELECT option_value FROM `{$wpdb->options}` WHERE option_name=%s",$key));
        if($wpdb->last_error!=='')throw new RuntimeException('Import journal read failed.');
        return $v===null?$default:maybe_unserialize($v);
    }
    public static function write(string $key,$v):void {
        wp_cache_delete($key,'options');wp_cache_delete('notoptions','options');update_option($key,$v,false);
        if(JSON::hash(self::read($key))!==JSON::hash($v))throw new RuntimeException('Import journal persistence is unconfirmed. Do not repeat uncertain writes.');
    }
    public static function job():?array {$id=self::read(self::CURRENT);if(!$id)return null;$j=self::read('seab_crm_import_job_'.$id);if(!is_array($j)||($j['id']??null)!==$id)throw new RuntimeException('Import journal is invalid.');return $j;}
    public static function save(array &$j):void {
        $old=self::read('seab_crm_import_job_'.$j['id']);
        if($old!==null&&($old['revision']??null)!==$j['revision'])throw new RuntimeException('Import state changed. Reload before continuing.');
        $j['revision']++;self::write('seab_crm_import_job_'.$j['id'],$j);
    }
}

final class Engine {
    public static bool $executing=false;
    public static function sourcePin():string {
        $pins=[];foreach(glob(__DIR__.'/*') as $p)if(is_file($p)&&in_array(pathinfo($p,PATHINFO_EXTENSION),['php','json','js'],true))$pins['import/'.basename($p)]=hash_file('sha256',$p);
        foreach(['settings','fields','setup','config','coordination','inquiries'] as $name){$p=dirname(__DIR__).'/class-siteengine-ads-bridge-'.$name.'.php';if(!is_file($p))throw new RuntimeException('Importer dependency source is unavailable.');$pins[$name]=hash_file('sha256',$p);}
        return JSON::hash($pins);
    }
    private static function owner(array $j):void {
        if(!current_user_can('manage_options')||!current_user_can('unfiltered_html'))throw new RuntimeException('Administrator and unfiltered HTML permissions are required.');
        if($j['actor']!==get_current_user_id())throw new RuntimeException('This import belongs to another administrator. Its progress is preserved.');
        if($j['destination']!==seab_configuration_capability()['destination'])throw new RuntimeException('The import belongs to another destination.');
        if(in_array($j['status'],['running','uncertain'],true)&&!Recovery::compatible($j))throw new RuntimeException('Saved importer source/plan is not a reviewed recovery predecessor. Preserve the original journal and recover with its pinned importer; no operation was rebound.');
    }
    public static function readOnly(callable $f) {
        return \SiteEngine_Ads_Bridge_Setup::with_executor(static function()use($f){
            $held=\SiteEngine_Ads_Bridge_Coordination::enter_setup(true);if(!$held['ok'])throw new RuntimeException(\SiteEngine_Ads_Bridge_Setup::blocker_message($held['reason']));
            try{return $f();}finally{if(!\SiteEngine_Ads_Bridge_Coordination::release())throw new RuntimeException('Import lock release is unconfirmed.');}
        });
    }
    public static function upload(string $path):array {
        $package=Archive::read($path);
        return self::readOnly(static function()use($package,$path){$old=Store::job();if($old&&in_array($old['status'],['running','uncertain'],true))throw new RuntimeException('An existing import must complete or recover first.');
            $j=['id'=>bin2hex(random_bytes(16)),'revision'=>0,'actor'=>get_current_user_id(),'destination'=>seab_configuration_capability()['destination'],'source_pin'=>self::sourcePin(),
                'upload_sha256'=>hash_file('sha256',$path),'package'=>$package,'status'=>'uploaded','mappings'=>[],'choices'=>[],'actions'=>[],'cursor'=>0,'pending'=>null,'receipts'=>[],'writes'=>0,'message'=>'ZIP validated. Choose destination mappings.'];
            Store::save($j);Store::write(Store::CURRENT,$j['id']);return self::view($j);
        });
    }
    public static function mappings(array $m):array {
        global $wpdb;$id=filter_var($m['form']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
        $form=$id?$wpdb->get_row($wpdb->prepare("SELECT id,form_fields FROM `{$wpdb->prefix}fluentform_forms` WHERE id=%d",$id),ARRAY_A):null;
        if($wpdb->last_error||!$form)throw new RuntimeException('Choose an existing destination Fluent Form.');
        $fields=json_decode($form['form_fields'],true,64,JSON_THROW_ON_ERROR);$selectors=[];
        $walk=static function($v)use(&$walk,&$selectors){if(!is_array($v))return;if(isset($v['attributes']['name'])&&is_string($v['attributes']['name']))$selectors[]=$v['attributes']['name'];foreach($v as $x)if(is_array($x))$walk($x);};$walk($fields);
        $selector=(string)($m['form_service']??'');if(!in_array($selector,$selectors,true))throw new RuntimeException('Choose the actual Service selector from this form.');
        foreach(['service_field','outcome_field'] as $key)if(!is_string($m[$key]??null)||!preg_match('/^[a-z][a-z0-9_]{0,100}$/D',$m[$key]))throw new RuntimeException('Choose an existing field or its displayed new-field identity.');
        $label=trim((string)($m['service_label']??''));if($label===''||strlen($label)>200)throw new RuntimeException('Choose or enter the destination service label.');
        return ['form'=>(int)$id,'form_service'=>$selector,'service_field'=>$m['service_field'],'outcome_field'=>$m['outcome_field'],'service_label'=>$label,'form_hash'=>JSON::hash($form)];
    }
    private static function definitions(array $j):array {
        $defs=$j['package']['dependencies'];$defs['field.service_type']['body']['slug']=$j['mappings']['service_field'];$defs['field.outcome_reason']['body']['slug']=$j['mappings']['outcome_field'];
        // Normal Ads provisioning definitions join the same reviewed operation.
        // They are local trusted definitions, never accepted from a source archive.
        foreach(\SiteEngine_Ads_Bridge_Fields::required() as $f){$f['field_key']=$f['type'];$defs['ads.field.'.$f['slug']]=['kind'=>'field','body'=>$f];}
        return $defs+Contract::order($j['package']['objects']);
    }
    private static function native(array $j):Native {return new Native(['bindings'=>['form.primary'=>$j['mappings']['form']]]);}
    private static function same($a,$b):bool {return JSON::hash($a)===JSON::hash($b);}
    /** Only the supported portable relationships, never title/contact matching. */
    private static function references(array $body):array {
        $refs=[];foreach(['funnel_ref','parent_ref','sequence_ref'] as $key)if(!empty($body[$key]))$refs[]=$body[$key];
        if(isset($body['settings']['campaign_ref']))$refs[]=$body['settings']['campaign_ref'];
        foreach($body['conditions']??[] as $group)foreach($group as $condition){
            [$provider,$field]=$condition['source'];
            if($provider==='custom_fields')$refs[]=$field;
            elseif($provider==='subscriber'&&in_array($field,['tags','lists'],true))foreach((array)$condition['value'] as $ref)$refs[]=$ref;
        }
        return array_values(array_unique($refs));
    }
    /** Completed receipts remain immutable; reusing an upload creates a new operation. */
    private static function reviewIdentity(array $j):array {
        if($j['status']!=='complete')return $j;
        $j['previous_import']=$j['id'];$j['id']=bin2hex(random_bytes(16));$j['revision']=0;$j['status']='uploaded';$j['cursor']=0;$j['receipts']=[];$j['actions']=[];$j['pending']=null;$j['writes']=0;unset($j['plan_hash']);return $j;
    }
    /** Actual conflicts alone require Keep/Replace. Compatible dependencies are adopted. */
    public static function decision(string $key,array $d,?array $actual,?array $known,string $choice=''):array {
        $desired=$d['body'];$kind=$d['kind'];$decision=$actual===null?'create':'adopt';$conflict=false;
        if($actual!==null){
            if(isset($actual['status'])&&$actual['status']!=='draft')throw new RuntimeException('Active native object must be handled in FluentCRM before import: '.$key);
            if($kind==='field'&&($actual['type']!==$desired['type']||($desired['type']==='select-one'&&($actual['options']??[])!==$desired['options']))){
                if($key!=='field.service_type'||!in_array($actual['type'],['text','select-one'],true))throw new RuntimeException('Incompatible custom-field definition: '.$key.'. Existing values are preserved.');
            }
            if(self::same($actual,$desired))$decision='reuse';
            elseif($known&&self::same($actual,$known['body'])){
                if(($known['source_hash']??null)===JSON::hash($desired)){$decision='reuse';$desired=$actual;}
                elseif(JSON::hash($known['body'])!==($known['source_hash']??null)){
                    // Keep/adopt accepts the destination body, not future overwrites.
                    $conflict=true;$decision='conflict';
                    if($choice==='keep'){$decision='reuse';$desired=$actual;$conflict=false;}
                    elseif($choice==='replace'){$decision='update';$conflict=false;}
                }else $decision='update';
            }elseif(!$known&&in_array($kind,['field','list','tag'],true)){$decision='adopt';$desired=$actual;}
            else {$conflict=true;if($choice==='keep'){$decision='reuse';$desired=$actual;$conflict=false;}elseif($choice==='replace'){$decision='update';$conflict=false;}else $decision='conflict';}
            if($decision==='update'&&$kind==='field'&&!str_starts_with($desired['slug'],'se_crm_'))throw new RuntimeException('Shared custom-field replacement is not supported: '.$key);
        }
        return ['key'=>$key,'kind'=>$kind,'decision'=>$decision,'conflict'=>$conflict,'body'=>$desired,'source_hash'=>JSON::hash($d['body']),'before'=>$actual];
    }
    public static function preview(array $input,array $choices=[]):array {
        return self::readOnly(static function()use($input,$choices){$j=Store::job();if(!$j)throw new RuntimeException('Upload a configuration ZIP first.');self::owner($j);
            if(in_array($j['status'],['running','uncertain'],true))throw new RuntimeException('Recover the current import before changing its plan.');
            $j=self::reviewIdentity($j);$j['source_pin']=self::sourcePin();Contract::validate($j['package']);
            $j['draft_mappings']=$input;$j['choices']=$choices;Store::save($j);Store::write(Store::CURRENT,$j['id']);
            $j['mappings']=self::mappings($input);$j['registry']=Store::read(Store::REGISTRY,['objects'=>[]]);$native=self::native($j);
            // Freeze old native identities for exact pre-write checks. This is a
            // new review, not permission to rewrite any completed journal.
            $j['review_bindings']=['objects'=>array_map(static fn($r)=>['kind'=>$r['kind'],'id'=>$r['id']],$j['registry']['objects'])];
            $defs=self::definitions($j);new Session(array_unique(array_column($defs,'kind')));$actions=[];$recreated=[];
            foreach($defs as $key=>$d){$known=$j['registry']['objects'][$key]??null;$id=$known['id']??null;
                if($known&&$known['kind']!==$d['kind'])throw new RuntimeException('Managed identity kind changed: '.$key);
                if($known&&$d['kind']!=='field'&&filter_var($id,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])===false)throw new RuntimeException('Managed native identity is invalid: '.$key.'. Preserve the registry and establish its original ID before review.');
                $ids=$native->candidates($d['kind'],$d['body'],$j['registry']);
                if(count($ids)>1||($id!==null&&array_filter($ids,fn($other)=>(string)$other!==(string)$id)))throw new RuntimeException('Multiple native objects match '.$key.'. Resolve the ambiguity in FluentCRM.');
                if($known&&$d['kind']==='field'&&(string)$id!==$d['body']['slug'])throw new RuntimeException('Managed field mapping changed: '.$key.'. Existing definitions and values are preserved.');
                if($id===null)$id=$ids[0]??null;
                $actual=$id===null?null:$native->read($d['kind'],$id,$j['registry']);$replaces=null;
                if($known&&$actual===null){$replaces=$id;$id=null;if($d['kind']!=='field')$recreated[]=$key;}
                $a=self::decision($key,$d,$actual,$known,$choices[$key]??'');$a['id']=$id;$actions[]=$a;
                if($replaces!==null)$actions[array_key_last($actions)]['replaces_id']=$replaces;
                if($id!==null)$j['registry']['objects'][$key]=['kind'=>$d['kind'],'id'=>$id,'body'=>$actual,'source_hash'=>$known['source_hash']??null];
            }
            foreach($actions as &$a){
                $refs=array_values(array_intersect(self::references($a['body']),$recreated));
                if($a['before']!==null&&$a['decision']!=='conflict'&&$refs){
                    $a['rebind_refs']=$refs;
                    if(in_array($a['decision'],['reuse','adopt'],true))$a['decision']='relink';
                }
            }unset($a);
            // A final exact settings action is part of the protected operation.
            $c=\SiteEngine_Ads_Bridge_Config::instance();if($c->external_override_active())throw new RuntimeException('An external Ads configuration is authoritative. Use Ads Bridge > Migration to deliberately select WordPress settings before importing. Your ZIP and mappings remain saved.');
            $before=\SiteEngine_Ads_Bridge_Settings::get();$after=\SiteEngine_Ads_Bridge_Settings::merge(\SiteEngine_Ads_Bridge_Settings::defaults(),$before?:$c->all());
            $after['fluent_forms']['form_id']=$j['mappings']['form'];$after['fluent_forms']['fields']['service_type']=$j['mappings']['form_service'];
            $after['fluentcrm']['lifecycle_automation']['enabled']=false;$after['fluentcrm']['lifecycle_automation']['unqualified_reason_field']=$j['mappings']['outcome_field'];
            $after['google_sheets']['enabled']=false;foreach(['all_leads','qualified_leads','won_jobs','lost_leads'] as $k)$after['exports'][$k]['enabled']=false;
            // Titles are resolved from the actual imported/adopted identities at dispatch.
            $actions[]=['key'=>'ads.settings','kind'=>'settings','decision'=>'update','body'=>$after,'before'=>$before,'source_hash'=>JSON::hash($after),'id'=>'seab_settings_v2'];
            $j['actions']=$actions;$j['status']='review';$j['cursor']=0;$j['receipts']=[];$j['pending']=null;$j['writes']=0;
            $j['message']='Review inactive content and destination mappings. Import never enables lifecycle automation, Sheets, exports or enrollment.';
            $j['plan_hash']=JSON::hash([$j['upload_sha256'],$j['mappings'],$actions,$j['source_pin'],$j['review_bindings']]);Store::save($j);return self::view($j);
        });
    }
    public static function begin(string $pin):array {
        $j=Store::job();if(!$j)throw new RuntimeException('No import to begin.');self::owner($j);
        if($j['status']!=='review'||!hash_equals($j['plan_hash'],$pin))throw new RuntimeException('Review the current import before starting.');
        if($j['source_pin']!==self::sourcePin())throw new RuntimeException('Importer changed since review. Review changes again; your ZIP and mappings are retained.');
        foreach($j['actions'] as $a)if($a['decision']==='conflict')throw new RuntimeException('Choose Keep or Replace for the displayed conflicts.');
        // Normal product setup, performed only after Import, never via fixture preparation.
        \SiteEngine_Ads_Bridge_Setup::initialize_storage();
        return self::readOnly(static function()use($j){$current=Store::job();if($current['revision']!==$j['revision'])throw new RuntimeException('Import review changed.');$j['status']='running';Store::save($j);return self::view($j);});
    }
    private static function settingsBody(array $j,array $a,Native $native):array {
        $b=$a['body'];foreach(['new','qualified','unqualified','won'] as $role){$m=$j['registry']['objects']['tag.'.$role]??null;if(!$m)throw new RuntimeException('Lifecycle identity missing: '.$role);$actual=$native->read('tag',$m['id'],$j['registry']);$b['fluentcrm']['tags']['lifecycle'][$role]=$actual['title'];}
        foreach(['customers'=>'list.customers','future_customers'=>'list.future-customers','do_not_contact'=>'list.dnc'] as $role=>$key){$m=$j['registry']['objects'][$key];$b['fluentcrm']['lists'][$role]=$native->read('list',$m['id'],$j['registry'])['title'];}
        return \SiteEngine_Ads_Bridge_Settings::sanitize_config($b);
    }
    private static function readAction(array $j,array $a,Native $native,bool $review=false) {
        $bindings=$review?($j['review_bindings']??$j['registry']):$j['registry'];
        return $a['kind']==='settings'?Store::read('seab_settings_v2',[]):($a['id']===null?null:$native->read($a['kind'],$a['id'],$bindings));
    }
    private static function accept(array &$j,array $a,$body):void {
        if($a['kind']!=='settings')$j['registry']['objects'][$a['key']]=['kind'=>$a['kind'],'id'=>$a['id'],'body'=>$body,'source_hash'=>$a['source_hash']];
        $j['receipts'][$a['key']]=['id'=>$a['id'],'kind'=>$a['kind'],'body_hash'=>JSON::hash($body),'decision'=>$a['decision']];$j['cursor']++;$j['pending']=null;Store::save($j);
    }
    /** One native operation at a time; receipt before moving to the next identity. */
    private static function one(array &$j,Native $native,Session $session):void {
        $a=$j['pending']??$j['actions'][$j['cursor']];$session->check();
        if($a['kind']==='settings')$a['body']=self::settingsBody($j,$a,$native);
        if($j['pending']!==null){
            if($a['id']===null)throw new RuntimeException('Uncertain create has no returned native ID: '.$a['key'].'. Preserve the journal; do not replay creation.');
            try{$actual=self::readAction($j,$a,$native);}
            catch(RuntimeException $e){
                // Old condition IDs can be unmapped after their parents were
                // recreated. Only exact frozen-before readback may permit retry.
                if(empty($a['rebind_refs']))throw $e;
                if($e->getMessage()!=='condition_dependency_unmapped')throw $e;
                $actual=null;
            }
            if(self::same($actual,$a['body'])){self::accept($j,$a,$actual);return;}
            if(!in_array($a['decision'],['update','relink'],true)||!self::same(self::readAction($j,$a,$native,true),$a['before']))throw new RuntimeException('Uncertain native operation requires exact readback: '.$a['key']);
        }else{
            $actual=self::readAction($j,$a,$native,true);
            if(!self::same($actual,$a['before']))throw new RuntimeException('Destination changed since review: '.$a['key']);
            if(isset($a['replaces_id'])&&$native->read($a['kind'],$a['replaces_id'],$j['review_bindings'])!==null)throw new RuntimeException('Deleted managed identity reappeared since review: '.$a['key']);
            if($a['kind']!=='settings'){$ids=$native->candidates($a['kind'],$a['body'],$j['registry']);if(array_filter($ids,fn($other)=>$a['id']===null||(string)$other!==(string)$a['id']))throw new RuntimeException('Native identity collision after review: '.$a['key']);}
            if(self::same($actual,$a['body'])&&empty($a['rebind_refs'])){self::accept($j,$a,$actual);return;}
            if($a['id']===null&&$a['kind']!=='settings'&&$native->candidates($a['kind'],$a['body'],$j['registry']))throw new RuntimeException('New native collision after review: '.$a['key']);
            $j['pending']=$a;Store::save($j);
        }
        if(!in_array($a['decision'],['reuse','adopt'],true)){
            $session->check();
            if($a['kind']==='settings')\SiteEngine_Ads_Bridge_Settings::save($a['body']);
            elseif(in_array($a['kind'],['field','list','tag'],true))$a['id']=$native->saveDependency($a['kind'],$a['body'],$a['id']);
            else $a['id']=$native->save($a['kind'],$a['body'],$a['id']===null?null:(int)$a['id'],$j['registry']);
            $j['writes']++;$j['pending']=$a;Store::save($j);$session->check();
        }
        $actual=self::readAction($j,$a,$native);if(!self::same($actual,$a['body']))throw new RuntimeException('Native readback differs: '.$a['key']);
        self::accept($j,$a,$actual);
    }
    private static function verifyRange(array $j,Native $native,int $start,int $end):string {
        $hashes=[];for($i=$start;$i<$end;$i++){$a=$j['actions'][$i];$r=$j['receipts'][$a['key']]??null;if(!$r)throw new RuntimeException('Missing native receipt.');$a['id']=$r['id'];$actual=self::readAction($j,$a,$native);
            if(JSON::hash($actual)!==$r['body_hash'])throw new RuntimeException('Completed import object changed: '.$a['key']);$hashes[$a['key']]=$r['body_hash'];}return JSON::hash($hashes);
    }
    public static function run():array {
        return \SiteEngine_Ads_Bridge_Setup::with_executor(static function(){
            $j=Store::job();if(!$j)throw new RuntimeException('No current import.');self::owner($j);
            if(!in_array($j['status'],['running','uncertain'],true))return self::view($j);
            if(self::mappings($j['mappings'])!==$j['mappings'])throw new RuntimeException('Destination form or mappings changed.');
            $native=self::native($j);$groups=(int)ceil(count($j['actions'])/8);$steps=[];
            for($g=0;$g<$groups;$g++)$steps['group.'.$g]=['kind'=>'update','intent_hash'=>JSON::hash(array_slice($j['actions'],$g*8,8))];
            $request=seab_configuration_capability()+['operation_id'=>'ads-import.'.$j['id'],'plan_hash'=>$j['plan_hash'],'steps'=>$steps];$failure=null;
            self::$executing=true;
            try{$result=seab_configuration_with_batch($request,static function($batch)use(&$j,$native,$groups,&$failure){
                try{$session=new Session(array_unique(array_column(self::definitions($j),'kind')));$deadline=microtime(true)+10;$did=false;
                    for($g=0;$g<$groups;$g++){$start=$g*8;$end=min($start+8,count($j['actions']));
                        if($j['cursor']<$start)break;
                        if($j['cursor']<$end&&$did)break;$wasIncomplete=$j['cursor']<$end;
                        $batch->step('group.'.$g,static function()use(&$j,$native,$session,$end,$deadline){while($j['cursor']<$end){if(microtime(true)>$deadline)throw new RuntimeException('Import batch time bound reached; progress retained.');self::one($j,$native,$session);}},
                            static function()use(&$j,$native,$session,$start,$end){$session->check();if($j['cursor']<$end){
                                // Inner pending intent is independently resolved by one(), never blind replay.
                                self::verifyRange($j,$native,0,$j['cursor']);return ['state'=>'before'];
                            }return ['state'=>'verified','hash'=>self::verifyRange($j,$native,$start,$end)];});
                        if($wasIncomplete)$did=true;
                    }
                    if($j['cursor']===count($j['actions']))$batch->finish(static function()use($j,$native,$session){$session->check();return self::verifyRange($j,$native,0,count($j['actions']));});
                }catch(Throwable $e){$failure=$e;throw $e;}
            });
            if($failure){$j['status']='uncertain';$j['message']=$failure->getMessage();Store::save($j);throw $failure;}
            if($result['status']==='complete'){
                $j['registry']['service_label']=$j['mappings']['service_label'];$j['registry']['views']=$j['package']['views'];$j['registry']['execution_enabled']=false;Store::write(Store::REGISTRY,$j['registry']);$j['status']='complete';$j['message']='Configuration imported. Native objects remain editable and inactive. Automation, Sheets and exports remain disabled.';Store::save($j);
            }elseif($result['reason_code']==='coordination_batch_incomplete'){
                if($j['status']==='uncertain'){$j['status']='running';$j['message']='Saved operation read back; continuing the original import in bounded batches.';Store::save($j);}
            }else{
                $j['status']='uncertain';$j['message']=$result['reason_code'];Store::save($j);throw new RuntimeException($result['reason_code']);
            }
            return self::view($j);
            } finally {self::$executing=false;}
        });
    }
    public static function view(?array $j=null):array {
        $j??=Store::job();if(!$j)return ['status'=>'empty'];
        $actions=[];foreach($j['actions'] as $a){$changed=[];if(is_array($a['before']))foreach($a['body'] as $key=>$value)if(JSON::hash($a['before'][$key]??null)!==JSON::hash($value))$changed[]=$key;
            $actions[]=['key'=>$a['key'],'kind'=>$a['kind'],'title'=>$a['body']['title']??$a['body']['label']??$a['key'],'current_title'=>$a['before']['title']??$a['before']['label']??null,'changed_fields'=>$changed,'decision'=>$a['decision'],'id'=>$j['receipts'][$a['key']]['id']??$a['id'],'replaces_id'=>$a['replaces_id']??null,'rebind_refs'=>$a['rebind_refs']??[]];}
        return ['id'=>$j['id'],'revision'=>$j['revision'],'status'=>$j['status'],'message'=>$j['message'],'mappings'=>$j['draft_mappings']??$j['mappings'],'choices'=>$j['choices'],'actions'=>$actions,'cursor'=>$j['cursor'],'total'=>count($j['actions']),'writes'=>$j['writes'],'plan_hash'=>$j['plan_hash']??null,'package_revision'=>$j['package']['configuration_revision'],'upload_sha256'=>$j['upload_sha256']];
    }
}
