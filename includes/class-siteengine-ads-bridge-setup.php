<?php
/** Explicit administrator setup; no traffic migration or delivery. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class SiteEngine_Ads_Bridge_Setup {
    private static bool $executor_owned=false;
    public static ?string $storage_error=null;
    public static function executor_owned():bool { return self::$executor_owned; }
    const AUTHORITY_OPTION = 'seab_settings_authority_v1';

    /** A deliberate migration applies only to the reviewed external file bytes. */
    public static function settings_authority() {
        $choice = get_option( self::AUTHORITY_OPTION, array() );
        return SiteEngine_Ads_Bridge_Settings::exists()
            && is_array( $choice ) && isset( $choice['path'], $choice['sha256'] )
            && defined( 'SEAB_CONFIG_FILE' ) && $choice['path'] === SEAB_CONFIG_FILE
            && is_file( SEAB_CONFIG_FILE )
            && hash_equals( (string) $choice['sha256'], hash_file( 'sha256', SEAB_CONFIG_FILE ) );
    }

    /** Existing route, or the predecessor's deterministic private directory if retained. No mkdir/discovery. */
    public static function executor_directory() {
        if (function_exists('se_crm_deployment_directory')) return se_crm_deployment_directory();
        if (defined('DB_NAME')) {
            global $wpdb;
            $parent=realpath(dirname(rtrim(ABSPATH,'/')));
            if (!$parent) { throw new \SiteEngine\AdsBridge\Import\OwnershipRecordFailure('executor directory','unavailable_parent','predecessor location parent cannot be resolved; absence cannot be established'); }
            \SiteEngine\AdsBridge\Import\Recovery::directoryEvidence($parent);
            if ($parent) { $path=$parent.'/siteengine-crm-private-'.substr(hash('sha256',DB_NAME.'|'.$wpdb->prefix),0,16); if (file_exists($path)||is_link($path)) return $path; }
        }
        return null;
    }

    public static function blocker_message($reason) {
        if (class_exists('SiteEngine\\AdsBridge\\Import\\Recovery')) return \SiteEngine\AdsBridge\Import\Recovery::message($reason);
        if ($reason==='shared_configuration_recovery_required') return 'An unfinished shared configuration job owns these settings. Use its original saved recovery; no takeover or initialization.';
        if ($reason==='coordination_configuration_recovery_required') return 'Unfinished configuration operation requires its saved import/recovery workflow. Initialization does not repair or reset it. Reason: '.$reason;
        if ($reason==='coordination_schema_unsupported') return 'Existing storage is incomplete or unsupported. Review the physical inquiry/guard schemas with the supported inquiry-admin migration preview before any repair. Reason: '.$reason;
        if ($reason==='coordination_busy') return 'Another configuration writer currently holds the destination lock. No initialization was attempted. Reason: '.$reason;
        return 'Ads setup blocked: '.$reason.'. Preserve existing state; no rows or journals were reset.';
    }

    /** Consult the existing shared executor and durable job; never take over a job. */
    public static function with_executor( callable $callback ) {
        $lock = null;
        try {
            $directory = self::executor_directory();
            if (!class_exists('SiteEngine\\AdsBridge\\Import\\Recovery')) { throw new RuntimeException('Ads ownership inspector is unavailable; restore the matching plugin package before changing settings.'); }
            if ( null !== $directory ) {
                $lock=\SiteEngine\AdsBridge\Import\Recovery::acquireExecutor($directory,true);
                self::$executor_owned=true;
                $shared=\SiteEngine\AdsBridge\Import\Recovery::shared();
                if (!in_array($shared['kind'],['none','idle'],true)) throw new RuntimeException($shared['message'].' Job '.($shared['job_id']??'unknown').'; state '.($shared['state']??'unknown').'.');
            }
            return $callback();
        } finally {
            self::$executor_owned=false;
            if ( is_resource( $lock ) ) { flock( $lock, LOCK_UN ); fclose( $lock ); }
        }
    }

    /** Guard configuration saves against unfinished Ads history and journals. */
    public static function with_configuration( callable $callback ) {
        return self::with_executor( static function() use ( $callback ) {
            if ( class_exists('SiteEngine\\AdsBridge\\Import\\Store') && !\SiteEngine\AdsBridge\Import\Engine::$executing ) {
                $import=\SiteEngine\AdsBridge\Import\Store::job();
                if ($import && in_array($import['status'],['running','uncertain'],true)) { throw new RuntimeException('Saved Ads import '.$import['id'].'; state '.$import['status'].'. Resume its saved operation before changing settings; initialization is not recovery.'); }
            }
            // Fresh setup can save first, while still checking any existing journal.
            $held = SiteEngine_Ads_Bridge_Coordination::enter_setup( true );
            if ( ! $held['ok'] ) { throw new RuntimeException( self::blocker_message($held['reason']) ); }
            try { return $callback(); }
            finally { if ( ! SiteEngine_Ads_Bridge_Coordination::release() ) { throw new RuntimeException( 'Ads configuration lock release could not be confirmed. Inspect state before retrying.' ); } }
        } );
    }

    /** Reuse existing explicit migrations; existing partial schemas fail closed. */
    public static function initialize_storage() {
        return self::with_executor( static function() {
            if(class_exists('SiteEngine\\AdsBridge\\Import\\Store')){
                $import=\SiteEngine\AdsBridge\Import\Store::job();
                if($import&&in_array($import['status'],['running','uncertain'],true))throw new RuntimeException('Saved Ads import '.$import['id'].'; state '.$import['status'].'. Resume its saved operation before preparing storage; initialization is not recovery.');
            }
            $held = SiteEngine_Ads_Bridge_Coordination::enter_setup( true );
            if ( ! $held['ok'] ) { throw new RuntimeException( self::blocker_message($held['reason']) ); }
            try {
                if ( ! SiteEngine_Ads_Bridge_Inquiries::table_exists() || ! SiteEngine_Ads_Bridge_Inquiries::schema_ready() ) {
                    $inquiry = SiteEngine_Ads_Bridge_Inquiries::migrate( true );
                    if ( empty( $inquiry['applied'] ) ) { throw new RuntimeException( 'Inquiry storage initialization was not confirmed. Review Storage before retrying.' ); }
                }
            } finally {
                if ( ! SiteEngine_Ads_Bridge_Coordination::release() ) { throw new RuntimeException( 'Storage lock release unconfirmed. Inspect storage before retrying.' ); }
            }
            $guard = SiteEngine_Ads_Bridge_Coordination::migrate( true );
            if ( $guard['status'] !== 'complete' ) { throw new RuntimeException( 'Configuration guard initialization blocked: ' . $guard['reason_code'] ); }
            $held=SiteEngine_Ads_Bridge_Coordination::enter_setup();if(!$held['ok'])throw new RuntimeException(self::blocker_message($held['reason']));
            try{SiteEngine_Ads_Bridge_Storage::prepare_ledger();if(defined('SEAB_VERSION'))update_option('seab_version',SEAB_VERSION,false);}
            finally{if(!SiteEngine_Ads_Bridge_Coordination::release())throw new RuntimeException('Storage lock release unconfirmed; inspect before retrying.');}
            return true;
        } );
    }

    /** Only normal activation/Ads administrator entry; never front-end or workers. */
    public static function prepare_storage_for_admin() {
        if(!current_user_can('manage_options'))return;
        try{self::initialize_storage();self::$storage_error=null;}
        catch(Throwable $e){self::$storage_error=$e->getMessage();}
    }
    public static function required_form_keys():array {
        return ['first_name','last_name','address_1','address_2','city','state','zip_code','country','phone','email','service_type','estimated_value','gclid','gbraid','wbraid','fbclid','msclkid','utm_source','utm_medium','utm_campaign','utm_content','utm_term','referrer_url','page_title','ip_address','user_agent','cookie_value','transaction_id'];
    }
    /** Flat selectors supported by the actual reader; no feed/Consent claim. */
    public static function form_connection(int $id,array $mapping):array {
        global $wpdb;$out=['ready'=>false,'fields'=>[],'mapping'=>$mapping,'needs'=>[],'error'=>'Select a lead form.'];
        if($id<1)return $out;
        $raw=$wpdb->get_var($wpdb->prepare("SELECT form_fields FROM `{$wpdb->prefix}fluentform_forms` WHERE id=%d",$id));
        if($wpdb->last_error||!is_string($raw)||strlen($raw)>1048576){$out['error']='Form definition is unavailable or exceeds1 MiB; no mappings changed.';return $out;}
        try{$tree=json_decode($raw,true,128,JSON_THROW_ON_ERROR);}catch(Throwable $e){$out['error']='Form definition is malformed; no mappings changed.';return $out;}
        $walk=static function($v)use(&$walk,&$out){if(!is_array($v))return;
            if(isset($v['attributes']['name'])&&is_string($v['attributes']['name'])){$n=$v['attributes']['name'];if(!str_starts_with($n,'se_consent_')){$old=$out['fields'][$n]??['count'=>0,'label'=>(string)($v['settings']['label']??$n),'type'=>(string)($v['element']??'')];$old['count']++;$out['fields'][$n]=$old;}}
            foreach($v as$x)if(is_array($x))$walk($x);
        };$walk($tree);if(count($out['fields'])>500){$out['fields']=[];$out['error']='Form exceeds500 selectors; inspect Advanced.';return $out;}
        $defaults=SiteEngine_Ads_Bridge_Settings::default_field_map();
        foreach(self::required_form_keys()as$key){$current=$mapping[$key]??$defaults[$key];$candidates=[];
            foreach($out['fields']as$name=>$f){if($f['count']!==1)continue;if($name===$defaults[$key]||($key==='email'&&$f['type']==='input_email')||($key==='phone'&&in_array($f['type'],['phone','input_tel'],true)))$candidates[]=$name;}
            if(isset($out['fields'][$current])&&$out['fields'][$current]['count']===1)continue;
            // Preserve an explicitly customized but now missing selector; require choice.
            if($current===$defaults[$key]&&count($candidates)===1){$out['mapping'][$key]=$candidates[0];continue;}
            $out['needs'][$key]=$candidates;
        }
        $out['ready']=!$out['needs'];$out['error']=$out['ready']?'':'Missing or ambiguous inputs need a field choice. Saving incomplete setup is allowed.';return $out;
    }
    public static function resolve_form_mapping(int $id,array $mapping,array $choices):array {
        $view=self::form_connection($id,$mapping);if(!$view['fields'])return $mapping;
        foreach($choices as$key=>$selector){if(!in_array($key,self::required_form_keys(),true)||$selector==='')continue;if(!isset($view['fields'][$selector])||$view['fields'][$selector]['count']!==1)throw new RuntimeException('Selected '.str_replace('_',' ',$key).' field is absent or ambiguous on this form. Reload and choose again.');$mapping[$key]=$selector;}
        return self::form_connection($id,$mapping)['mapping'];
    }

    /** Exact existing tag titles only; ambiguous titles cannot establish identity. */
    public static function validate_lifecycle( $mapping ) {
        global $wpdb;
        $seen = array();
        foreach ( SiteEngine_Ads_Bridge_Config::lifecycle_roles() as $role ) {
            $title = $mapping[ $role ] ?? '';
            if ( ! is_string( $title ) || '' === trim( $title ) || isset( $seen[ $title ] ) ) { throw new RuntimeException( 'Select four distinct existing lifecycle tags in CRM & Lifecycle; check ' . $role . '.' ); }
            $table = $wpdb->prefix . 'fc_tags';
            $ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE BINARY title = %s", $title ) );
            if ( $wpdb->last_error || ! is_array( $ids ) || count( $ids ) !== 1 ) { throw new RuntimeException( ucfirst( $role ) . ' lifecycle tag must identify exactly one existing FluentCRM tag. Create missing tags in FluentCRM > Contacts > Tags, then select them in Ads Bridge > CRM & Lifecycle.' ); }
            $seen[ $title ] = true;
        }
    }

    const VERIFICATIONS_OPTION = 'seab_operator_verifications_v1';
    /** Bind operator evidence to the effective authority, never alter its precedence. */
    public static function verification_context($kind,$config=null) {
        $c=$config?:SiteEngine_Ads_Bridge_Config::instance();
        if($kind==='timezone')return hash('sha256',json_encode([(string)$c->get('client.google_ads_customer_id',''),(string)$c->get('client.timezone','')]));
        if($kind!=='submission')throw new RuntimeException('Unknown verification kind.');
        global $wpdb;$id=(int)$c->get('fluent_forms.form_id',0);
        $form=$wpdb->get_var($wpdb->prepare("SELECT form_fields FROM `{$wpdb->prefix}fluentform_forms` WHERE id=%d",$id));
        if($wpdb->last_error||!is_string($form)||$form==='')throw new RuntimeException('Master-form definition unavailable; verification cannot be established.');
        $meta=$wpdb->get_results($wpdb->prepare("SELECT meta_key,value FROM `{$wpdb->prefix}fluentform_form_meta` WHERE form_id=%d ORDER BY id LIMIT 101",$id),ARRAY_A);
        if($wpdb->last_error||!is_array($meta)||count($meta)>100)throw new RuntimeException('Form/feed configuration unavailable or exceeds bounded verification scope.');
        return hash('sha256',json_encode([$id,$c->get('fluent_forms.fields',[]),$form,$meta]));
    }
    public static function verification($kind,$config=null) {
        try{$context=self::verification_context($kind,$config);}catch(Throwable $e){return ['valid'=>false,'reason'=>$e->getMessage()];}
        $all=get_option(self::VERIFICATIONS_OPTION,[]);$r=is_array($all)?($all[$kind]??[]):[];
        $valid=is_array($r)&&($r['method']??null)==='manual_operator'&&isset($r['context'])&&hash_equals($context,$r['context'])&&is_int($r['user_id']??null)&&$r['user_id']>0&&is_int($r['timestamp']??null);
        return ['valid'=>$valid,'reason'=>$valid?'Recorded manual operator confirmation; not an automatic external check.':'Not confirmed for current inputs, or inputs changed.','record'=>$valid?$r:[]];
    }
    public static function record_verification($kind,$pin,$input=[]) {
        return self::with_configuration(static function()use($kind,$pin,$input){
            $c=SiteEngine_Ads_Bridge_Config::instance();$context=self::verification_context($kind,$c);
            if(!is_string($pin)||!hash_equals($context,$pin))throw new RuntimeException('Verification inputs changed; reload before confirming.');
            $detail=[];
            if($kind==='timezone'){
                $cid=preg_replace('/[^0-9]/','',(string)$c->get('client.google_ads_customer_id',''));
                $zone=(string)$c->get('client.timezone','');
                if(strlen($cid)!==10||!in_array($zone,timezone_identifiers_list(),true)||($input['account_timezone']??'')!==$zone)throw new RuntimeException('Inspect the specified Google Ads account Settings. Enter its exact IANA timezone; resolve any account/conversion mismatch without changing WordPress.');
                $detail=['scope'=>'Google Ads account and effective conversion timezone manually compared'];
            }elseif($kind==='submission'){
                // Optional readback of one administrator-specified pair, never latest/marker discovery.
                $contact=(int)($input['contact_id']??0);$submission=(int)($input['submission_id']??0);
                if(($contact>0)!==($submission>0))throw new RuntimeException('Supply both exact IDs or neither.');
                $detail=['scope'=>'Feed maps {submission.id} to submission_id; manually inspected','runtime_pair'=>'not supplied'];
                if($contact>0){
                    global $wpdb;$row=$wpdb->get_row($wpdb->prepare("SELECT id,form_id FROM `{$wpdb->prefix}fluentform_submissions` WHERE id=%d",$submission),ARRAY_A);
                    if($wpdb->last_error||!$row||(int)$row['form_id']!==(int)$c->get('fluent_forms.form_id',0))throw new RuntimeException('Exact submission/master-form readback failed.');
                    $person=\FluentCrm\App\Models\Subscriber::find($contact);$custom=$person?$person->custom_fields():[];
                    if(!$person||!is_array($custom)||(string)($custom['submission_id']??'')!==(string)$submission)throw new RuntimeException('Exact contact submission_id readback failed; no fallback search.');
                    $detail['runtime_pair']=['contact_id'=>$contact,'submission_id'=>$submission,'scope'=>'Stored numeric linkage and master-form membership only; does not prove every feed, identity, consent or future submission.'];
                }
            }else{throw new RuntimeException('Unknown verification kind.');}
            $all=get_option(self::VERIFICATIONS_OPTION,[]);if(!is_array($all))throw new RuntimeException('Malformed operator-verification storage; inspect before saving.');
            $all[$kind]=['method'=>'manual_operator','context'=>$context,'user_id'=>(int)get_current_user_id(),'timestamp'=>time(),'detail'=>$detail];
            if($all[$kind]['user_id']<1)throw new RuntimeException('Authenticated administrator required.');
            update_option(self::VERIFICATIONS_OPTION,$all,false);
            if(get_option(self::VERIFICATIONS_OPTION)!==$all)throw new RuntimeException('Verification readback failed; inspect before retrying.');
            return $all[$kind];
        });
    }
}
