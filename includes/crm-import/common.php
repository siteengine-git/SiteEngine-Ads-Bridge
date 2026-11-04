<?php
namespace SiteEngine\AdsBridge\Import;
use RuntimeException;
use Throwable;

final class JSON {
    public static function canonical($v):string {
        $sort=static function($x)use(&$sort){if(!is_array($x))return $x;if(!array_is_list($x))ksort($x,SORT_STRING);return array_map($sort,$x);};
        return json_encode($sort($v),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }
    public static function hash($v):string {return hash('sha256',self::canonical($v));}
}
final class Contract {
    public static function catalog():array {return json_decode(file_get_contents(__DIR__.'/contract.json'),true,32,JSON_THROW_ON_ERROR);}
    public static function validate(array $v):void {
        $config=['bindings'=>['form.primary'=>0,'field.service_type'=>'service_type','field.outcome_reason'=>'customer_rating']];
        $known=[];$c=self::catalog();foreach($c['logical_kinds'] as $key=>$kind)$known[$key]=$c['shapes'][$kind];Format::validate($v,$config,$known);
        foreach($v['objects'] as $key=>$d){$text=JSON::canonical($d['body']);preg_match_all('/\{\{([^{}]+)\}\}/',$text,$matches);
            foreach($matches[1] as $token)if(!in_array($token,['base.business_name','base.phone','base.privacy_policy_url','base.street_address','base.city','base.state','base.zip','base.timezone','base.business_type','base.services_offered','secrm.service_label'],true))throw new RuntimeException('Unsupported template placeholder '.$token.' in '.$key.'. Ask the producer owner to bind its consumer.');
            if($d['kind']==='step'&&isset($d['body']['settings']['campaign_ref'])){
                $ref=$d['body']['settings']['campaign_ref'];if(($v['objects'][$ref]['body']['funnel_ref']??null)!==$d['body']['funnel_ref'])throw new RuntimeException('Cross-funnel email reference: '.$key);
            }
        }
        self::order($v['objects']);
    }
    /** Topological order, including cross-object parents. Cycles fail before writes. */
    public static function order(array $objects):array {
        $out=[];
        while($objects){$progress=false;foreach($objects as $key=>$d){$refs=[];$b=$d['body'];
            foreach(['funnel_ref','parent_ref','sequence_ref'] as $f)if(!empty($b[$f]))$refs[]=$b[$f];
            if(isset($b['settings']['campaign_ref']))$refs[]=$b['settings']['campaign_ref'];
            if(array_diff($refs,array_keys($out)))continue;
            $out[$key]=$d;unset($objects[$key]);$progress=true;
        }if(!$progress)throw new RuntimeException('configuration_relationship_cycle_or_missing_reference');}
        return $out;
    }
}
final class Archive {
    public static function read(string $path):array {
        if(!class_exists('ZipArchive'))throw new RuntimeException('PHP ZIP extension is required for configuration upload.');
        if(!is_file($path)||filesize($path)>8388608)throw new RuntimeException('Configuration ZIP exceeds 8 MiB.');
        $z=new \ZipArchive();if($z->open($path)!==true)throw new RuntimeException('Invalid configuration ZIP.');
        try {
            if($z->numFiles!==2)throw new RuntimeException('Only MANIFEST.json and configuration.json are permitted.');
            $seen=[];$total=0;
            for($i=0;$i<2;$i++){$s=$z->statIndex($i);$name=$s['name'];
                if(!in_array($name,['MANIFEST.json','configuration.json'],true)||isset($seen[$name])||($s['encryption_method']??0)!==0)throw new RuntimeException('Unexpected, duplicate or encrypted ZIP member.');
                $seen[$name]=true;$total+=$s['size'];
                if($s['size']>8388608||($name==='MANIFEST.json'&&$s['size']>2048)||$total>8390656)throw new RuntimeException('Uncompressed configuration exceeds its bound.');
            }
            $body=$z->getFromName('configuration.json');$m=json_decode($z->getFromName('MANIFEST.json'),true,16,JSON_THROW_ON_ERROR);
            if(!is_string($body)||!is_array($m)||array_diff(array_keys($m),['format','configuration_sha256','execution_enabled'])||($m['format']??null)!=='siteengine.crm.configuration-zip/1'||($m['execution_enabled']??null)!==false||($m['configuration_sha256']??null)!==hash('sha256',$body))throw new RuntimeException('Configuration manifest does not match its payload.');
            $v=json_decode($body,true,128,JSON_THROW_ON_ERROR);if(!is_array($v))throw new RuntimeException('Configuration object required.');Contract::validate($v);return $v;
        } finally {$z->close();}
    }
}

