<?php
/** Target correction only: real pinned code, synthetic native/file boundaries. */
$fixture=file_get_contents(__DIR__ . '/joint-operations.php');
$fixture=substr($fixture,5,strpos($fixture,'$count=0;')-5);
eval($fixture);
$checks=0;
function target_pass($ok,$label) { global $checks; if(!$ok) throw new RuntimeException($label); $checks++; echo "PASS $label\n"; }
function target_refuses($fn): bool { try { $fn(); return false; } catch(Throwable $e) { return true; } }
function reset_target_fixture(): void {
    global $a,$j,$private_files,$observed,$baseline_a,$baseline_j,$baseline_files,$baseline_observed,$wpdb;
    $a=$baseline_a; $j=$baseline_j; $private_files=$baseline_files; $observed=$baseline_observed; $wpdb->prefix='wp_';
}
SCJTargetFixture\scj_context($a);
target_pass(SCJTargetFixture\scj_owned($a,$j,'C')['id']===$j['entries']['C']['id'], 'callback context/owned path accepts separately pinned HTTP home and HTTPS browser');
$args=['inspect','C','eligible',$run_dir.'/allocation.json',$a['_sha256'],str_repeat('a',64),$a['_release_sha256'],$run_dir.'/context.json',$j['context_sha256']];
target_pass(SCJTargetFixture\scj_cli_arguments($args)===$args, 'standalone nine-argument APIN JPIN RPIN context CPIN contract');
$bound=SCJTargetFixture\scj_cli_context($a,$args[7],$args[8],$args[6]);
SCJTargetFixture\scj_context($bound);
target_pass(SCJTargetFixture\scj_owned($bound,$j,'C')['id']===$j['entries']['C']['id'], 'standalone independently authenticated split identities pass');
foreach (['server_hostname'=>'foreign.server','owner'=>'foreign_owner','owner_uid'=>12346,'document_root'=>'/wrong/root',
    'database_server_hostname'=>'foreign.database','database_name'=>'wrong-database','prefix'=>'other_',
    'stored_home'=>'https://alma.siteengine.space','stored_siteurl'=>'http://alma.siteengine.space/',
    'effective_home'=>'https://alma.siteengine.space/'] as $key=>$value) {
    reset_target_fixture(); $observed[$key]=$value; if($key==='prefix')$wpdb->prefix=$value;
    target_pass(target_refuses(function()use($a,$j){SCJTargetFixture\scj_owned($a,$j,'C');}), 'callback rejects wrong actual '.$key);
    target_pass(target_refuses(function()use($a,$args){SCJTargetFixture\scj_cli_context($a,$args[7],$args[8],$args[6]);}), 'standalone rejects wrong actual '.$key);
}
foreach (['browser_origin'=>'http://alma.siteengine.space','database_name_sha256'=>str_repeat('0',64),'prefix'=>'other_'] as $key=>$value) {
    reset_target_fixture(); $bad=$allocated_target; $bad[$key]=$value; repin_context($bad);
    target_pass(target_refuses(function()use($a){SCJTargetFixture\scj_context($a);}), 'authenticated but conflicting allocated '.$key.' refused');
}
reset_target_fixture();
$bad=$a; unset($bad['_context']); target_pass(target_refuses(function()use($bad){SCJTargetFixture\scj_context($bad);}), 'missing supplied callback context refused');
$bad=$a; unset($bad['_release_sha256']); target_pass(target_refuses(function()use($bad){SCJTargetFixture\scj_context($bad);}), 'unverified injected target without RPIN refused');
$bad=$a; $bad['_context']['target']['effective_home']='https://alma.siteengine.space/';
target_pass(target_refuses(function()use($bad){SCJTargetFixture\scj_context($bad);}), 'arbitrary supplied target array cannot override pinned context');
$bad=$j; unset($bad['context_sha256']); target_pass(target_refuses(function()use($a,$bad){SCJTargetFixture\scj_owned($a,$bad,'C');}), 'journal missing CPIN refused');
target_pass(target_refuses(function()use($a,$args){SCJTargetFixture\scj_cli_context($a,$args[7],str_repeat('0',64),$args[6]);}), 'standalone wrong independent CPIN refused');
target_pass(target_refuses(function()use($a,$args){SCJTargetFixture\scj_cli_context($a,$args[7],$args[8],str_repeat('0',64));}), 'standalone wrong independent RPIN refused');
target_pass(target_refuses(function()use($a,$args,$run_dir){SCJTargetFixture\scj_cli_context($a,$run_dir.'/foreign.json',$args[8],$args[6]);}), 'standalone foreign context path refused');
target_pass(target_refuses(function()use($args){SCJTargetFixture\scj_cli_arguments(array_slice($args,0,7));}), 'old unauthenticated seven-argument invocation refused');
$private_files[$run_dir.'/context.json']['mode']=0644;
target_pass(target_refuses(function()use($a){SCJTargetFixture\scj_context($a);}), 'nonprivate context refused');
reset_target_fixture(); $private_files[$run_dir.'/context.json']['bytes'].=' ';
target_pass(target_refuses(function()use($a){SCJTargetFixture\scj_context($a);}), 'context file changed after pinning refused');
reset_target_fixture(); $bad=$a; $bad['_sha256']=str_repeat('0',64);
target_pass(target_refuses(function()use($bad){SCJTargetFixture\scj_context($bad);}), 'wrong allocation pin refused');
$before=$wpdb;
target_pass(target_refuses(function()use($a,$j){SCJTargetFixture\scj_with_fault($a,$j,'C','failed-current-read',function(){throw new RuntimeException('injected callback exception');});}), 'fault callback exception remains a failure');
target_pass($wpdb===$before && SCJTargetFixture\scj_inspect($a,$j,'C')['current']['allowed'], 'callback exception restores original DB and real authority under split identity');
echo "Target correction checks: {$checks} passed; verified Ads helper/native+file boundaries doubled, no installed execution.\n";
