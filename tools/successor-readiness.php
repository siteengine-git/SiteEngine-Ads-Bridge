<?php
/** Alma-owned explicit WP-CLI readback; no migration, fixtures, delivery or writes. */
if (!defined('WP_CLI') || !WP_CLI) { exit(1); }
try {
    if (!isset($args) || count($args)!==1 || !preg_match('/^[a-f0-9]{64}$/D',$args[0])) throw new RuntimeException('independent_manifest_pin_required');
    $root=SEAB_PLUGIN_DIR;$file=$root.'SUCCESSOR-MANIFEST.json';
    if (!is_file($file)||hash_file('sha256',$file)!==$args[0]) throw new RuntimeException('installed_manifest_mismatch');
    $manifest=json_decode(file_get_contents($file),true);
    if (!is_array($manifest)||($manifest['candidate']??'')!=='0.8.0'||SEAB_VERSION!=='0.8.0') throw new RuntimeException('candidate_mismatch');
    $count=0;foreach($manifest['payload'] as $row){$path=$row['path'];if(!is_string($path)||strpos($path,'..')!==false||strpos($path,'\\')!==false||$path[0]==='/'||!is_file($root.$path)||is_link($root.$path)||hash_file('sha256',$root.$path)!==$row['sha256'])throw new RuntimeException('installed_payload_mismatch');$count++;}
    if (PHP_VERSION_ID<80300||!extension_loaded('mysqli')||get_class($GLOBALS['wpdb'])!=='wpdb') throw new RuntimeException('stock_php83_mysqli_wpdb_profile_required');
    $cap=seab_configuration_capability();
    foreach(array('contract_version'=>'1','inquiry_schema'=>'2','coordination_schema'=>'1','scope'=>'destination_shared_configuration','session_model'=>'single_php_process_single_wpdb_session','max_steps'=>64)as$key=>$value)if(($cap[$key]??null)!==$value)throw new RuntimeException('capability_mismatch');
    $inspection=seab_configuration_inspect_pending($cap);
    if(($inspection['complete']??false)!==true||($inspection['state']??'')!=='none'||($inspection['write_permission']??null)!==false)throw new RuntimeException('pending_or_unsupported_destination');
    echo wp_json_encode(array('status'=>'PASS','version'=>SEAB_VERSION,'payloads'=>$count,'capability'=>$cap,'inspection'=>$inspection,'native_limits'=>'readback only; no vendor/CRM installation, concurrency, fixture or power-loss claim'))."\n";
}catch(Throwable $e){fwrite(STDERR,"FAIL: successor readback incomplete; retain private native evidence and holds; no automatic repair\n");exit(1);}
