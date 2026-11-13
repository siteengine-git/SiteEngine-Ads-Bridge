<?php
/** Read-only installed acceptance aid. Run through the canonical site profile/guard. */
defined('ABSPATH') || exit;
if(!current_user_can('manage_options'))throw new RuntimeException('Administrator identity required.');
$plugin=SEAB_PLUGIN_DIR;$manifestFile=$plugin.'SUCCESSOR-MANIFEST.json';
$m=json_decode(file_get_contents($manifestFile),true,128,JSON_THROW_ON_ERROR);
if(($m['version']??null)!=='0.9.1'||SEAB_VERSION!=='0.9.1')throw new RuntimeException('Wrong installed candidate.');
$verified=0;foreach($m['payload']as$row){$p=$row['path'];if(str_contains($p,'..')||str_starts_with($p,'/')||str_contains($p,'\\'))throw new RuntimeException('Unsafe manifest path.');if(!is_file($plugin.$p)||!hash_equals($row['sha256'],hash_file('sha256',$plugin.$p)))throw new RuntimeException('Payload differs: '.$p);$verified++;}
$c=SiteEngine_Ads_Bridge_Config::instance();$flags=['sheets'=>(bool)$c->get('google_sheets.enabled',false)];foreach(['all_leads','qualified_leads','won_jobs','lost_leads']as$family)$flags[$family]=(bool)$c->get('exports.'.$family.'.enabled',false);
$feed=SiteEngine_Ads_Bridge_Product_Setup::feed((int)$c->get('fluent_forms.form_id',0));
$requirements=[];try{$p=SiteEngine_Ads_Bridge_Product_Setup::crm(false);$requirements=['missing_terms'=>count(array_filter($p['terms'],static fn($r)=>!$r['id'])),'missing_fields'=>count($p['fields']['missing']),'conflicts'=>$p['fields']['conflicts']];}catch(Throwable$e){$requirements=['unavailable'=>$e->getMessage()];}
$job=\SiteEngine\AdsBridge\Import\Store::job();
$out=['candidate'=>SEAB_VERSION,'PHP'=>PHP_VERSION,'payload_verified'=>$verified,'manifest_sha256'=>hash_file('sha256',$manifestFile),'effective_config_sha256'=>hash('sha256',json_encode($c->all(),JSON_THROW_ON_ERROR)),'configuration_source'=>$c->configuration_source(),'credential_path_sha256'=>hash('sha256',(string)$c->get('google_sheets.credentials_path','')),'form_id'=>(int)$c->get('fluent_forms.form_id',0),'feed'=>['configured'=>$feed['configured'],'message'=>$feed['message']],'CRM_requirements'=>$requirements,'flags'=>$flags,'saved_job'=>['id'=>$job['id']??null,'status'=>$job['status']??null,'cursor'=>$job['cursor']??null,'total'=>isset($job['actions'])?count($job['actions']):null,'pending_present'=>!empty($job['pending'])],'limits'=>'Readback only; no authenticated visible save/setup utility or real Google connection demonstrated by this script.'];
echo json_encode($out,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
