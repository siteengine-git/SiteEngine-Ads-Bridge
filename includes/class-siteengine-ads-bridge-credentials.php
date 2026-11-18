<?php
defined('ABSPATH') || exit;
/** One local resolver for web and CLI. Secret material never leaves trusted PHP. */
final class SiteEngine_Ads_Bridge_Credentials {
    const MODULE = 'ads-bridge';
    const GROUP = 'google-sheets';
    const PURPOSE = 'sheets-auth';
    const LIMIT = 131072;
    private static $registered = false;

    public static function register(): void {
        if (self::$registered || !function_exists('siteengine_register_credentials_v1')) return;
        $definition = [
            'label'=>__('Google Sheets service-account JSON', 'siteengine-ads-bridge'),
            'module_label'=>__('Ads Bridge', 'siteengine-ads-bridge'),
            'kind'=>'document', 'purposes'=>[self::PURPOSE], 'authority'=>'base',
            'validator'=>static function($value,$resource) {
                if ($resource !== 'default') return false;
                try { self::document($value); return true; } catch (Throwable $e) { return false; }
            },
            // Base administration never changes Ads runtime source selection.
            'allow_replace'=>true, 'allow_remove'=>false, 'allow_rollback'=>false,
        ];
        if (self::simple_document_save()) {
            $definition['admin_mode'] = 'simple';
            $definition['document_label'] = __('Google service-account JSON', 'siteengine-ads-bridge');
        }
        $r = siteengine_register_credentials_v1(self::MODULE, self::GROUP, $definition);
        self::$registered = $r === true;
    }
    /** Exact additive capability probe; older Base document administration is preserved. */
    public static function simple_document_save(): bool {
        if (!function_exists('siteengine_credentials_document_save_contract_version')) return false;
        try {
            return siteengine_credentials_document_save_contract_version() === 'document-save-1';
        } catch (Throwable $e) {
            return false;
        }
    }
    public static function setup_guidance(): string {
        return self::simple_document_save()
            ? __('Paste the complete service-account JSON in Base Credentials and click Save credentials. Then test the Base source here before deliberately selecting it. Saving credentials does not switch the active source.', 'siteengine-ads-bridge')
            : __('This Base version uses protected Stage and Activate controls. Complete those in Base Credentials, then test the Base source here before deliberately selecting it.', 'siteengine-ads-bridge');
    }
    public static function admin_url(): ?string {
        if (!function_exists('siteengine_credentials_admin_url_v1')) return null;
        try { $u=siteengine_credentials_admin_url_v1(self::MODULE,self::GROUP,'default');
            return is_string($u) && $u !== '' ? $u : null;
        } catch (Throwable $e) { return null; }
    }
    public static function source($config): string {
        $s=$config->get('google_sheets.credentials_source','file');
        if (!is_string($s) || !in_array($s,['file','base'],true)) throw new RuntimeException('Credential source is invalid. Choose a verified file or Base source in Setup; no fallback was used.');
        return $s;
    }
    /** Pure format/key validation, shared by Base credential administration and runtime reads. No provider request. */
    public static function document($raw): array {
        if (!is_string($raw) || strlen($raw)>self::LIMIT || !str_starts_with(ltrim($raw),'{')) throw new RuntimeException('Service-account document must be a JSON object within 128 KiB. Contents omitted.');
        $c=json_decode($raw,true,32);
        if (!is_array($c) || ($c['type']??null)!=='service_account') throw new RuntimeException('Not a Google service-account JSON object. Contents omitted.');
        foreach (['project_id','private_key_id','client_id','client_email','private_key','token_uri'] as $k)
            if (!is_string($c[$k]??null) || trim($c[$k])==='') throw new RuntimeException('Service-account JSON is incomplete. Required identity, key and OAuth fields must be nonempty strings. Contents omitted.');
        if (!preg_match('/^[A-Za-z0-9._-]+@[A-Za-z0-9.-]+\.gserviceaccount\.com$/D',$c['client_email']) || $c['token_uri']!=='https://oauth2.googleapis.com/token') throw new RuntimeException('Service-account identity or Google OAuth endpoint is invalid. Contents omitted.');
        if (!function_exists('openssl_pkey_get_private')) throw new RuntimeException('OpenSSL is unavailable for local private-key validation.');
        $key=@openssl_pkey_get_private($c['private_key']);
        $details=$key?@openssl_pkey_get_details($key):false;
        if (!$details || ($details['type']??null)!==OPENSSL_KEYTYPE_RSA || ($details['bits']??0)<2048) throw new RuntimeException('Service-account private key is not a supported RSA key of at least 2048 bits. Contents omitted.');
        return $c;
    }
    /** Resolves only the selected source; never falls back, imports, stages or activates. */
    public static function resolve($config,?string $source=null,?string $path=null): array {
        $source=$source??self::source($config);
        if (!in_array($source,['file','base'],true)) throw new RuntimeException('Unknown credential source; no fallback was used.');
        if ($source==='base') {
            if (!function_exists('siteengine_credentials_runtime_read_v1') || !function_exists('siteengine_credentials_contract_version')) throw new RuntimeException('Base credentials-1 API is unavailable. No file fallback was used.');
            try { if(siteengine_credentials_contract_version()!=='credentials-1')throw new RuntimeException('Unknown contract');
                $r=siteengine_credentials_runtime_read_v1(self::MODULE,self::GROUP,'default',self::PURPOSE); }
            catch (Throwable $e) { throw new RuntimeException('Base credential read is unavailable. No file fallback was used.'); }
            if (is_wp_error($r) || !is_array($r) || ($r['kind']??null)!=='document' || !is_string($r['version_id']??null) || !preg_match('/^[a-f0-9-]{36}$/D',$r['version_id']) || !is_int($r['revision']??null)) throw new RuntimeException('No accessible active Base Google credential. '.self::setup_guidance().' No file fallback was used.');
            return ['credentials'=>self::document($r['value']??null),'source'=>'base','identity'=>$r['version_id'].':'.$r['revision']];
        }
        $path=$path??$config->get('google_sheets.credentials_path','');
        if (!is_string($path) || $path==='') throw new RuntimeException('Missing credentials file path. Enter a private absolute path or verify and select Base credentials.');
        if (!preg_match('#^(?:/|[A-Za-z]:[\\\\/])#',$path)) throw new RuntimeException('Credentials path must be absolute in this PHP execution context.');
        clearstatcache(true,$path);
        if (!file_exists($path)) throw new RuntimeException(is_dir(dirname($path))&&is_readable(dirname($path))?'Credentials file is missing in its configured directory.':'Credentials parent is unavailable or inaccessible to this PHP identity.');
        if (!is_file($path)||!is_readable($path)) throw new RuntimeException('Credentials path is not a readable regular file for this PHP identity. No permissions were changed.');
        $raw=@file_get_contents($path,false,null,0,self::LIMIT+1);
        if (!is_string($raw)) throw new RuntimeException('Credentials file could not be read by this PHP identity.');
        $c=self::document($raw);
        // Private in-memory comparison only. Never returned to browser, persisted or logged.
        return ['credentials'=>$c,'source'=>'file','identity'=>hash('sha256',$raw)];
    }
    /** Safe local diagnostics. Format validity is not provider authentication. */
    public static function status($config): array {
        try { $source=self::source($config); } catch (Throwable $e) { return ['source'=>'invalid','configured'=>false,'locally_valid'=>false,'authenticated'=>false,'message'=>$e->getMessage()]; }
        try { self::resolve($config); return ['source'=>$source,'configured'=>true,'locally_valid'=>true,'authenticated'=>false,'message'=>'Configured; locally valid. Google authentication is not established by this page. Use Test connection.']; }
        catch (Throwable $e) { return ['source'=>$source,'configured'=>$source==='base'?false:$config->get('google_sheets.credentials_path','')!=='','locally_valid'=>false,'authenticated'=>false,'message'=>$e->getMessage()]; }
    }
    /** Explicit read-only provider test; exact resolved identity retained privately for a subsequent switch. */
    public static function test($config,string $source,string $path,string $id): array {
        if (!preg_match('/^[A-Za-z0-9_-]{10,200}$/D',$id)) throw new RuntimeException('Enter a spreadsheet ID, not a URL.');
        $r=self::resolve($config,$source,$path);
        try { $token=SiteEngine_Ads_Bridge_Google::token($r['credentials']); } catch(Throwable $e) { throw new RuntimeException('Google authentication request could not complete. Secret transport details omitted.'); }
        unset($r['credentials']);
        if (!$token['ok']) throw new RuntimeException($token['error']);
        try { $response=wp_remote_get('https://sheets.googleapis.com/v4/spreadsheets/'.rawurlencode($id).'?fields=spreadsheetId',[
            'timeout'=>10,'redirection'=>0,'limit_response_size'=>65536,'headers'=>['Authorization'=>'Bearer '.$token['token']]]); } catch(Throwable $e) { throw new RuntimeException('Spreadsheet metadata request could not complete. Secret transport details omitted.'); }
        unset($token);
        if (is_wp_error($response)) throw new RuntimeException('Spreadsheet metadata request failed. Authentication succeeded, but access is unverified. Secret transport details omitted.');
        $code=(int)wp_remote_retrieve_response_code($response); $body=json_decode((string)wp_remote_retrieve_body($response),true);
        if ($code!==200) throw new RuntimeException('Google authenticated, but spreadsheet read failed (HTTP '.$code.'). Check ID/sharing/API access. No write permission was tested.');
        if (!is_array($body)||($body['spreadsheetId']??null)!==$id) throw new RuntimeException('Spreadsheet identity response is incomplete or mismatched.');
        return ['identity'=>$r['identity'],'source'=>$source,'authenticated'=>true,'spreadsheet_readable'=>true,'write_verified'=>false,
            'message'=>'Google authentication and spreadsheet read access verified. Write permission is NOT verified. Draft settings were not saved; no rows appended.'];
    }
    /** Provider testing occurs before the configuration lock; commit only an unchanged verified selection. */
    public static function test_and_select(string $source,string $path,string $id): array {
        if(!current_user_can('manage_options'))throw new RuntimeException('Administrator permission required to select a credential source.');
        $config=SiteEngine_Ads_Bridge_Config::instance();
        if ($config->external_override_active()) throw new RuntimeException('External configuration controls credential selection. Update its explicit source deliberately; WordPress settings were not changed.');
        $before=SiteEngine_Ads_Bridge_Settings::get();
        if ($id!==$config->get('google_sheets.spreadsheet_id','') || $path!==$config->get('google_sheets.credentials_path','')) throw new RuntimeException('Save the spreadsheet ID and file path before switching source.');
        $r=self::test($config,$source,$path,$id);
        SiteEngine_Ads_Bridge_Setup::with_configuration(static function()use($config,$before,$r,$source,$path) {
            if (SiteEngine_Ads_Bridge_Settings::get()!==$before) throw new RuntimeException('Settings changed during verification; source was not switched. Reload and test again.');
            $now=self::resolve($config,$source,$path);
            if (!hash_equals($r['identity'],$now['identity'])) throw new RuntimeException('Credential changed during verification; source was not switched. Test again.');
            $data=$before; $data['google_sheets']['credentials_previous_source']=self::source($config);
            $data['google_sheets']['credentials_source']=$source;
            SiteEngine_Ads_Bridge_Settings::save($data);
            if (SiteEngine_Ads_Bridge_Settings::get()!==SiteEngine_Ads_Bridge_Settings::sanitize_config($data)) throw new RuntimeException('Source selection readback failed. Reload and inspect before retrying.');
        });
        unset($r['identity']); $r['message']='Verified and selected '.$source.' credentials. Previous source/path retained for deliberate rollback. Exports unchanged; write permission unverified.';
        return $r;
    }
}
