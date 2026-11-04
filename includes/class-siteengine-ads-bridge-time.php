<?php
/** Timestamp provenance: site-local vendor wall time versus conversion presentation. */
if (!defined('ABSPATH')) { exit; }
final class SiteEngine_Ads_Bridge_Time {
    public static function site_zone() {
        return new DateTimeZone(function_exists('wp_timezone_string') ? wp_timezone_string() : 'UTC');
    }
    /** New Ads milestones carry their offset; older naive vendor values use WP time. */
    public static function event_stamp($instant = null) {
        return (new DateTimeImmutable('@'.($instant ?? time())))->setTimezone(self::site_zone())->format('Y-m-d H:i:sP');
    }
    public static function business_date($instant = null) {
        $zone = new DateTimeZone((string)SiteEngine_Ads_Bridge_Config::instance()->get('client.timezone','UTC'));
        return (new DateTimeImmutable('@'.($instant ?? time())))->setTimezone($zone)->format('Y-m-d');
    }
    public static function conversion($raw,$fallback,$timezone) {
        $out=['value'=>'','error'=>''];
        try {
            $target=new DateTimeZone($timezone);$raw=trim((string)$raw);
            if (!preg_match('/^(\d{4}-\d{2}-\d{2})(?:[ T](\d{2}:\d{2}:\d{2})(Z|[+-]\d{2}:\d{2})?)?$/D',$raw,$m)) { throw new RuntimeException('Expected an exact date or datetime; unknown timestamp provenance must be reviewed.'); }
            $dateOnly=empty($m[2]);$wall=$m[1].' '.($dateOnly?$fallback:$m[2]);
            $zone=$dateOnly?$target:self::site_zone();$explicit=!empty($m[3]);
            if($explicit&&$m[3]!=='Z'&&((int)substr($m[3],1,2)>14||(int)substr($m[3],4,2)>59||((int)substr($m[3],1,2)===14&&(int)substr($m[3],4,2)!==0)))throw new RuntimeException('Invalid UTC offset.');
            if($explicit){$zone=new DateTimeZone($m[3]==='Z'?'+00:00':$m[3]);}
            $dt=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$wall,$zone);$err=DateTimeImmutable::getLastErrors();
            if(!$dt||($err&&($err['warning_count']||$err['error_count']))||$dt->format('Y-m-d H:i:s')!==$wall) { throw new RuntimeException('Invalid date/time or daylight-saving gap; no normalization was accepted.'); }
            if(!$explicit){
                // A naive wall time in a fall-back fold has two possible instants.
                $utc=DateTimeImmutable::createFromFormat('!Y-m-d H:i:s',$wall,new DateTimeZone('UTC'))->getTimestamp();
                $offsets=[];foreach($zone->getTransitions($utc-172800,$utc+172800)?:[] as$t){$offsets[$t['offset']]=true;}
                $matches=0;foreach(array_keys($offsets)as$offset){if((new DateTimeImmutable('@'.($utc-$offset)))->setTimezone($zone)->format('Y-m-d H:i:s')===$wall)$matches++;}
                if($matches>1){throw new RuntimeException('Ambiguous daylight-saving wall time; an explicit offset is required. Historical value left unchanged.');}
            }
            $out['value']=$dt->setTimezone($target)->format('Y-m-d H:i:sP');
        } catch(Throwable $e) { $out['error']=$e->getMessage(); }
        return $out;
    }
}
