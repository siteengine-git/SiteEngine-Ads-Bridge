<?php
/** Bounded trusted same-site external Converted evidence. No listeners or transport. */
if (!defined('ABSPATH')) { exit; }

final class SiteEngine_Ads_Bridge_Outcomes {
    const VERSION = '1';
    private const FIELDS = ['provider','tenant_id','customer_id','job_id','invoice_id','inquiry_id','completed_at','invoice_at','amount','currency','snapshot_sha256','value_policy_id'];

    public static function capability(): array {
        return ['contract_version'=>self::VERSION,'destination'=>SiteEngine_Ads_Bridge_Coordination::capability()['destination'],
            'callable'=>'seab_external_outcome_submit','source'=>'form','stage'=>'converted',
            'value_policy'=>'synthetic-usd-demo-1','google_call_delivery'=>false];
    }
    public static function table(string $kind): string {
        global $wpdb;
        if (!in_array($kind,['evidence','receipts'],true)) throw new InvalidArgumentException('invalid_table');
        return $wpdb->prefix.'se_ads_outcome_'.$kind;
    }
    public static function schema_sql(): array {
        $e=self::table('evidence'); $r=self::table('receipts');
        return ["CREATE TABLE `$e` (evidence_id varchar(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY, relationship_key char(64) NOT NULL, digest char(64) NOT NULL, body longtext NOT NULL, KEY relationship_key (relationship_key)) ENGINE=InnoDB",
            "CREATE TABLE `$r` (receipt_id char(64) NOT NULL PRIMARY KEY, body_digest char(64) NOT NULL, event_key char(64) NOT NULL, economic_key char(64) NULL, inquiry_key char(64) NULL, body longtext NOT NULL, response longtext NOT NULL, UNIQUE KEY event_key (event_key), UNIQUE KEY economic_key (economic_key), UNIQUE KEY inquiry_key (inquiry_key)) ENGINE=InnoDB"];
    }
    /** Explicit, additive only. Existing incompatible/partial tables are preserved. */
    public static function migrate(bool $apply=false): array {
        if (!$apply) return self::schema_sql();
        if (!current_user_can('manage_options')) throw new RuntimeException('administrator_required');
        return SiteEngine_Ads_Bridge_Setup::with_configuration(static function() {
            global $wpdb;
            foreach (array_combine(['evidence','receipts'],self::schema_sql()) as $kind=>$sql) {
                $exists=self::rows($wpdb->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',self::table($kind)));
                if (!$exists) self::sql($sql);
            }
            self::physical(); return ['status'=>'complete','schema_version'=>'1'];
        });
    }
    private static function sql(string $sql): void {
        global $wpdb;
        if ($wpdb->query($sql)===false || $wpdb->last_error!=='') throw new RuntimeException('outcome_write_unconfirmed');
    }
    private static function rows(string $sql): array {
        global $wpdb; $rows=$wpdb->get_results($sql,ARRAY_A);
        if ($wpdb->last_error!=='' || !is_array($rows)) throw new RuntimeException('outcome_read_unavailable');
        return $rows;
    }
    private static function physical(): void {
        global $wpdb;
        $spec=['evidence'=>['evidence_id'=>'varchar(128)','relationship_key'=>'char(64)','digest'=>'char(64)','body'=>'longtext'],
            'receipts'=>['receipt_id'=>'char(64)','body_digest'=>'char(64)','event_key'=>'char(64)','economic_key'=>'char(64)','inquiry_key'=>'char(64)','body'=>'longtext','response'=>'longtext']];
        foreach ($spec as $kind=>$columns) {
            $t=self::table($kind);
            $engine=self::rows($wpdb->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s',$t));
            if (count($engine)!==1 || strtolower($engine[0]['ENGINE'])!=='innodb') throw new RuntimeException('outcome_schema_migration_required');
            $actual=self::rows("SHOW COLUMNS FROM `$t`");
            if (count($actual)!==count($columns)) throw new RuntimeException('outcome_schema_incompatible');
            foreach ($actual as $c) {
                $nullable=$kind==='receipts' && in_array($c['Field'],['economic_key','inquiry_key'],true);
                if (($columns[$c['Field']]??null)!==strtolower($c['Type']) || $c['Null']!==($nullable?'YES':'NO')) throw new RuntimeException('outcome_schema_incompatible');
            }
            $indexes=[];
            foreach (self::rows("SHOW INDEX FROM `$t`") as $i) $indexes[$i['Key_name']][]=[(int)$i['Non_unique'],$i['Column_name'],(int)$i['Seq_in_index'],$i['Sub_part']];
            $unique=$kind==='evidence'?['PRIMARY'=>'evidence_id']:['PRIMARY'=>'receipt_id','event_key'=>'event_key','economic_key'=>'economic_key','inquiry_key'=>'inquiry_key'];
            foreach ($unique as $name=>$column) if (($indexes[$name]??[])!==[[0,$column,1,null]]) throw new RuntimeException('outcome_schema_incompatible');
        }
    }
    private static function json(array $v): string { return json_encode($v,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES); }
    private static function key(array $v): string { return hash('sha256',self::json($v)); }
    private static function id($v): string { return \SiteEngine\AdsBridge\Connectors\FieldEdge\Foundation::id($v); }
    private static function digest($v): string {
        if (!is_string($v) || !preg_match('/^[a-f0-9]{64}$/D',$v)) throw new InvalidArgumentException('invalid_digest');
        return $v;
    }
    private static function normalized(array $v): array {
        if (array_diff(self::FIELDS,array_keys($v))) throw new InvalidArgumentException('missing_outcome_field');
        $out=[];
        foreach (self::FIELDS as $k) {
            $value=$v[$k];
            if (in_array($k,['completed_at','invoice_at'],true)) $value=\SiteEngine\AdsBridge\Connectors\FieldEdge\Foundation::timestamp($value);
            elseif ($k==='amount') $value=\SiteEngine\AdsBridge\Connectors\FieldEdge\Foundation::amount($value);
            elseif ($k==='snapshot_sha256') $value=self::digest($value);
            else $value=self::id($value);
            $out[$k]=$value;
        }
        if (strlen($out['inquiry_id'])>40) throw new InvalidArgumentException('invalid_inquiry_identity');
        return $out;
    }
    /** Shared guards serialize existing configuration/inquiry writers, not business state. */
    public static function protected_call(callable $work): array {
        return SiteEngine_Ads_Bridge_Setup::with_executor(static function() use($work) {
            $guard=SiteEngine_Ads_Bridge_Coordination::enter_reconciliation();
            if (!$guard['ok']) throw new RuntimeException($guard['reason']);
            try {
                global $wpdb;
                if (!SiteEngine_Ads_Bridge_Inquiries::schema_ready()) throw new RuntimeException('outcome_inquiry_schema_unavailable');
                $pending=self::rows('SELECT inquiry_id FROM `'.SiteEngine_Ads_Bridge_Inquiries::table_name()."` WHERE state NOT IN ('active','archived') OR contract_version<>'1' LIMIT 1");
                if ($pending) throw new RuntimeException('outcome_pending_inquiry');
                if (class_exists('SiteEngine\\AdsBridge\\Import\\Store')) {
                    $job=\SiteEngine\AdsBridge\Import\Store::job();
                    if ($job && in_array($job['status'],['running','uncertain'],true)) throw new RuntimeException('outcome_configuration_recovery_required');
                }
                self::physical();
                $result=$work(); SiteEngine_Ads_Bridge_Coordination::assert_owner();
            } finally {
                if (!SiteEngine_Ads_Bridge_Coordination::release()) throw new RuntimeException('outcome_lock_release_unconfirmed');
            }
            return $result;
        });
    }
    /** Operator review is stored independently; submission booleans cannot create it. */
    public static function review(array $evidence): array {
        if (!current_user_can('manage_options')) throw new RuntimeException('administrator_required');
        $id=self::id($evidence['evidence_id']??null);
        $outcome=self::normalized($evidence['outcome']??[]);
        $provenance=$evidence['provenance']??[];
        $reference=self::id($provenance['reference']??null);
        $digest=self::digest($provenance['sha256']??null);
        $synthetic=($evidence['synthetic']??null)===true;
        $body=self::json(['outcome'=>$outcome,'provenance'=>['reference'=>$reference,'sha256'=>$digest],
            'synthetic'=>$synthetic,'reviewer_id'=>get_current_user_id(),'source'=>$evidence['source']??'form']);
        return self::protected_call(static function() use($id,$outcome,$body) {
            global $wpdb; $t=self::table('evidence');
            $prior=self::rows($wpdb->prepare("SELECT * FROM `$t` WHERE evidence_id=%s",$id));
            if ($prior) {
                $p=json_decode($prior[0]['body'],true,16,JSON_THROW_ON_ERROR); unset($p['reviewed_at']);
                $same=self::json($p)===$body;
                return ['status'=>$same?'recorded':'conflict','reason_code'=>$same?'evidence_already_reviewed':'evidence_identity_reused','evidence_id'=>$id];
            }
            $reviewed=json_decode($body,true,16,JSON_THROW_ON_ERROR); $reviewed['reviewed_at']=gmdate('Y-m-d\TH:i:s\Z'); $body=self::json($reviewed);
            $key=self::key([$outcome['provider'],$outcome['tenant_id'],$outcome['job_id']]);
            if ($wpdb->insert($t,['evidence_id'=>$id,'relationship_key'=>$key,'digest'=>hash('sha256',$body),'body'=>$body])!==1) throw new RuntimeException('outcome_evidence_write_unconfirmed');
            $read=self::rows($wpdb->prepare("SELECT body FROM `$t` WHERE evidence_id=%s",$id));
            if (($read[0]['body']??null)!==$body) throw new RuntimeException('outcome_evidence_readback_unconfirmed');
            return ['status'=>'recorded','reason_code'=>'evidence_reviewed','evidence_id'=>$id];
        });
    }
    private static function response(string $status,string $reason,string $receipt,string $digest,array $extra=[]): array {
        return ['contract_version'=>'1','status'=>$status,'reason_code'=>$reason,'receipt_id'=>$receipt,'body_sha256'=>$digest,'outcome_revision'=>1,'export_eligible'=>false]+$extra;
    }
    public static function read(string $receipt): ?array {
        self::digest($receipt); global $wpdb;
        $r=self::rows($wpdb->prepare('SELECT * FROM `'.self::table('receipts').'` WHERE receipt_id=%s',$receipt));
        if (!$r) return null;
        $r=$r[0];
        if (hash('sha256',$r['body'])!==$r['body_digest']) throw new RuntimeException('outcome_receipt_corrupt');
        $response=json_decode($r['response'],true,16,JSON_THROW_ON_ERROR);
        if (($response['receipt_id']??null)!==$r['receipt_id'] || ($response['body_sha256']??null)!==$r['body_digest'] ||
            !in_array($response['status']??null,['recorded','held'],true) || ($response['export_eligible']??null)!==false) throw new RuntimeException('outcome_receipt_corrupt');
        return $r;
    }
    /** Exact JSON bytes, not re-encoded PHP arrays, define an operation body. */
    public static function submit(string $body): array {
        $digest=hash('sha256',$body); $receipt='';
        try {
            if (strlen($body)>16384) throw new InvalidArgumentException('request_too_large');
            $r=json_decode($body,true,16,JSON_THROW_ON_ERROR);
            if (!is_array($r) || array_diff(array_keys($r),array_merge(self::FIELDS,['contract_version','destination','operation_id','event_id','event_sha256','evidence_id']))) throw new InvalidArgumentException('unsupported_request_fields');
            if (($r['contract_version']??null)!=='1' || ($r['destination']??null)!==self::capability()['destination']) throw new InvalidArgumentException('outcome_destination_or_version_mismatch');
            $o=self::normalized($r); self::id($r['event_id']??null); self::digest($r['event_sha256']??null); self::id($r['evidence_id']??null);
            $receipt=self::key([self::capability()['destination'],$o['provider'],$o['tenant_id'],self::id($r['operation_id']??null)]);
            return self::protected_call(static function() use($r,$o,$body,$digest,$receipt) {
                global $wpdb;
                $old=self::read($receipt);
                if ($old) return $old['body']===$body?json_decode($old['response'],true,16,JSON_THROW_ON_ERROR):self::response('conflict','operation_identity_reused',$receipt,$digest);
                $t=self::table('receipts'); $event=self::key([$o['provider'],$o['tenant_id'],$r['event_id']]);
                if (self::rows($wpdb->prepare("SELECT receipt_id FROM `$t` WHERE event_key=%s",$event))) return self::response('conflict','source_event_identity_reused',$receipt,$digest);
                $e=self::rows($wpdb->prepare('SELECT * FROM `'.self::table('evidence').'` WHERE evidence_id=%s',$r['evidence_id']));
                $reason='association_missing'; $associated=false; $canonical='';
                if ($e) {
                    if (hash('sha256',$e[0]['body'])!==$e[0]['digest']) throw new RuntimeException('outcome_evidence_corrupt');
                    $e=json_decode($e[0]['body'],true,16,JSON_THROW_ON_ERROR);
                    $links=self::rows($wpdb->prepare('SELECT body FROM `'.self::table('evidence').'` WHERE relationship_key=%s',self::key([$o['provider'],$o['tenant_id'],$o['job_id']])));
                    $inquiries=[]; foreach ($links as $link) $inquiries[]=json_decode($link['body'],true,16,JSON_THROW_ON_ERROR)['outcome']['inquiry_id'];
                    if (count(array_unique($inquiries))!==1) $reason='association_ambiguous';
                    elseif ($e['outcome']!==$o) $reason='reviewed_evidence_mismatch';
                    else {
                        $inq=SiteEngine_Ads_Bridge_Inquiries::get($o['inquiry_id']);
                        if (!$inq || !in_array($inq['state'],['active','archived'],true)) $reason='inquiry_unavailable';
                        else {
                            $associated=true; $reason='outcome_persisted';
                            if ($e['source']!=='form') $reason='call_origin_google_contract_unsupported';
                            elseif (!preg_match('/^ff:[1-9][0-9]*:[1-9][0-9]*$/D',$inq['transaction_id']??'')) $reason='form_origin_required';
                            elseif ($o['provider']!=='fieldedge' || !$e['synthetic'] || $o['value_policy_id']!=='synthetic-usd-demo-1' || $o['currency']!=='USD' || $o['amount']==='0.00') $reason='value_policy_requires_review';
                        }
                    }
                }
                $economic=self::key([$o['provider'],$o['tenant_id'],$o['job_id'],'converted']);
                $inquiry=self::key([$o['inquiry_id'],'converted']);
                $primary=null;
                if ($reason==='outcome_persisted') {
                    $prior=self::rows($wpdb->prepare("SELECT * FROM `$t` WHERE economic_key=%s OR inquiry_key=%s",$economic,$inquiry));
                    if ($prior) {
                        $p=json_decode($prior[0]['body'],true,16,JSON_THROW_ON_ERROR);
                        if (self::normalized($p)!==$o) $reason='additional_or_revised_outcome_requires_review';
                        else $canonical=$prior[0]['receipt_id'];
                    } else $primary=true;
                }
                $result=self::response($reason==='outcome_persisted'?'recorded':'held',$reason,$receipt,$digest,
                    ['association_status'=>$associated?'attributed':'unmatched','canonical_receipt_id'=>$canonical?:($primary?$receipt:''),'inquiry_id'=>$o['inquiry_id']]);
                $saved=$wpdb->insert($t,['receipt_id'=>$receipt,'body_digest'=>$digest,'event_key'=>$event,'economic_key'=>$primary?$economic:null,'inquiry_key'=>$primary?$inquiry:null,'body'=>$body,'response'=>self::json($result)]);
                if ($saved!==1) throw new RuntimeException('outcome_receipt_write_unconfirmed');
                $read=self::read($receipt);
                if (!$read || $read['response']!==self::json($result)) throw new RuntimeException('outcome_receipt_readback_unconfirmed');
                return $result;
            });
        } catch (InvalidArgumentException|JsonException $e) { return self::response('conflict','invalid_request',$receipt,$digest); }
        catch (Throwable $e) {
            $reason=preg_match('/^(outcome_|coordination_)[a-z_]+$/D',$e->getMessage())?$e->getMessage():'outcome_protection_unavailable';
            return self::response('retry',$reason,$receipt,$digest);
        }
    }
    /** CB identity stays independent of the AB relationship. No CB table writes. */
    public static function call_evidence(string $sid,string $inquiry_id): array {
        if (!current_user_can('manage_options')) throw new RuntimeException('administrator_required');
        if (!preg_match('/^CA[a-f0-9]{32}$/Di',$sid)) throw new InvalidArgumentException('invalid_call_sid');
        self::id($inquiry_id);
        if (!class_exists('SiteEngine\\CallBridge\\DB\\CallLogRepository')) return ['status'=>'held','reason_code'=>'call_bridge_interface_unavailable'];
        $call=(new \SiteEngine\CallBridge\DB\CallLogRepository())->findByCallSid($sid,true);
        if (!$call) return ['status'=>'held','reason_code'=>'call_evidence_unavailable'];
        if (($call['twilio_call_sid']??null)!==$sid || ($call['transaction_id']??null)!=='twilio:'.$sid) return ['status'=>'held','reason_code'=>'call_identity_mismatch'];
        $result=['status'=>'held','reason_code'=>'call_origin_google_contract_unsupported','call_identity'=>'twilio:'.$sid,
            'inquiry_id'=>$inquiry_id,'call_status'=>$call['call_status']??null,'duration_seconds'=>$call['call_duration_sec']??null,
            'qualification_status'=>$call['qualification_status']??null,'created_at'=>$call['created_at']??null,
            'snapshot_sha256'=>self::key($call)];
        return self::protected_call(static function() use($result) {
            if (!SiteEngine_Ads_Bridge_Inquiries::get($result['inquiry_id'])) return ['status'=>'held','reason_code'=>'inquiry_unavailable'];
            global $wpdb; $t=self::table('evidence');
            $id='cb-'.self::key([$result['call_identity'],$result['inquiry_id']]);
            $body=self::json($result+['reviewer_id'=>get_current_user_id()]);
            $old=self::rows($wpdb->prepare("SELECT body FROM `$t` WHERE evidence_id=%s",$id));
            if ($old && $old[0]['body']!==$body) return $result+['evidence_id'=>$id,'review_reason'=>'call_evidence_changed_requires_review'];
            if (!$old && $wpdb->insert($t,['evidence_id'=>$id,'relationship_key'=>self::key(['cb',$result['call_identity']]),'digest'=>hash('sha256',$body),'body'=>$body])!==1) throw new RuntimeException('outcome_call_evidence_write_unconfirmed');
            $read=self::rows($wpdb->prepare("SELECT body FROM `$t` WHERE evidence_id=%s",$id));
            if (($read[0]['body']??null)!==$body) throw new RuntimeException('outcome_call_evidence_readback_unconfirmed');
            return $result+['evidence_id'=>$id,'persisted'=>true];
        });
    }
}
