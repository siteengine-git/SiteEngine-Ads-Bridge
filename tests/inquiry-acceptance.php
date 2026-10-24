<?php
/**
 * Dynamic 0.6.3 inquiry/adoption/reconciliation acceptance.
 * Run: php tests/inquiry-acceptance.php
 */
namespace FluentCrm\App\Models {
    class Subscriber {
        public static $rows = array();
        public $id;
        public $email = '';
        public $phone = '';
        public $first_name = '';
        public $last_name = '';
        public $ip = '';
        public $city = '';
        public $postal_code = '';
        public $state = '';
        public $country = '';
        public $tags = array();
        public $lists = array();
        public $ignore_field_writes = false;
        public $ignore_tag_writes = false;
        public $throw_on_field_write = false;
        public $field_write_calls = 0;
        public $tag_write_calls = 0;
        public $field_batches = array();
        public $throw_after_field_keys = 0;
        public $throw_after_detach = false;
        public $throw_after_attach = false;
        public $on_field_write = null;
        private $custom = array();

        public function __construct( $id, array $custom, $lifecycle = 'won' ) {
            $this->id = (int) $id;
            $this->custom = $custom;
            $this->email = 'current-' . $id . '@example.test';
            $this->phone = '85055501' . str_pad( (string) $id, 2, '0', STR_PAD_LEFT );
            $this->first_name = 'Current';
            $this->last_name = 'Contact' . $id;
            $this->city = 'Tallahassee';
            $this->postal_code = '32301';
            $this->state = 'FL';
            $this->country = 'US';
            $this->setLifecycle( $lifecycle );
        }
        public static function find( $id ) { return isset( self::$rows[ (int) $id ] ) ? self::$rows[ (int) $id ] : null; }
        public function custom_fields() { return $this->custom; }
        public function syncCustomFieldValues( $values, $delete_other = true, $custom_fields = array() ) {
            unset( $custom_fields );
            $this->field_write_calls++;
            $this->field_batches[]=$values;
            if (is_callable($this->on_field_write)) { $callback=$this->on_field_write; $this->on_field_write=null; $callback(); }
            if ( $this->throw_on_field_write ) { throw new \RuntimeException( 'injected_field_write_failure' ); }
            if ( $this->ignore_field_writes ) { return array(); }
            $changed = array();
            $processed=0;
            foreach ( (array) $values as $k => $v ) {
                if ( '' === $v ) {
                    if ( $delete_other && array_key_exists( $k, $this->custom ) ) {
                        unset( $this->custom[ $k ] );
                        $changed[ $k ] = '';
                    }
                } elseif ( ! array_key_exists( $k, $this->custom ) || (string) $v !== (string) $this->custom[ $k ] ) {
                    $this->custom[ $k ] = $v;
                    $changed[ $k ] = $v;
                }
                if ($this->throw_after_field_keys && ++$processed >= $this->throw_after_field_keys) { throw new \RuntimeException('injected_partial_field_crash'); }
            }
            if ( $changed ) {
                \do_action( 'fluent_crm/contact_custom_data_updated', array_filter( $values, fn($v)=>''!==$v ), $this, $changed );
            }
            return $changed;
        }
        public function attachTags( $ids ) {
            $this->tag_write_calls++;
            if ( $this->ignore_tag_writes ) { return $this; }
            $added = array();
            foreach ( (array) $ids as $id ) {
                $id = (int) $id;
                $found = false;
                foreach ( $this->tags as $tag ) { if ( (int) $tag->id === $id ) { $found = true; } }
                if ( ! $found ) { $this->tags[] = (object) array( 'id' => $id, 'title' => self::tagTitle( $id ) ); $added[] = $id; }
            }
            if ($this->throw_after_attach) throw new \RuntimeException('injected_after_attach_crash');
            if ( $added ) { \do_action( 'fluent_crm/contact_added_to_tags', $this, $added ); }
            return $this;
        }
        public function detachTags( $ids ) {
            $this->tag_write_calls++;
            if ( $this->ignore_tag_writes ) { return $this; }
            $ids = array_map( 'intval', (array) $ids );
            $this->tags = array_values( array_filter( $this->tags, function ( $tag ) use ( $ids ) { return ! in_array( (int) $tag->id, $ids, true ); } ) );
            if ($this->throw_after_detach) throw new \RuntimeException('injected_after_detach_crash');
            return true;
        }
        public function setLifecycle( $role ) {
            $ids = array( 'new'=>101, 'qualified'=>102, 'unqualified'=>103, 'won'=>104 );
            $this->tags = array();
            if ( isset( $ids[ $role ] ) ) { $this->tags[] = (object) array( 'id'=>$ids[ $role ], 'title'=>ucfirst( $role ) ); }
        }
        private static function tagTitle( $id ) {
            return array( 101=>'New', 102=>'Qualified', 103=>'Unqualified', 104=>'Won' )[ (int) $id ] ?? 'Tag';
        }
    }
}

namespace {
    define( 'ABSPATH', __DIR__ . '/' );
    define( 'ARRAY_A', 'ARRAY_A' );
    define( 'SEAB_VERSION', '0.6.3' );

    $GLOBALS['seab_options'] = array('seab_inquiry_schema_version'=>'2');
    $GLOBALS['seab_hooks'] = array();
    function add_action( $hook, $callback, $priority = 10, $args = 1 ) {
        $GLOBALS['seab_hooks'][$hook][$priority][] = array($callback, $args);
    }
    function do_action( $hook, ...$args ) {
        $callbacks = $GLOBALS['seab_hooks'][$hook] ?? array();
        ksort($callbacks);
        foreach ($callbacks as $group) foreach ($group as [$callback, $count]) call_user_func_array($callback, array_slice($args,0,$count));
    }
    function current_time( $format ) { return 'Y-m-d' === $format ? '2026-10-23' : '2026-10-23 12:34:56'; }
    function absint( $value ) { return abs( (int) $value ); }
    function wp_json_encode( $value, $flags = 0 ) { return json_encode( $value, $flags ); }
    function get_option( $key, $default = false ) { return isset( $GLOBALS['seab_options'][ $key ] ) ? $GLOBALS['seab_options'][ $key ] : $default; }
    function update_option( $key, $value, $autoload = null ) { unset( $autoload ); $GLOBALS['seab_options'][ $key ] = $value; return true; }

    final class SiteEngine_Ads_Bridge_Config {
        private static $instance;
        public $overrides = array();
        public static function instance() { return self::$instance ?: ( self::$instance = new self() ); }
        public static function lifecycle_roles() { return array('new', 'qualified', 'unqualified', 'won'); }
        public function get( $key, $default = null ) {
            $map = array(
                'fluentcrm.lifecycle_automation.enabled' => true,
                'fluentcrm.tags.lifecycle.new' => 'New',
                'fluentcrm.tags.lifecycle.qualified' => 'Qualified',
                'fluentcrm.tags.lifecycle.unqualified' => 'Unqualified',
                'fluentcrm.tags.lifecycle.won' => 'Won',
                'fluent_forms.form_id' => 3,
                'exports.qualified_leads.date_field' => 'qualified_at',
                'fluentcrm.lifecycle_automation.unqualified_at_field' => 'unqualified_at',
                'exports.won_jobs.date_field' => 'closed_date',
                'exports.won_jobs.value_field' => 'invoiced_amount',
                'fluentcrm.lifecycle_automation.unqualified_reason_field' => 'customer_rating',
            );
            if ( array_key_exists( $key, $this->overrides ) ) { return $this->overrides[$key]; }
            return array_key_exists( $key, $map ) ? $map[ $key ] : $default;
        }
    }

    final class SEAB_Inquiry_Test_WPDB {
        public $prefix = 'wp_';
        public $rows = array();
        public $submissions = array();
        public $fail_activating_write = false;
        public $missing_recovery_columns = false;
        public $fail_checkpoint = false;
        public $after_checkpoint = null;
        public $locks = array();
        public $deny_locks = false;
        public $lock_unavailable = false;
        public $lose_lock = false;
        public $fail_release = false;
        public $storage_engine = 'InnoDB';
        public $fail_query = '';
        public $fail_archive = false;
        public $fail_activate = false;
        private $transaction_rows = null;
        private $next_id = 1;
        public function get_charset_collate() { return ''; }
        public function prepare( $query, ...$args ) {
            if ( 1 === count( $args ) && is_array( $args[0] ) ) { $args = $args[0]; }
            foreach ( $args as $arg ) {
                $rep = is_int( $arg ) || is_float( $arg ) || ctype_digit( (string) $arg ) ? (string) $arg : "'" . str_replace( "'", "''", (string) $arg ) . "'";
                $query = preg_replace( '/%[dsf]/', $rep, $query, 1 );
            }
            return $query;
        }
        public function get_var( $sql ) {
            if (preg_match("/SELECT GET_LOCK\\('([^']+)', 0\\)/",$sql,$m)) {
                if ($this->lock_unavailable) return null;
                if ($this->deny_locks || (isset($this->locks[$m[1]]) && 1!==$this->locks[$m[1]])) return 0;
                $this->locks[$m[1]]=1;
                return 1;
            }
            if (preg_match("/SELECT IS_USED_LOCK\\('([^']+)'\\) = CONNECTION_ID\\(\\)/",$sql,$m)) {
                if ($this->lose_lock) { unset($this->locks[$m[1]]); return null; }
                return isset($this->locks[$m[1]]) ? (int)(1===$this->locks[$m[1]]) : null;
            }
            if (preg_match("/SELECT RELEASE_LOCK\\('([^']+)'\\)/",$sql,$m)) {
                if ($this->fail_release) return null;
                if (!isset($this->locks[$m[1]])) return null;
                if (1!==$this->locks[$m[1]]) return 0;
                unset($this->locks[$m[1]]); return 1;
            }
            if ( false !== strpos( $sql, 'SHOW TABLES LIKE' ) ) {
                if ( false !== strpos( $sql, 'wp_se_ads_inquiries' ) ) { return 'wp_se_ads_inquiries'; }
                if ( false !== strpos( $sql, 'wp_fluentform_submissions' ) ) { return 'wp_fluentform_submissions'; }
                if ( false !== strpos( $sql, 'wp_fc_tags' ) ) { return 'wp_fc_tags'; }
            }
            if (preg_match("/WHERE title = '([^']+)'/",$sql,$m)) return array('New'=>101,'Qualified'=>102,'Unqualified'=>103,'Won'=>104)[$m[1]] ?? null;
            if ( preg_match( '/SELECT COUNT\(\*\) FROM `wp_se_ads_inquiries` WHERE contact_id = (\d+)/', $sql, $m ) ) {
                $rows = array_filter( $this->rows, fn($r)=>(int)$r['contact_id']===(int)$m[1] );
                if ( false !== strpos($sql, "state = 'activating'") ) $rows = array_filter($rows,fn($r)=>'activating'===$r['state']);
                return count($rows);
            }
            if ( preg_match( "/SELECT COUNT\(\*\) FROM `wp_se_ads_inquiries` WHERE state='([^']+)'/", $sql, $m ) ) {
                return count( array_filter( $this->rows, fn($r)=>(string)$r['state']===$m[1] ) );
            }
            if ( false !== strpos( $sql, 'SELECT COUNT(*) FROM `wp_se_ads_inquiries`' ) ) { return count( $this->rows ); }
            return null;
        }
        private function unquote( $s ) { return str_replace( "''", "'", trim( $s, "'" ) ); }
        public function get_row( $sql, $format = null ) {
            unset( $format );
            if (false!==strpos($sql,'SHOW TABLE STATUS WHERE Name')) return array('Engine'=>$this->storage_engine);
            if ( preg_match( '/SELECT id, form_id FROM `wp_fluentform_submissions` WHERE id = (\d+)/', $sql, $m ) ) {
                $id=(int)$m[1]; return isset($this->submissions[$id]) ? array('id'=>$id,'form_id'=>$this->submissions[$id]['form_id']) : null;
            }
            if ( preg_match( '/SELECT \* FROM `wp_se_ads_inquiries` WHERE (.+) LIMIT 1/', $sql, $m ) ) {
                $where=$m[1];
                foreach ( $this->rows as $row ) {
                    $match=false;
                    if ( preg_match( "/request_event_id = '([^']+)' OR inquiry_id = '([^']+)'/", $where, $w ) ) $match = ((string)$row['request_event_id']===$this->unquote($w[1]) || (string)$row['inquiry_id']===$this->unquote($w[2]));
                    elseif ( preg_match( "/inquiry_id = '([^']+)' OR request_event_id = '([^']+)'/", $where, $w ) ) $match = ((string)$row['inquiry_id']===$this->unquote($w[1]) || (string)$row['request_event_id']===$this->unquote($w[2]));
                    elseif ( preg_match( '/active_contact_id = (\d+)/', $where, $w ) ) $match = ((int)$row['active_contact_id']===(int)$w[1]);
                    elseif ( preg_match( "/inquiry_id = '([^']+)'/", $where, $w ) ) $match = ((string)$row['inquiry_id']===$this->unquote($w[1]));
                    elseif ( preg_match( "/reconciliation_id = '([^']+)'/", $where, $w ) ) $match = ((string)$row['reconciliation_id']===$this->unquote($w[1]));
                    elseif ( preg_match( "/transaction_id = '([^']+)'/", $where, $w ) ) $match = ((string)$row['transaction_id']===$this->unquote($w[1]));
                    if ($match) return $row;
                }
                return null;
            }
            if ( preg_match( '/SELECT inquiry_id,contact_id FROM `wp_se_ads_inquiries` WHERE transaction_id = \'([^\']+)\'/', $sql, $m ) ) {
                foreach($this->rows as $r) if((string)$r['transaction_id']===$this->unquote($m[1])) return array('inquiry_id'=>$r['inquiry_id'],'contact_id'=>$r['contact_id']);
                return null;
            }
            if ( preg_match( '/SELECT \* FROM `wp_se_ads_inquiries` WHERE active_contact_id = (\d+)/', $sql, $m ) ) {
                foreach($this->rows as $r) if((int)$r['active_contact_id']===(int)$m[1]) return $r;
                return null;
            }
            return null;
        }
        public function insert( $table, $data, $formats = null ) {
            unset( $formats ); if ( 'wp_se_ads_inquiries' !== $table ) return false;
            foreach($this->rows as $r) {
                if((string)$r['inquiry_id']===(string)$data['inquiry_id']) return false;
                if(!empty($data['active_contact_id']) && (int)$r['active_contact_id']===(int)$data['active_contact_id']) return false;
                if(!empty($data['transaction_id']) && (string)$r['transaction_id']===(string)$data['transaction_id']) return false;
                if(!empty($data['request_event_id']) && (string)$r['request_event_id']===(string)$data['request_event_id']) return false;
            }
            $data['id']=$this->next_id++; $this->rows[]=$data; return 1;
        }
        public function update( $table, $data, $where, $formats = null, $where_formats = null ) {
            unset($formats,$where_formats); if('wp_se_ads_inquiries'!==$table) return false;
            if ($this->fail_checkpoint && isset($data['projection_verified_at_utc']) && null!==$data['projection_verified_at_utc']) return 0;
            if ($this->fail_activating_write && 'activating' === ($data['state'] ?? '')) return false;
            if ($this->fail_archive && 'archived' === ($data['state'] ?? '')) return 0;
            if ($this->fail_activate && 'active' === ($data['state'] ?? '')) return 0;
            $n=0;
            foreach($this->rows as &$r) {
                $ok=true; foreach($where as $k=>$v) { if((string)$r[$k] !== (string)$v) {$ok=false; break;} }
                if($ok){ foreach($data as $k=>$v)$r[$k]=$v; $n++; }
            }
            unset($r);
            if (isset($data['projection_verified_at_utc']) && is_callable($this->after_checkpoint)) { $callback=$this->after_checkpoint; $this->after_checkpoint=null; $callback(); }
            return $n;
        }
        public function query( $sql ) {
            if ($sql===$this->fail_query) return false;
            if ('START TRANSACTION'===$sql) $this->transaction_rows=$this->rows;
            if ('COMMIT'===$sql) $this->transaction_rows=null;
            if ('ROLLBACK'===$sql && null!==$this->transaction_rows) { $this->rows=$this->transaction_rows; $this->transaction_rows=null; }
            return 0;
        }
        public function close() {
            if (null!==$this->transaction_rows) $this->rows=$this->transaction_rows;
            $this->transaction_rows=null; $this->locks=array(); return true;
        }
        public function get_results( $sql, $format = null ) {
            unset($format);
            if(false!==strpos($sql,'SHOW COLUMNS FROM')) return $this->missing_recovery_columns ? array() : array(
                array('Field'=>'projection_plan','Type'=>'longtext','Null'=>'YES'),
                array('Field'=>'projection_plan_hash','Type'=>'char(64)','Null'=>'YES'),
                array('Field'=>'projection_verified_at_utc','Type'=>'datetime','Null'=>'YES')
            );
            if(false===strpos($sql,'FROM `wp_se_ads_inquiries`')) return array();
            $rows=$this->rows;
            if(false!==strpos($sql,"state IN ('active','archived')")) $rows=array_values(array_filter($rows,fn($r)=>in_array($r['state'],array('active','archived'),true)));
            if(false!==strpos($sql,"state IN ('prepared','activating')")) $rows=array_values(array_filter($rows,fn($r)=>in_array($r['state'],array('prepared','activating'),true)));
            if(preg_match('/WHERE contact_id = (\d+)/',$sql,$m)) $rows=array_values(array_filter($rows,fn($r)=>(int)$r['contact_id']===(int)$m[1]));
            if(preg_match('/`(qualified_at|unqualified_at|closed_date)` IS NOT NULL/',$sql,$m)) { $f=$m[1]; $rows=array_values(array_filter($rows,fn($r)=>null!==$r[$f] && ''!==$r[$f])); }
            if(false!==strpos($sql,"transaction_id IS NOT NULL")) $rows=array_values(array_filter($rows,fn($r)=>!empty($r['transaction_id'])));
            if(preg_match('/contact_id IN \(([^)]+)\)/',$sql,$m)){ $ids=array_map('intval',explode(',',$m[1])); $rows=array_values(array_filter($rows,fn($r)=>in_array((int)$r['contact_id'],$ids,true))); }
            if(preg_match('/LIMIT (\d+),(\d+)/',$sql,$m)) $rows=array_slice($rows,(int)$m[1],(int)$m[2]);
            return $rows;
        }
    }

    $GLOBALS['wpdb'] = new SEAB_Inquiry_Test_WPDB();
    $GLOBALS['wpdb']->submissions = array(
        37=>array('form_id'=>3), 38=>array('form_id'=>3), 39=>array('form_id'=>3),
    );

    $c23 = new \FluentCrm\App\Models\Subscriber( 23, array(
        'submission_id'=>'37','qualified_at'=>'2026-10-23 11:44:44','unqualified_at'=>'','closed_date'=>'2026-10-23','invoiced_amount'=>'567.89','customer_rating'=>'','ads_conversion_value_frozen'=>'567.89'
    ), 'won' );
    \FluentCrm\App\Models\Subscriber::$rows[23]=$c23;
    $c24 = new \FluentCrm\App\Models\Subscriber( 24, array('submission_id'=>'999','qualified_at'=>'','unqualified_at'=>'','closed_date'=>'','invoiced_amount'=>'','customer_rating'=>''), 'new' );
    \FluentCrm\App\Models\Subscriber::$rows[24]=$c24;
    $c25 = new \FluentCrm\App\Models\Subscriber( 25, array('submission_id'=>'39','qualified_at'=>'','unqualified_at'=>'','closed_date'=>'','invoiced_amount'=>'','customer_rating'=>''), 'new' );
    \FluentCrm\App\Models\Subscriber::$rows[25]=$c25;

    require dirname(__DIR__) . '/includes/class-siteengine-ads-bridge-lifecycle.php';
    require dirname(__DIR__) . '/includes/class-siteengine-ads-bridge-inquiries.php';
    SiteEngine_Ads_Bridge_Lifecycle::init();
    SiteEngine_Ads_Bridge_Inquiries::init();

    $passes=0; $fails=0;
    function check_inq($ok,$msg){ global $passes,$fails; if($ok){$passes++;echo "PASS: $msg\n";}else{$fails++;echo "FAIL: $msg\n";} }
    function uuid_for($suffix){ return 'inq_00000000-0000-4000-8000-' . str_pad((string)$suffix,12,'0',STR_PAD_LEFT); }

    $inqA=uuid_for(1); $inqB=uuid_for(2); $missing=uuid_for(9);
    $adopt=array('contract_version'=>'1','event_id'=>'evt_adopt_23','site_instance_id'=>'site-test','contact_id'=>23,'inquiry_id'=>$inqA,'head_version'=>1);
    $r=SiteEngine_Ads_Bridge_Inquiries::adopt_current_inquiry($adopt);
    check_inq('adopted'===$r['status'],'existing current inquiry adopts successfully');
    check_inq('ff:3:37'===$r['transaction_id'],'adoption freezes canonical pre-repeat transaction ID');
    $a=SiteEngine_Ads_Bridge_Inquiries::active_for_contact(23);
    check_inq($inqA===$a['inquiry_id'] && 'won'===$a['lifecycle'],'adopted row is active and preserves Won lifecycle');
    check_inq('567.89'==(string)$a['invoiced_amount'] && '2026-10-23'===$a['closed_date'],'adoption preserves prior revenue and close date');
    $r2=SiteEngine_Ads_Bridge_Inquiries::adopt_current_inquiry($adopt);
    check_inq('adopted'===$r2['status'] && 'already_adopted'===$r2['reason_code'],'adoption retry is idempotent');

    $bad=SiteEngine_Ads_Bridge_Inquiries::adopt_current_inquiry(array('contract_version'=>'1','event_id'=>'evt_bad','site_instance_id'=>'site-test','contact_id'=>24,'inquiry_id'=>uuid_for(24),'head_version'=>1));
    check_inq('rejected'===$bad['status'] && 'current_submission_binding_unresolved'===$bad['reason_code'],'adoption fails closed on unresolved existing submission_id');

    // Simulate the repeat form feed overwriting the contact projection AFTER adoption.
    $c23->syncCustomFieldValues(array('submission_id'=>'38'),false);
    $still=SiteEngine_Ads_Bridge_Inquiries::active_for_contact(23);
    check_inq('ff:3:37'===$still['transaction_id'] && 37===(int)$still['submission_id'],'later contact submission overwrite cannot rebind adopted inquiry');

    $prep=array(
        'contract_version'=>'1','event_id'=>'evt_prepare_B','site_instance_id'=>'site-test','contact_id'=>23,
        'previous_inquiry_id'=>$inqA,'proposed_inquiry_id'=>$inqB,'expected_head_version'=>1,'request_id'=>'req_B',
        'source'=>array('type'=>'fluent_form','reference'=>'form:3:entry:38','form_id'=>3,'entry_id'=>38),
    );
    $pr=SiteEngine_Ads_Bridge_Inquiries::prepare_reconciliation($prep);
    check_inq('ready'===$pr['status'] && 'prepared'===$pr['reason_code'],'repeat inquiry prepare succeeds against adopted previous inquiry');
    $b=SiteEngine_Ads_Bridge_Inquiries::get($inqB);
    check_inq('prepared'===$b['state'] && 'ff:3:38'===$b['transaction_id'],'new inquiry is prepared with its own immutable form binding');
    $pr2=SiteEngine_Ads_Bridge_Inquiries::prepare_reconciliation($prep);
    check_inq('ready'===$pr2['status'] && 'already_prepared'===$pr2['reason_code'],'prepare retry is idempotent');

    $unadopted=array(
        'contract_version'=>'1','event_id'=>'evt_prepare_missing','site_instance_id'=>'site-test','contact_id'=>25,
        'previous_inquiry_id'=>$missing,'proposed_inquiry_id'=>uuid_for(10),'expected_head_version'=>1,'request_id'=>'req_missing',
        'source'=>array('type'=>'fluent_form','form_id'=>3,'entry_id'=>39),
    );
    $mr=SiteEngine_Ads_Bridge_Inquiries::prepare_reconciliation($unadopted);
    check_inq('retry'===$mr['status'] && 'previous_inquiry_not_adopted'===$mr['reason_code'],'repeat prepare refuses mutable legacy snapshot when previous inquiry was not adopted');

    $activation=array(
        'contract_version'=>'1','event_id'=>'evt_activate_B','site_instance_id'=>'site-test','reconciliation_id'=>$pr['reconciliation_id'],
        'contact_id'=>23,'previous_inquiry_id'=>$inqA,'proposed_inquiry_id'=>$inqB,'head_version'=>2,
    );
    $ar=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
    check_inq('committed'===$ar['status'],'activation commits after CRM Ops head activation');
    $old=SiteEngine_Ads_Bridge_Inquiries::get($inqA); $new=SiteEngine_Ads_Bridge_Inquiries::get($inqB);
    check_inq('archived'===$old['state'] && 'ff:3:37'===$old['transaction_id'],'previous inquiry archives without attribution mutation');
    check_inq('active'===$new['state'] && 'new'===$new['lifecycle'] && 'ff:3:38'===$new['transaction_id'],'new inquiry activates as New with new immutable transaction');
    $custom=$c23->custom_fields();
    check_inq('38'===(string)$custom['submission_id'] && ''===(string)($custom['qualified_at']??'') && ''===(string)($custom['closed_date']??'') && ''===(string)($custom['invoiced_amount']??''),'current contact projection resets inquiry-specific Ads fields');
    $tagids=array_map(fn($t)=>(int)$t->id,$c23->tags);
    check_inq(array(101)===$tagids,'activation makes New the sole lifecycle tag');
    $ar2=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
    check_inq('committed'===$ar2['status'] && 'already_committed'===$ar2['reason_code'],'activation retry is idempotent');

    // New inquiry progresses; sync must mutate milestones only, never binding.
    $c23->syncCustomFieldValues(array('submission_id'=>'999','qualified_at'=>'2026-10-24 09:00:00'),false);
    $c23->setLifecycle('qualified');
    SiteEngine_Ads_Bridge_Inquiries::sync_active_from_subscriber($c23);
    $new=SiteEngine_Ads_Bridge_Inquiries::get($inqB);
    check_inq('qualified'===$new['lifecycle'] && '2026-10-24 09:00:00'===$new['qualified_at'],'active inquiry mirrors current lifecycle milestone');
    check_inq('ff:3:38'===$new['transaction_id'] && 38===(int)$new['submission_id'],'active sync never rebinds immutable transaction from contact submission_id');

    $q=SiteEngine_Ads_Bridge_Inquiries::candidates('qualified',25,array(23),0);
    $qids=array_column($q,'inquiry_id');
    check_inq(2===count($q) && in_array($inqA,$qids,true) && in_array($inqB,$qids,true),'qualified candidate selection preserves both archived and active reached stages');
    $w=SiteEngine_Ads_Bridge_Inquiries::candidates('won',25,array(23),0);
    check_inq(1===count($w) && $inqA===$w[0]['inquiry_id'],'archived prior Won inquiry remains export candidate');

    $resA=SiteEngine_Ads_Bridge_Inquiries::resolver_for_inquiry($old);
    $resB=SiteEngine_Ads_Bridge_Inquiries::resolver_for_inquiry($new);
    check_inq('ff:3:37'===$resA['order_id'] && 'ff:3:38'===$resB['order_id'],'resolver preserves independent canonical transaction IDs across repeat inquiries');

    // Reset regression cases use real lifecycle callbacks and FluentCRM's
    // selective blank-key deletion semantics, rather than merge-only doubles.
    function reset_case($id) {
        $entry = $id * 10;
        $GLOBALS['wpdb']->submissions[$entry] = array('form_id'=>3);
        $GLOBALS['wpdb']->submissions[$entry+1] = array('form_id'=>3);
        $contact = new \FluentCrm\App\Models\Subscriber($id, array(
            'submission_id'=>(string)$entry,
            'qualified_at'=>'2026-10-20 10:00:00', 'unqualified_at'=>'2026-10-19 10:00:00',
            'closed_date'=>'2026-10-21', 'invoiced_amount'=>'4500', 'customer_rating'=>'Other',
            'lost_date'=>'2026-10-19', 'ads_export_status'=>'exported', 'ads_exported_at'=>'2026-10-22',
            'ads_conversion_value_frozen'=>'4500', 'ads_export_error'=>'old error',
            'se_crm_current_inquiry_id'=>uuid_for(900+$id), 'service_type'=>'Repair',
            'se_google_ad_user_data'=>'granted',
        ), 'won');
        \FluentCrm\App\Models\Subscriber::$rows[$id] = $contact;
        $contact->attachTags(array(201));
        $old=uuid_for($id*2); $new=uuid_for($id*2+1);
        $adopt=array('contract_version'=>'1','event_id'=>'evt_adopt_'.$id,'site_instance_id'=>'site-test','contact_id'=>$id,'inquiry_id'=>$old,'head_version'=>1);
        $adopted=SiteEngine_Ads_Bridge_Inquiries::adopt_current_inquiry($adopt);
        if ('adopted'!==$adopted['status']) throw new \RuntimeException('reset fixture adoption failed');
        $contact->syncCustomFieldValues(array('submission_id'=>(string)($entry+1)),false);
        $prep=array('contract_version'=>'1','event_id'=>'evt_prepare_'.$id,'site_instance_id'=>'site-test','contact_id'=>$id,'previous_inquiry_id'=>$old,'proposed_inquiry_id'=>$new,'expected_head_version'=>1,'request_id'=>'req_'.$id,'source'=>array('type'=>'fluent_form','form_id'=>3,'entry_id'=>$entry+1));
        $ready=SiteEngine_Ads_Bridge_Inquiries::prepare_reconciliation($prep);
        if ('ready'!==$ready['status']) throw new \RuntimeException('reset fixture prepare failed');
        $activation=array('contract_version'=>'1','event_id'=>'evt_activate_'.$id,'site_instance_id'=>'site-test','reconciliation_id'=>$ready['reconciliation_id'],'contact_id'=>$id,'previous_inquiry_id'=>$old,'proposed_inquiry_id'=>$new,'head_version'=>2);
        return array($contact,$old,$new,$activation);
    }

    $semantics=new \FluentCrm\App\Models\Subscriber(99,array('closed_date'=>'2026-10-21'),'new');
    $semantics->syncCustomFieldValues(array('closed_date'=>''),false);
    check_inq('2026-10-21'===($semantics->custom_fields()['closed_date']??''),'test double preserves blanks with deleteOtherValues=false, matching installed FluentCRM');
    $semantics->syncCustomFieldValues(array('closed_date'=>''),true);
    check_inq(!array_key_exists('closed_date',$semantics->custom_fields()),'test double deletes only supplied blank keys with deleteOtherValues=true');

    [$contact,$old,$new,$activation]=reset_case(40);
    $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
    $values=$contact->custom_fields();
    $clear_keys=array('qualified_at','unqualified_at','closed_date','invoiced_amount','customer_rating','lost_date','ads_export_status','ads_exported_at','ads_conversion_value_frozen','ads_export_error');
    check_inq('committed'===$result['status'] && !array_intersect($clear_keys,array_keys($values)),'activation deletes all old inquiry milestones, revenue, reason and export status');
    check_inq('401'===($values['submission_id']??'') && uuid_for(940)===($values['se_crm_current_inquiry_id']??'') && 'Repair'===($values['service_type']??'') && 'granted'===($values['se_google_ad_user_data']??''),'reset preserves CRM Ops identity, unrelated fields and consent while projecting the new submission');
    check_inq(in_array(201,array_map(fn($t)=>(int)$t->id,$contact->tags),true),'reset preserves non-lifecycle tags');
    $prior=SiteEngine_Ads_Bridge_Inquiries::get($old);
    check_inq('4500'==(string)$prior['invoiced_amount'] && '2026-10-21'===$prior['closed_date'] && '2026-10-20 10:00:00'===$prior['qualified_at'],'real lifecycle hooks do not restamp or erase the previous inquiry during reset');
    $contact->attachTags(array(102));
    check_inq('2026-10-23 12:34:56'===($contact->custom_fields()['qualified_at']??''),'lifecycle compliance resumes after activation completes');

    [$contact,$old,$new,$activation]=reset_case(41);
    $contact->ignore_field_writes=true;
    $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
    check_inq('retry'===$result['status'] && 'projection_fields_not_persisted'===$result['reason_code'],'silent field-write failure cannot receive a committed acknowledgment');
    check_inq('active'===SiteEngine_Ads_Bridge_Inquiries::get($old)['state'] && 'activating'===SiteEngine_Ads_Bridge_Inquiries::get($new)['state'],'failed field reset does not archive old history or activate the new inquiry');
    check_inq(!SiteEngine_Ads_Bridge_Inquiries::is_reconciling() && false===SiteEngine_Ads_Bridge_Inquiries::sync_active_from_subscriber($contact),'request guard releases but pending activation still blocks old-history synchronization');
    $contact->ignore_field_writes=false;
    $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
    check_inq('committed'===$result['status'],'same activation can complete after the field persistence failure is repaired');

    [$contact,$old,$new,$activation]=reset_case(42);
    $contact->ignore_tag_writes=true;
    $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
    check_inq('retry'===$result['status'] && 'projection_lifecycle_not_persisted'===$result['reason_code'],'silent lifecycle-tag failure cannot receive a committed acknowledgment');
    $contact->syncCustomFieldValues(array('service_type'=>'Maintenance'),false);
    check_inq(!isset($contact->custom_fields()['closed_date']) && !isset($contact->custom_fields()['qualified_at']),'pending activation prevents later lifecycle callbacks from restoring old Won milestones');
    check_inq('4500'==(string)SiteEngine_Ads_Bridge_Inquiries::get($old)['invoiced_amount'],'pending activation keeps prior revenue intact across later callbacks');
    $contact->ignore_tag_writes=false;
    $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
    check_inq('committed'===$result['status'],'same activation can complete after the tag persistence failure is repaired');

    [$contact,$old,$new,$activation]=reset_case(43);
    $contact->throw_on_field_write=true;
    $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
    check_inq('retry'===$result['status'] && !SiteEngine_Ads_Bridge_Inquiries::is_reconciling(),'projection exception returns retry and always releases the request guard');
    $contact->throw_on_field_write=false;
    $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
    check_inq('committed'===$result['status'],'same activation resumes after a projection exception');

    [$contact,$old,$new,$activation]=reset_case(44);
    SiteEngine_Ads_Bridge_Config::instance()->overrides['fluentcrm.tags.lifecycle.new']='Missing New';
    $calls=$contact->field_write_calls;
    $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
    check_inq('retry'===$result['status'] && 'projection_lifecycle_tags_unresolved'===$result['reason_code'] && $calls===$contact->field_write_calls,'missing lifecycle tag is detected before any reset field write');
    SiteEngine_Ads_Bridge_Config::instance()->overrides=array();

    [$contact,$old,$new,$activation]=reset_case(45);
    $contact->syncCustomFieldValues(array('sale_total'=>'987','qualified_date'=>'2026-10-20 10:00:00'),false);
    SiteEngine_Ads_Bridge_Config::instance()->overrides=array('exports.won_jobs.value_field'=>'sale_total','exports.qualified_leads.date_field'=>'qualified_date');
    $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
    check_inq('committed'===$result['status'] && !isset($contact->custom_fields()['sale_total']) && !isset($contact->custom_fields()['qualified_date']),'activation also clears configured lifecycle/revenue field aliases');
    SiteEngine_Ads_Bridge_Config::instance()->overrides=array();

    [$contact,$old,$new,$activation]=reset_case(46);
    $GLOBALS['wpdb']->fail_activating_write=true;
    $calls=$contact->field_write_calls;
    $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
    check_inq('retry'===$result['status'] && 'activation_state_not_persisted'===$result['reason_code'] && $calls===$contact->field_write_calls,'activation marker must persist before any CRM reset begins');
    $GLOBALS['wpdb']->fail_activating_write=false;

    [$contact,$old,$new,$activation]=reset_case(47);
    SiteEngine_Ads_Bridge_Config::instance()->overrides['exports.won_jobs.value_field']='submission_id';
    $calls=$contact->field_write_calls;
    $result=SiteEngine_Ads_Bridge_Inquiries::commit_activation($activation);
    check_inq('retry'===$result['status'] && 'projection_field_mapping_invalid'===$result['reason_code'] && $calls===$contact->field_write_calls,'invalid reset field mapping cannot overwrite the canonical submission binding');
    SiteEngine_Ads_Bridge_Config::instance()->overrides=array();

    if (!defined('SEAB_INQUIRY_TEST_INCLUDE')) {
        echo "\nSUMMARY: {$passes} PASS, {$fails} FAIL\n";
        exit($fails?1:0);
    }
}
