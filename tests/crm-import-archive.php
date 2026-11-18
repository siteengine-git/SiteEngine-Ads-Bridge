<?php
foreach(['common','sequences','dependencies','format'] as $file)require dirname(__DIR__).'/includes/crm-import/'.$file.'.php';
use SiteEngine\AdsBridge\Import\{Archive,JSON};
$n=0;$files=[];
function ok($v,$why){global $n;if(!$v)throw new LogicException($why);$n++;echo "PASS $why\n";}
function reject($f,$why){try{$f();}catch(Throwable $e){ok(true,$why);return;}throw new LogicException('Expected refusal '.$why);}
function zipfile($members){global $files;$path=tempnam(sys_get_temp_dir(),'seab-archive-test-');$files[]=$path;$z=new ZipArchive();$z->open($path,ZipArchive::OVERWRITE);foreach($members as $key=>$value)$z->addFromString($key,$value);$z->close();return $path;}
try{
    $body=file_get_contents($argv[1]);$m=JSON::canonical(['format'=>'siteengine.crm.configuration-zip/1','configuration_sha256'=>hash('sha256',$body),'execution_enabled'=>false]);
    $base=['configuration.json'=>$body,'MANIFEST.json'=>$m];$p=zipfile($base);$v=Archive::read($p);ok(count($v['objects'])===93,'Read exact real producer package');
    foreach(['../unexpected'=>'x','contacts.json'=>'[]','extra.txt'=>'x'] as $name=>$content){$a=$base;$a[$name]=$content;$p=zipfile($a);reject(fn()=>Archive::read($p),'Refuse extra/path member '.$name);}
    $a=$base;$a['MANIFEST.json']='{}';$p=zipfile($a);reject(fn()=>Archive::read($p),'Refuse incomplete manifest');
    $a=$base;$a['configuration.json'].=' ';$p=zipfile($a);reject(fn()=>Archive::read($p),'Refuse changed payload hash');
    $a=$base;$a['configuration.json']=str_repeat('x',8388609);$p=zipfile($a);reject(fn()=>Archive::read($p),'Bound decompression before JSON parse');
    $p=zipfile(['configuration.json'=>$body]);reject(fn()=>Archive::read($p),'Require both exact members');
    $a=$base;$a['MANIFEST.json']=str_repeat('x',2049);$p=zipfile($a);reject(fn()=>Archive::read($p),'Bound manifest bytes');
    echo json_encode(['assertions'=>$n,'native_sql'=>false,'status'=>'PASS'])."\n";
}finally{foreach($files as $p)if(is_file($p))unlink($p);}
