<?php
/** Normal product setup. No marketing-content importer or delivery side effects. */
defined('ABSPATH') || exit;
final class SiteEngine_Ads_Bridge_Product_Setup {
    /** The issuer's published policy identity, never a locally invented grant. */
    public static function policy(): string {
        return is_callable(['SiteEngine_Consent_Authority','current_eligibility']) && defined('SE_CONSENT_POLICY_VERSION')
            ? (string) SE_CONSENT_POLICY_VERSION : '';
    }
    public static function term_requirements(array $data): array {
        $out=[];
        foreach (['customers'=>'Customers','future_customers'=>'Future Customers','do_not_contact'=>'DNC'] as $role=>$default)
            $out[]=['kind'=>'list','role'=>$role,'title'=>trim((string)($data['fluentcrm']['lists'][$role]??''))?:$default];
        foreach (['new'=>'New','qualified'=>'Qualified','unqualified'=>'Unqualified','won'=>'Won'] as $role=>$default)
            $out[]=['kind'=>'tag','role'=>$role,'title'=>trim((string)($data['fluentcrm']['tags']['lifecycle'][$role]??''))?:$default];
        // Outcome summary only: Lost is distinct from Unqualified and is not a fifth lifecycle state.
        $out[] = ['kind'=>'tag','role'=>'lost','purpose'=>'outcome_summary','title'=>'Lost'];
        return $out;
    }
    /**
     * Retain a compatible legacy reason field; never repurpose a real customer rating.
     * Selection is read-only. The ordinary setup handler saves the resulting mapping.
     */
    public static function reason_field(): string {
        $configured = SiteEngine_Ads_Bridge_Config::instance()->get(
            'fluentcrm.lifecycle_automation.unqualified_reason_field', 'se_ads_unqualified_reason'
        );
        if (!is_string($configured)) {
            throw new RuntimeException('Unqualified Reason field slug is invalid.');
        }
        if ($configured !== 'customer_rating') {
            return $configured;
        }
        if (!class_exists('FluentCrm\\App\\Models\\CustomContactField')) {
            throw new RuntimeException('FluentCRM custom-field manager unavailable.');
        }
        $manager = new \FluentCrm\App\Models\CustomContactField();
        $global = $manager->getGlobalFields();
        $fields = $global['fields'] ?? [];
        if (!is_array($fields)) {
            throw new RuntimeException('Malformed existing custom-field registry.');
        }
        $legacy = array_values(array_filter($fields, static fn($field) =>
            is_array($field) && ($field['slug'] ?? null) === 'customer_rating'
        ));
        // Duplicate/malformed registries still fail the complete provisioning preflight.
        if (count($legacy) === 1 && ($legacy[0]['type'] ?? null) === 'select-one' &&
            array_values((array)($legacy[0]['options'] ?? [])) === SiteEngine_Ads_Bridge_Lifecycle::unqualified_reason_values()) {
            return 'customer_rating';
        }
        return 'se_ads_unqualified_reason';
    }
    /** One complete setup contract, including connector storage but no live connector actions. */
    public static function field_requirements( ?string $reason_field = null ): array {
        if (!class_exists('SiteEngine\\AdsBridge\\Connectors\\FieldEdge\\CRMProjection')) {
            throw new RuntimeException('FieldEdge CRM definition contract is unavailable. No definitions were created.');
        }
        return array_merge(
            SiteEngine_Ads_Bridge_Fields::required($reason_field ?? self::reason_field()),
            \SiteEngine\AdsBridge\Connectors\FieldEdge\CRMProjection::definitions()
        );
    }
    /** Preflight every definition before the first vendor write; adopt unique existing identities. */
    public static function crm(bool $apply=false): array {
        $data=SiteEngine_Ads_Bridge_Config::instance()->all();$plan=[];$seen=[];
        foreach(self::term_requirements($data) as $term){
            $class=$term['kind']==='list'?'FluentCrm\\App\\Models\\Lists':'FluentCrm\\App\\Models\\Tag';
            if(!class_exists($class))throw new RuntimeException('FluentCRM '.$term['kind'].' API is unavailable. Install/activate FluentCRM; no CRM builder is required.');
            $title=trim((string)$term['title']);$slug=sanitize_title($title);$key=$term['kind'].':'.$slug;
            if($title===''||$slug===''||isset($seen[$key]))throw new RuntimeException('CRM roles need distinct, nonempty names: '.$term['role']);$seen[$key]=true;
            $matches=$class::where('title',$title)->orWhere('slug',$slug)->limit(3)->get();
            if(count($matches)>1)throw new RuntimeException('Ambiguous CRM '.$term['kind'].' name/slug: '.$title.'. Resolve duplicate definitions in FluentCRM; nothing was replaced.');
            $existing=count($matches)?$matches[0]:null;
            if($existing && (string)$existing->title!==$title)throw new RuntimeException('CRM slug conflicts with another title: '.$title.'. Existing definition preserved.');
            $plan[]= $term+['class'=>$class,'slug'=>$slug,'id'=>$existing?(int)$existing->id:null];
        }
        $reason_field=self::reason_field();
        $definitions=self::field_requirements($reason_field);
        $fields=SiteEngine_Ads_Bridge_Fields::provision(false,$definitions);
        if($fields['conflicts'])throw new RuntimeException('CRM field definitions conflict: '.implode(', ',$fields['conflicts']).'. Existing fields and values preserved.');
        if($apply){
            foreach($plan as &$term){if($term['id'])continue;$c=$term['class'];
                // Recheck under the Ads executor before creation; never overwrite a late collision.
                if($c::where('title',$term['title'])->orWhere('slug',$term['slug'])->limit(1)->count())throw new RuntimeException('CRM definitions changed during setup. Reload to adopt the new definition.');
                $m=new $c();$body=['title'=>$term['title'],'slug'=>$term['slug'],'description'=>''];if($term['kind']==='list')$body['is_public']=0;
                $m->fill($body);if(!$m->save()||!(int)$m->id)throw new RuntimeException('CRM creation unconfirmed for '.$term['title'].'. Inspect before retrying.');
                $term['id']=(int)$m->id;
                $readback=$c::where('title',$term['title'])->orWhere('slug',$term['slug'])->limit(3)->get();
                if(count($readback)!==1||(int)$readback[0]->id!==$term['id'])throw new RuntimeException('CRM creation identity readback is ambiguous for '.$term['title'].'. Definitions retained; stop and inspect.');
            }unset($term);
            $fields=SiteEngine_Ads_Bridge_Fields::provision(true,$definitions);
        }
        return ['terms'=>$plan,'fields'=>$fields,'reason_field'=>$reason_field,
            'reason_mapping_update_required'=>$reason_field !== ($data['fluentcrm']['lifecycle_automation']['unqualified_reason_field'] ?? 'se_ads_unqualified_reason')];
    }
    /** Exact configured feed evidence only; not proof a contact was delivered. */
    public static function feed(int $id): array {
        $url=admin_url('admin.php?page=fluent_forms&route=editor&form_id='.$id.'#/integrations');
        if($id<1)return ['configured'=>false,'message'=>'Select a lead form first.','url'=>$url];
        global $wpdb;
        $rows=$wpdb->get_results($wpdb->prepare("SELECT meta_key,value FROM `{$wpdb->prefix}fluentform_form_meta` WHERE form_id=%d AND meta_key=%s ORDER BY id LIMIT 21",$id,'fluentcrm_feeds'),ARRAY_A);
        if($wpdb->last_error||!is_array($rows)||count($rows)>20)return ['configured'=>false,'message'=>'FluentCRM feed inspection unavailable or exceeds 20 feeds.','url'=>$url];
        $count=0;$bytes=0;
        foreach($rows as $r){
            $bytes+=strlen((string)$r['value']);if($bytes>2097152)return ['configured'=>false,'message'=>'Feed inspection exceeds 2 MiB; evidence unavailable.','url'=>$url];
            $v=json_decode((string)$r['value'],true,64);
            if(!is_array($v)||!in_array($v['enabled']??false,[true,'yes','1',1],true))continue;
            // Fluent Forms FluentCRM integration stores custom-field mappings in other_fields (vendor Bootstrap.php).
            foreach((array)($v['other_fields']??[]) as $field){
                if(is_array($field)&&($field['label']??null)==='submission_id'&&($field['item_value']??null)==='{submission.id}')$count++;
            }
        }
        return ['configured'=>$count===1,'message'=>$count===1?'Configured: enabled FluentCRM feed maps {submission.id} to submission_id. Configuration inspection does not prove actual contact delivery.':($count>1?'Multiple matching enabled feeds; review duplicate contact delivery.':'Missing verified enabled FluentCRM feed mapping {submission.id} to submission_id. Unsupported feed structures remain unverified.'),'url'=>$url];
    }
    /** Apply only exposed values; preserve all custom mappings and hidden identities. */
    public static function values(array $data,array $post): array {
        $text=static fn($key)=>sanitize_text_field(wp_unslash((string)($post[$key]??'')));
        $bool=static fn($key)=>isset($post[$key])&&in_array((string)$post[$key],['1','on'],true);
        $data['fluent_forms']['form_id']=max(0,(int)($post['form_id']??0));
        $choices=[];foreach(SiteEngine_Ads_Bridge_Setup::required_form_keys()as$key)if(isset($post['form_field_'.$key]))$choices[$key]=$text('form_field_'.$key);
        $data['fluent_forms']['fields']=SiteEngine_Ads_Bridge_Setup::resolve_form_mapping($data['fluent_forms']['form_id'],$data['fluent_forms']['fields'],$choices);
        foreach(['spreadsheet_id','credentials_path']as$key)$data['google_sheets'][$key]=$text($key);
        $data['google_sheets']['enabled']=$bool('sheets_enabled');
        foreach(['all_leads'=>'all_leads_enabled','qualified_leads'=>'qualified_enabled','won_jobs'=>'won_enabled']as$family=>$key)$data['exports'][$family]['enabled']=$bool($key);
        foreach(['all_leads'=>['sheet_tab'=>'all_leads_tab'],'qualified_leads'=>['conversion_action'=>'qualified_action','conversion_value'=>'qualified_value'],'won_jobs'=>['sheet_tab'=>'won_tab','conversion_action'=>'won_action']]as$family=>$fields)
            foreach($fields as$key=>$input)$data['exports'][$family][$key]=$text($input);
        $zone=$text('account_timezone');if($zone!==''&&!in_array($zone,timezone_identifiers_list(),true))throw new RuntimeException('Google Ads account timezone must be an IANA timezone, for example America/Chicago.');
        $data['google_sheets']['account_timezone']=$zone;
        // Deliberate lifecycle enablement is separate from email, enrollment and transport.
        $data['fluentcrm']['lifecycle_automation']['enabled']=$bool('lifecycle_automation_enabled');
        if(empty($data['client']['slug']))$data['client']['slug']=sanitize_title((string)wp_parse_url(home_url(),PHP_URL_HOST));
        if(empty($data['client']['short_name']))$data['client']['short_name']=(string)($data['client']['name']??'')?:$data['client']['slug'];
        return $data;
    }
    /** Two bounded Google requests maximum: token exchange and metadata GET. No retries/writes. */
    public static function test_connection(string $path,string $id):array {
        if($path==='')throw new RuntimeException('Missing service-account JSON path. Enter a private absolute file path.');
        if(!preg_match('#^(?:/|[A-Za-z]:[\\\\/])#',$path))throw new RuntimeException('Credentials path must be absolute in this server execution context.');
        if(!file_exists($path))throw new RuntimeException('Credentials file is missing or its parent cannot be traversed by this PHP identity.');
        if(!is_file($path)||!is_readable($path))throw new RuntimeException('Credentials path is not a readable regular file for this PHP identity. No permissions were changed.');
        $size=filesize($path);if($size===false||$size>65536)throw new RuntimeException('Credentials file size is unavailable or exceeds 64 KiB.');
        if(!preg_match('/^[A-Za-z0-9_-]{10,200}$/D',$id))throw new RuntimeException('Enter a spreadsheet ID, not a URL.');
        $raw=file_get_contents($path,false,null,0,65537);$c=is_string($raw)?json_decode($raw,true,16):null;unset($raw);
        if(!is_array($c)||($c['type']??'')!=='service_account'||empty($c['client_email'])||empty($c['private_key']))throw new RuntimeException('Credentials file is not valid service-account JSON. Contents omitted.');
        $token=SiteEngine_Ads_Bridge_Google::token($c);unset($c);
        if(!$token['ok'])throw new RuntimeException($token['error']);
        $response=wp_remote_get('https://sheets.googleapis.com/v4/spreadsheets/'.rawurlencode($id).'?fields=spreadsheetId,properties.title',[
            'timeout'=>10,'redirection'=>0,'limit_response_size'=>65536,'headers'=>['Authorization'=>'Bearer '.$token['token']]]);unset($token);
        if(is_wp_error($response))throw new RuntimeException('Spreadsheet metadata request failed. No rows were read or written; secret transport details omitted.');
        $code=(int)wp_remote_retrieve_response_code($response);$body=json_decode((string)wp_remote_retrieve_body($response),true);unset($response);
        if($code!==200)throw new RuntimeException('Spreadsheet read failed (HTTP '.$code.'). Check sharing, spreadsheet ID and API access; write permission remains unverified.');
        if(!is_array($body)||($body['spreadsheetId']??null)!==$id||!is_string($body['properties']['title']??null))throw new RuntimeException('Spreadsheet identity response is incomplete or mismatched.');
        return ['message'=>'Read access verified: '.sanitize_text_field($body['properties']['title']).'. Write permission is NOT verified. Draft settings were not saved; no rows appended.','spreadsheet_id'=>$id,'write_verified'=>false];
    }
}
