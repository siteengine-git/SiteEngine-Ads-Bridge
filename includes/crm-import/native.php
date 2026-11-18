<?php
namespace SiteEngine\AdsBridge\Import;
use RuntimeException;
/** Extracted native configuration read/save adapters from CRM 7df6810. */
final class Native {
    public function __construct(private array $config) {}
    public static function model(string $kind):string {
        return match($kind){'template'=>'FluentCrm\\App\\Models\\Template','campaign'=>'FluentCrm\\App\\Models\\Campaign','sequence_email'=>'FluentCrm\\App\\Models\\FunnelCampaign','email_sequence'=>'FluentCampaign\\App\\Models\\Sequence','email_sequence_mail'=>'FluentCampaign\\App\\Models\\SequenceMail','automation'=>'FluentCrm\\App\\Models\\Funnel','step'=>'FluentCrm\\App\\Models\\FunnelSequence','list'=>'FluentCrm\\App\\Models\\Lists','tag'=>'FluentCrm\\App\\Models\\Tag',default=>throw new RuntimeException('Unknown native kind.')};
    }
    public function fields():array {
        $f=(new \FluentCrm\App\Models\CustomContactField())->getGlobalFields()['fields'];
        if(count($f)!==count(array_unique(array_column($f,'slug'))))throw new RuntimeException('Ambiguous custom-field identities.');return $f;
    }
    public static function portable(array $b,string $kind):array {
        $keys=match($kind){'field'=>['slug','type','field_key','label','options','group'],'list'=>['slug','title','description','is_public'],'tag'=>['slug','title','description']};
        $b=array_intersect_key($b,array_flip($keys));if($kind!=='field')$b['description']=$b['description']??'';
        if($kind==='list')$b['is_public']=(int)$b['is_public'];
        if($kind==='field')$b['field_key']=$b['field_key']??($b['type']==='select-one'?'single-select':$b['type']);return $b;
    }
    public function read(string $kind,$id,array $registry):?array {
        if($kind==='field'){$r=array_values(array_filter($this->fields(),fn($f)=>$f['slug']===$id));return $r?self::portable($r[0],$kind):null;}
        if(in_array($kind,['list','tag'],true)){$c=self::model($kind);$m=$c::find($id);return $m?self::portable($m->toArray(),$kind):null;}
        return $this->current($kind,(int)$id,$registry);
    }
    public function candidates(string $kind,array $b,array $registry):array {
        if($kind==='field')return array_column(array_values(array_filter($this->fields(),fn($f)=>$f['slug']===$b['slug'])),'slug');
        $c=self::model($kind);$q=$c::query();
        if(in_array($kind,['list','tag'],true))$q->where(static function($q)use($b){$q->where('slug',$b['slug'])->orWhere('title',$b['title']);});
        else $q->where($kind==='template'?'post_title':'title',$b['title']);
        if($kind==='sequence_email')$q->where('parent_id',$registry['objects'][$b['funnel_ref']]['id']??-1);
        if($kind==='step')$q->where('funnel_id',$registry['objects'][$b['funnel_ref']]['id']??-1);
        if($kind==='email_sequence_mail')$q->where('parent_id',$registry['objects'][$b['sequence_ref']]['id']??-1);
        $rows=$q->limit(3)->get();return array_map(fn($m)=>(int)$m->getKey(),iterator_to_array($rows));
    }
    public function saveDependency(string $kind,array $b,$id):string {
        if($kind==='field'){$fields=$this->fields();$found=false;foreach($fields as &$f)if($f['slug']===$b['slug']){$f=array_replace($f,$b);$found=true;}unset($f);if(!$found)$fields[]=$b;(new \FluentCrm\App\Models\CustomContactField())->saveGlobalFields($fields);return $b['slug'];}
        $c=self::model($kind);$m=$id?$c::findOrFail($id):new $c();$m->fill($b);$m->save();return (string)$m->id;
    }
    private function reverse(array $registry): array {return array_map(fn($x)=>$x['id'],$registry['objects']);}
    private function ref(int $id,array $registry,string $kind): string {foreach($registry['objects'] as $key=>$r)if($r['kind']===$kind&&(int)$r['id']===$id)return $key;return 'unmapped:'.$id;}
    public function current(string $kind,int $id,array $registry): ?array {
        if($kind==='template'){$m=\FluentCrm\App\Models\Template::find($id);if(!$m)return null;return ['title'=>$m->post_title,'html'=>$m->post_content,'subject'=>get_post_meta($id,'_email_subject',true),'status'=>$m->post_status,'design'=>get_post_meta($id,'_design_template',true),'excerpt'=>$m->post_excerpt,'edit_type'=>get_post_meta($id,'_edit_type',true),'template_config'=>get_post_meta($id,'_template_config',true),'footer_settings'=>get_post_meta($id,'_footer_settings',true)];}
        if($kind==='campaign'||$kind==='sequence_email'){$class=$kind==='campaign'?\FluentCrm\App\Models\Campaign::class:\FluentCrm\App\Models\FunnelCampaign::class;$m=$class::find($id);if(!$m)return null;$a=['title'=>$m->title,'html'=>$m->email_body,'subject'=>$m->subject,'status'=>$m->status,'scheduled'=>$m->scheduled_at?:null,'design'=>$m->design_template,'settings'=>$m->settings];if($kind==='sequence_email')$a['funnel_ref']=$this->ref((int)$m->parent_id,$registry,'automation');return $a;}
        if(in_array($kind,['email_sequence','email_sequence_mail'],true)){$class=$kind==='email_sequence'?\FluentCampaign\App\Models\Sequence::class:\FluentCampaign\App\Models\SequenceMail::class;$m=$class::find($id);if(!$m)return null;$b=['title'=>$m->title,'status'=>$m->status,'design'=>$m->design_template,'settings'=>$m->settings];if($kind==='email_sequence_mail')$b+=['subject'=>$m->email_subject,'preheader'=>$m->email_pre_header??'','html'=>$m->email_body,'sequence_ref'=>$this->ref((int)$m->parent_id,$registry,'email_sequence'),'delay'=>(int)$m->delay];return $b;}
        if($kind==='automation'){$m=\FluentCrm\App\Models\Funnel::find($id);$settings=$m?$m->settings:[];if(($settings['form_ref']??null)===$this->config['bindings']['form.primary'])$settings['form_ref']='form.primary';return $m?['title'=>$m->title,'status'=>$m->status,'trigger'=>$m->trigger_name,'settings'=>$settings,'conditions'=>Format::conditionMap($m->conditions?:[],$registry,false),'description'=>$m->getMeta('description','')]:null;}
        if($kind==='step'){$m=\FluentCrm\App\Models\FunnelSequence::find($id);if(!$m)return null;$settings=$m->settings;if(isset($settings['reference_campaign'])){$settings['campaign_ref']=$this->ref((int)$settings['reference_campaign'],$registry,'sequence_email');unset($settings['reference_campaign']);}return ['title'=>$m->title,'action'=>$m->action_name,'status'=>$m->status,'type'=>$m->type,'funnel_ref'=>$this->ref((int)$m->funnel_id,$registry,'automation'),'settings'=>$settings,'conditions'=>Format::conditionMap($m->conditions?:[],$registry,false),'parent_ref'=>$m->parent_id?$this->ref((int)$m->parent_id,$registry,'step'):null,'sequence'=>(int)$m->sequence,'delay'=>(int)$m->delay,'c_delay'=>(int)$m->c_delay];}
        throw new RuntimeException('unknown_native_kind');
    }
    private function collisionCount(string $kind,string $title): int {
        if($kind==='template')return \FluentCrm\App\Models\Template::where('post_title',$title)->count();
        $class=match($kind){'email_sequence'=>\FluentCampaign\App\Models\Sequence::class,'email_sequence_mail'=>\FluentCampaign\App\Models\SequenceMail::class,'campaign'=>\FluentCrm\App\Models\Campaign::class,'sequence_email'=>\FluentCrm\App\Models\FunnelCampaign::class,'automation'=>\FluentCrm\App\Models\Funnel::class,'step'=>\FluentCrm\App\Models\FunnelSequence::class};return $class::where('title',$title)->count();
    }
    public function save(string $kind,array $b,?int $id,array $registry): int {
        if($kind==='template'){$a=['post_title'=>$b['title'],'post_content'=>$b['html'],'post_excerpt'=>$b['excerpt'],'post_status'=>'draft','post_type'=>fluentcrmTemplateCPTSlug()];if($id){$a['ID']=$id;$a['edit_date']=true;}$id=$id?wp_update_post(wp_slash($a),true):wp_insert_post(wp_slash($a),true);if(is_wp_error($id))throw new RuntimeException('template_save_failed');update_post_meta($id,'_email_subject',wp_slash($b['subject']));update_post_meta($id,'_edit_type','html');update_post_meta($id,'_design_template','raw_html');update_post_meta($id,'_template_config',wp_slash($b['template_config']));update_post_meta($id,'_footer_settings',wp_slash($b['footer_settings']));return (int)$id;}
        if($kind==='campaign'||$kind==='sequence_email'){$class=$kind==='campaign'?\FluentCrm\App\Models\Campaign::class:\FluentCrm\App\Models\FunnelCampaign::class;$a=['title'=>$b['title'],'email_body'=>$b['html'],'email_subject'=>$b['subject'],'status'=>'draft','scheduled_at'=>null,'design_template'=>'raw_html','settings'=>$b['settings']];if($kind==='sequence_email')$a['parent_id']=$registry['objects'][$b['funnel_ref']]['id'];$m=$id?$class::findOrFail($id):new $class();$m->fill($a);$m->save();return (int)$m->id;}
        if(in_array($kind,['email_sequence','email_sequence_mail'],true)){$class=$kind==='email_sequence'?\FluentCampaign\App\Models\Sequence::class:\FluentCampaign\App\Models\SequenceMail::class;$m=$id?$class::findOrFail($id):new $class();$a=['title'=>$b['title'],'status'=>'draft','design_template'=>'raw_html','settings'=>$b['settings']];if($kind==='email_sequence_mail')$a+=['email_subject'=>$b['subject'],'email_pre_header'=>$b['preheader'],'email_body'=>$b['html'],'parent_id'=>$registry['objects'][$b['sequence_ref']]['id'],'scheduled_at'=>null];$m->fill($a);$m->save();return (int)$m->id;}
        if($kind==='automation'){$settings=$b['settings'];$settings['form_ref']=$this->config['bindings']['form.primary'];$m=$id?\FluentCrm\App\Models\Funnel::findOrFail($id):new \FluentCrm\App\Models\Funnel();$m->fill(['title'=>$b['title'],'status'=>'draft','trigger_name'=>$b['trigger'],'settings'=>$settings,'conditions'=>Format::conditionMap($b['conditions'],$registry,true)]);$m->save();$m->updateMeta('description',$b['description']);return (int)$m->id;}
        if($kind==='step'){$settings=$b['settings'];if(isset($settings['campaign_ref'])){$settings['reference_campaign']=$registry['objects'][$settings['campaign_ref']]['id'];unset($settings['campaign_ref']);}$m=$id?\FluentCrm\App\Models\FunnelSequence::findOrFail($id):new \FluentCrm\App\Models\FunnelSequence();$m->fill(['title'=>$b['title'],'action_name'=>$b['action'],'status'=>'draft','type'=>'action','funnel_id'=>$registry['objects'][$b['funnel_ref']]['id'],'parent_id'=>empty($b['parent_ref'])?null:$registry['objects'][$b['parent_ref']]['id'],'settings'=>$settings,'conditions'=>Format::conditionMap($b['conditions'],$registry,true),'sequence'=>$b['sequence'],'delay'=>$b['delay'],'c_delay'=>$b['c_delay']]);$m->save();return (int)$m->id;}
        throw new RuntimeException('unknown_native_kind');
    }
}
