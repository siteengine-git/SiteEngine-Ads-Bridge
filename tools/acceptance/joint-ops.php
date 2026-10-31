<?php
/** Review-only installed operations. Run ONLY by Alma Codex after the corresponding release. */
define( 'SEAB_JOINT_LIBRARY_ONLY', true );
require __DIR__ . '/joint-owned-observer.php';

class JX_Stop extends RuntimeException {}
function jx_assert( $condition, $reason ) { if ( ! $condition ) { throw new JX_Stop( $reason ); } }
function jx_json( $path ) {
    jx_assert( is_file( $path ) && filesize( $path ) <= 1048576, 'private JSON absent/oversize' );
    $v = json_decode( file_get_contents( $path ), true ); jx_assert( is_array( $v ), 'JSON malformed' ); return $v;
}
function jx_save( $path, array $v ) {
    $bytes = json_encode( $v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
    jx_assert( strlen( $bytes ) <= 1048576, 'journal ceiling' );
    $tmp = $path . '.next'; $f = fopen( $tmp, 'x' ); jx_assert( $f !== false, 'pending journal .next requires review' );
    chmod( $tmp, 0600 ); jx_assert( fwrite( $f, $bytes ) === strlen( $bytes ), 'journal write incomplete' );
    fflush( $f ); if ( function_exists( 'fsync' ) ) { jx_assert( fsync( $f ), 'journal fsync failed' ); } fclose( $f );
    jx_assert( rename( $tmp, $path ), 'journal replace failed' );
}
function jx_intent( array $j ) { jx_assert( isset( $GLOBALS['jx_path'] ), 'journal context absent' ); jx_save( $GLOBALS['jx_path'], $j ); }
function jx_scope( $config, callable $body ) {
    global $wpdb; $db = $wpdb; $last = $db->last_error;
    $p = new ReflectionProperty( $config, 'config' ); $p->setAccessible( true ); $before = $p->getValue( $config );
    try { return $body(); }
    finally { $wpdb = $db; $db->last_error = $last; $p->setValue( $config, $before ); }
}
function jx_fault_response( array $response, $mode, $grant_id, $negative = null ) {
    switch ( $mode ) {
        case 'negative': jx_assert( is_array( $negative ), 'real negative stamp required' ); $response['_se_consent_server'] = $negative; break;
        case 'pending': case 'failed': $response['_se_consent_server']['state'] = $mode; break;
        case 'null': $response['_se_consent_server'] = null; break;
        case 'malformed': $response['_se_consent_server'] = 'injected-malformed'; break;
        case 'unknown': $response['_se_consent_server']['contract_version'] = 999; break;
        case 'unsigned': unset( $response['_se_consent_server'] ); break;
        default: throw new RuntimeException( 'unknown response fault' );
    }
    $response['se_consent_receipt_id'] = $grant_id; return $response;
}
/** Request-local read view. No candidate/vendor code or database row is edited. */
class JX_Read_View {
    public $prefix, $last_error = '', $entry, $response, $unavailable = false, $reads = 0;
    private $db;
    public function __construct( $db ) { $this->db = $db; $this->prefix = $db->prefix; }
    public function prepare( ...$args ) { return $this->db->prepare( ...$args ); }
    public function esc_like( $s ) { return $this->db->esc_like( $s ); }
    private function read( $method, $sql, $format = null ) {
        jx_assert( is_string( $sql ) && preg_match( '/^\s*(SELECT|SHOW)\b/i', $sql ), 'view refuses non-read SQL' );
        $this->reads++;
        if ( $this->unavailable && strpos( $sql, 'se_consent_receipts' ) !== false && stripos( $sql, 'SELECT' ) !== false ) {
            $this->last_error = 'joint test: unavailable receipt read'; return null;
        }
        $v = $format === null ? $this->db->$method( $sql ) : $this->db->$method( $sql, $format );
        $this->last_error = $this->db->last_error;
        if ( $method === 'get_row' && is_array( $v ) && isset( $v['response'], $v['id'] )
            && (int) $v['id'] === $this->entry && $this->response !== null && strpos( $sql, 'fluentform_submissions' ) !== false ) {
            $v['response'] = $this->response;
        }
        return $v;
    }
    public function get_row( $sql, $format = null ) { return $this->read( 'get_row', $sql, $format ); }
    public function get_results( $sql, $format = null ) { return $this->read( 'get_results', $sql, $format ); }
    public function get_col( $sql ) { return $this->read( 'get_col', $sql ); }
    public function get_var( $sql ) { return $this->read( 'get_var', $sql ); }
    public function query( $sql ) { throw new RuntimeException( 'fault view cannot mutate' ); }
}
function jx_tables( array $a ) {
    $names = array( 'forms' => 'fluentform_forms', 'submissions' => 'fluentform_submissions', 'details' => 'fluentform_entry_details',
        'contacts' => 'fc_subscribers', 'meta' => 'fc_subscriber_meta', 'pivot' => 'fc_subscriber_pivot',
        'tags' => 'fc_tags', 'lists' => 'fc_lists', 'inquiries' => 'se_ads_inquiries',
        'receipts' => 'se_consent_receipts', 'events' => 'se_consent_events', 'chains' => 'se_consent_chains' );
    foreach ( $names as $k => $suffix ) { jx_assert( ( $a['tables'][$k] ?? '' ) === $a['prefix'] . $suffix, 'actual table map missing/mismatch: ' . $k ); }
    return $a['tables'];
}
function jx_allocation( array $a ) {
    jx_assert( ( $a['assignment'] ?? '' ) === 'ALMA-JOINT-CONSENT-ADS-20261031'
        && ( $a['site'] ?? '' ) === 'https://alma.siteengine.space/'
        && preg_match( '/^[A-Za-z0-9_]{1,48}$/D', $a['prefix'] ?? '' )
        && is_int( $a['form_id'] ?? null ) && $a['form_id'] > 0
        && is_int( $a['owner_uid'] ?? null ) && $a['owner_uid'] > 0
        && ( $a['run_dir'] ?? '' ) === '/home/alma.siteengine.space/.se-joint-acceptance/joint-consent-ads-20261031-01'
        && preg_match( '/^jct_[a-f0-9]{32}$/D', $a['marker'] ?? '' ), 'allocation identity invalid/unfilled' );
    jx_tables( $a );
    jx_assert( preg_match( '/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/D', $a['fixture_qualified_at'] ?? '' )
        && preg_match( '/^\d{4}-\d\d-\d\d$/D', $a['fixture_closed_date'] ?? '' )
        && is_string( $a['site_instance_id'] ?? null ) && strlen( $a['site_instance_id'] ) > 0 && strlen( $a['site_instance_id'] ) <= 128, 'allocated fixture milestone/site identity missing' );
    jx_assert( ( $a['reference_map_reviewed'] ?? false ) === true && is_array( $a['logical_references'] ?? null ) && count( $a['logical_references'] ) <= 40, 'reviewed reference map required' );
    foreach ( $a['logical_references'] as $ref ) {
        jx_assert( preg_match( '/^[A-Za-z0-9_]+$/D', $ref['table'] ?? '' ) && strpos( $ref['table'], $a['prefix'] ) === 0
            && preg_match( '/^[A-Za-z0-9_]+$/D', $ref['column'] ?? '' )
            && in_array( $ref['kind'] ?? '', array( 'entry', 'contact', 'inquiry' ), true ), 'reference map invalid' );
    }
    foreach ( array( array( 'details', 'submission_id', 'entry' ), array( 'meta', 'subscriber_id', 'contact' ),
        array( 'pivot', 'subscriber_id', 'contact' ), array( 'inquiries', 'contact_id', 'contact' ), array( 'inquiries', 'submission_id', 'entry' ) ) as $required ) {
        $found = false; foreach ( $a['logical_references'] as $ref ) {
            if ( $ref['table'] === $a['tables'][$required[0]] && $ref['column'] === $required[1] && $ref['kind'] === $required[2] ) { $found = true; }
        }
        jx_assert( $found, 'required logical reference edge missing' );
    }
    return $a;
}
function jx_db_row( $table, $id ) { global $wpdb; return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$table}` WHERE id=%d", $id ), ARRAY_A ); }
function jx_owned_entry( array $a, array $j, $label ) {
    $e = $j['entries'][$label] ?? null; jx_assert( is_array( $e ), 'entry not admitted' );
    $row = jx_db_row( $a['tables']['submissions'], $e['id'] ); $response = json_decode( $row['response'] ?? '', true );
    jx_assert( is_array( $response ) && (int) $row['form_id'] === $a['form_id']
        && ( $response['se_joint_marker'] ?? '' ) === $a['marker'] && ( $response['se_joint_case'] ?? '' ) === $label
        && hash( 'sha256', $row['response'] ) === $e['response_sha256'], 'owned historical response changed/missing' );
    if ( isset( $e['details_sha256'] ) ) {
        global $wpdb; $details = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$a['tables']['details']}` WHERE submission_id=%d ORDER BY id LIMIT 129", $e['id'] ), ARRAY_A );
        jx_assert( is_array( $details ) && ! $wpdb->last_error && count( $details ) === $e['details_count'] && hash( 'sha256', wp_json_encode( $details ) ) === $e['details_sha256'], 'owned serialized details changed/unavailable' );
    }
    return $row;
}
function jx_audit( array $a, array &$j, $entry ) {
    global $wpdb;
    $s = json_decode( $entry['response'], true )['_se_consent_server'] ?? null;
    jx_assert( is_array( $s ) && isset( $s['receipt_row_id'], $s['chain_id'] ), 'admission requires real acknowledged capture' );
    $link = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$a['tables']['events']}` WHERE receipt_row_id=%d", $s['receipt_row_id'] ), ARRAY_A );
    jx_assert( is_array( $link ) && $link['chain_id'] === $s['chain_id'], 'real event link absent' );
    if ( isset( $j['chain_id'] ) ) { jx_assert( $j['chain_id'] === $s['chain_id'], 'second browser chain refused' ); }
    $j['chain_id'] = $s['chain_id'];
    $links = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$a['tables']['events']}` WHERE chain_id=%s LIMIT 11", $j['chain_id'] ), ARRAY_A );
    $head = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$a['tables']['chains']}` WHERE chain_id=%s", $j['chain_id'] ), ARRAY_A );
    jx_assert( is_array( $links ) && count( $links ) <= 10 && is_array( $head ) && (int) $head['revision'] <= 10, 'audit ceiling/unavailable head' );
    foreach ( $links as $l ) {
        $receipt = jx_db_row( $a['tables']['receipts'], $l['receipt_row_id'] );
        jx_assert( is_array( $receipt ) && $receipt['receipt_id'] === $l['receipt_id'], 'receipt/link mismatch' );
        $digest = hash( 'sha256', wp_json_encode( $receipt ) );
        if ( isset( $j['audit'][(string) $l['receipt_row_id']] ) ) {
            jx_assert( $j['audit'][(string) $l['receipt_row_id']]['sha256'] === $digest
                && $j['audit'][(string) $l['receipt_row_id']]['link_sha256'] === hash( 'sha256', wp_json_encode( $l ) ), 'immutable audit/link changed' );
        }
        $j['audit'][(string) $l['receipt_row_id']] = array( 'receipt_id' => $receipt['receipt_id'], 'sha256' => $digest, 'link_sha256' => hash( 'sha256', wp_json_encode( $l ) ) );
    }
    if ( isset( $j['head'] ) ) {
        foreach ( array( 'revision', 'advertising_barrier', 'personalization_barrier' ) as $k ) { jx_assert( (int) $head[$k] >= (int) $j['head'][$k], 'head/barrier regressed' ); }
    }
    $j['head'] = array( 'revision' => (int) $head['revision'], 'advertising_barrier' => $head['advertising_barrier'],
        'personalization_barrier' => $head['personalization_barrier'], 'sha256' => hash( 'sha256', wp_json_encode( $head ) ) );
}
function jx_contact( array $a, array $j, $label ) {
    jx_assert( isset( $j['contacts'][$label] ), 'contact not owned' );
    $s = \FluentCrm\App\Models\Subscriber::find( $j['contacts'][$label] );
    jx_assert( $s && $s->email === $a['marker'] . '.' . strtolower( $label ) . '@example.invalid', 'contact provenance mismatch' ); return $s;
}
function jx_probe( array $a, array $j, $label ) {
    $row = jx_owned_entry( $a, $j, $label ); $e = $j['entries'][$label];
    $s = jx_contact( $a, $j, $e['contact'] ); $cli = new SiteEngine_Ads_Bridge_CLI(); $c = SiteEngine_Ads_Bridge_Config::instance();
    $inquiry = isset( $j['inquiries'][$label] ) ? SiteEngine_Ads_Bridge_Inquiries::get( $j['inquiries'][$label] ) : null;
    jx_assert( is_array( $inquiry ) && (int) $inquiry['submission_id'] === $e['id'], 'probe requires owned persisted inquiry, never a rebound legacy CRM pointer' );
    jx_assert( (int) $inquiry['contact_id'] === (int) $s->id && $inquiry['transaction_id'] === 'ff:' . $a['form_id'] . ':' . $e['id'], 'canonical inquiry identity mismatch' );
    $q = seab_joint_method( $cli, 'build_qualified_lead_import', array( $s, $c, '12:00:00', $inquiry ) );
    $w = seab_joint_method( $cli, 'build_google_ads_import', array( $s, $c, '12:00:00', $inquiry ) );
    $browser = SiteEngine_Ads_Bridge_Runtime::inject_confirmation_payload( array(), (object) array( 'id' => $a['form_id'] ), array(), $e['id'], json_decode( $row['response'], true ) );
    $all = seab_joint_method( $cli, 'build_all_leads_row', array( $row, $a['tables']['submissions'], $c ) );
    $stamp = json_decode( $row['response'], true )['_se_consent_server'] ?? null;
    $authority = is_array( $stamp ) && is_callable( array( 'SiteEngine_Consent_Authority', 'current_eligibility' ) ) ? SiteEngine_Consent_Authority::current_eligibility( $stamp, $a['form_id'], (string) $c->get( 'consent.required_policy_version', '' ) ) : array();
    return array( 'authority' => $authority, 'browser' => isset( $browser['siteengine_ads_bridge'] ), 'qualified' => $q['exportable'], 'converted' => $w['exportable'],
        'all_leads' => $all['exportable'] && count( $all['values'] ) === 94, 'values' => $w['values'],
        'context' => array( 'cid' => $s->id, 'txn' => 'ff:' . $a['form_id'] . ':' . $e['id'], 'sink' => 'google_ads_import_converted_lead' ) );
}
function jx_expect( array $result, $allowed ) {
    foreach ( array( 'browser', 'qualified', 'converted' ) as $key ) { jx_assert( $result[$key] === $allowed, 'verdict mismatch: ' . $key ); }
    jx_assert( $result['all_leads'] === true, 'operational All_Leads suppressed' );
}
function jx_append( array $prepared ) {
    $calls = 0;
    $sink = static function( $pre, $args, $url ) use ( &$calls ) {
        jx_assert( strpos( $url, 'https://sheets.googleapis.com/' ) === 0 && strpos( $url, ':append?' ) !== false, 'unexpected HTTP path' );
        $calls++; return array( 'headers' => array(), 'body' => '{"updates":{"updatedRows":1,"updatedRange":"SYNTHETIC!A1:U1"}}', 'response' => array( 'code' => 200, 'message' => 'synthetic only' ), 'cookies' => array() );
    };
    add_filter( 'pre_http_request', $sink, PHP_INT_MAX, 3 );
    try {
        $r = seab_joint_method( new SiteEngine_Ads_Bridge_CLI(), 'google_sheets_append_row', array( 'SYNTHETIC-NOT-A-SHEET', 'Google_Ads_Import', $prepared['values'], 'SYNTHETIC-NOT-A-TOKEN', 'RAW', $prepared['context'] ) );
        return array( 'allowed' => $r['ok'], 'synthetic_calls' => $calls );
    } finally { remove_filter( 'pre_http_request', $sink, PHP_INT_MAX ); }
}
function jx_execute( $op, $label, array $a, array &$j ) {
    global $wpdb;
    $t = $a['tables'];
    if ( in_array( $op, array( 'attest', 'close' ), true ) ) {
        $ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM `{$t['contacts']}` WHERE email LIKE %s LIMIT 3", $wpdb->esc_like( $a['marker'] ) . '%' ) );
        jx_assert( is_array( $ids ) && ! $wpdb->last_error && count( $ids ) <= 2, 'marker contact ceiling/read failed' );
        foreach ( $ids as $id ) { jx_assert( in_array( (int) $id, array_values( $j['contacts'] ), true ), 'unadmitted marker contact; retain it' ); }
    }
    if ( $op === 'admit' ) {
        jx_assert( ! $j, 'journal already initialized' );
        seab_joint_observe( array( 'site' => $a['site'], 'prefix' => $a['prefix'], 'form_id' => $a['form_id'], 'cases' => array(), 'assignment' => $a['assignment'], 'phase' => 'J1' ) );
        foreach ( array( 'receipts', 'events', 'chains' ) as $key ) {
            $engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $t[$key] ) );
            jx_assert( strtoupper( (string) $engine ) === 'INNODB' && ! $wpdb->last_error, 'InnoDB prerequisite' );
        }
        $collision = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t['contacts']}` WHERE email LIKE %s", $a['marker'] . '%' ) );
        $entries = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t['submissions']}` WHERE response LIKE %s", '%' . $a['marker'] . '%' ) );
        jx_assert( (string) $collision === '0' && (string) $entries === '0', 'marker collision' );
        $form = jx_db_row( $t['forms'], $a['form_id'] ); jx_assert( is_array( $form ), 'allocated form absent' );
        $j0 = jx_json( $a['run_dir'] . '/J0-result.json' );
        jx_assert( hash_file( 'sha256', $a['run_dir'] . '/J0-result.json' ) === $a['j0_result_sha256']
            && $j0['assignment'] === $a['assignment'] && $j0['stage'] === 'J0' && $j0['status'] === 'PASS', 'independent Alma J0 completion receipt required' );
        $cfg = SiteEngine_Ads_Bridge_Config::instance();
        foreach ( array( 'won_tag_id' => array( 'tags', $cfg->get( 'fluentcrm.tags.lifecycle.won', '' ) ),
            'form_lead_tag_id' => array( 'tags', $cfg->get( 'fluentcrm.tags.sources.form', '' ) ), 'dnc_list_id' => array( 'lists', $cfg->get( 'crm_map.lists.do_not_contact', '' ) ) ) as $key => $expected ) {
            jx_assert( is_int( $a[$key] ?? null ) && $a[$key] > 0, 'allocated term ID missing' );
            $term = jx_db_row( $t[$expected[0]], $a[$key] ); jx_assert( is_array( $term ) && $term['title'] === $expected[1] && $expected[1] !== '', 'allocated term/config mismatch' );
        }
        foreach ( SiteEngine_Ads_Bridge_Config::lifecycle_roles() as $role ) {
            $id = $a['lifecycle_tag_ids'][$role] ?? null; jx_assert( is_int( $id ) && $id > 0, 'allocated lifecycle tag ID missing' );
            $term = jx_db_row( $t['tags'], $id ); jx_assert( is_array( $term ) && $term['title'] === $cfg->get( 'fluentcrm.tags.lifecycle.' . $role, '' ), 'lifecycle tag mapping mismatch' );
        }
        jx_assert( $a['won_tag_id'] === $a['lifecycle_tag_ids']['won'] && count( array_unique( $a['lifecycle_tag_ids'] ) ) === 4, 'lifecycle IDs must be distinct and mapped' );
        jx_assert( strpos( $form['form_fields'], 'se_consent_receipt_id' ) !== false, 'Consent owner must confirm existing supported receipt hidden field' );
        $cp = new ReflectionProperty( $cfg, 'config' ); $cp->setAccessible( true );
        $j = array( 'assignment' => $a['assignment'], 'marker' => $a['marker'], 'allocation_sha256' => $a['_sha256'],
            'baseline' => array( 'config_sha256' => hash( 'sha256', serialize( $cp->getValue( $cfg ) ) ), 'form_fields' => $form['form_fields'], 'active_plugins_sha256' => hash( 'sha256', serialize( get_option( 'active_plugins' ) ) ),
                'max_submission' => (int) $wpdb->get_var( "SELECT MAX(id) FROM `{$t['submissions']}`" ) ),
            'contacts' => array(), 'entries' => array(), 'inquiries' => array(), 'audit' => array(), 'results' => array(), 'pending' => null,
            'active_stage' => 'J0', 'stages' => array( 'J0' => 'PASS', 'J1' => 'NOT RUN', 'J2' => 'NOT RUN', 'J3' => 'NOT RUN', 'J4' => 'NOT RUN', 'J5' => 'NOT RUN', 'J6' => 'NOT RUN', 'J7' => 'NOT RUN' ) );
        return array( 'admission' => 'PASS', 'fixtures_created' => 0 );
    }
    if ( $op === 'begin' ) { jx_assert( isset( $j['stages'][$label] ) && $label !== 'J0', 'stage label' ); $j['active_stage'] = $label; return array( 'stage' => $label, 'status' => $j['stages'][$label] ); }
    if ( $op === 'browser-evidence' ) {
        jx_owned_entry( $a, $j, $label ); $file = $a['run_dir'] . '/browser-' . $label . '.json';
        $b = jx_json( $file ); $pin = trim( file_get_contents( $file . '.sha256' ) );
        $stamp = json_decode( jx_owned_entry( $a, $j, $label )['response'], true )['_se_consent_server'];
        jx_assert( hash_file( 'sha256', $file ) === $pin && $b['status'] === 'PASS' && $b['operation'] === 'submit ' . $label
            && $b['receipt_id'] === $stamp['receipt_id'] && $b['advertising_payload'] === ( $label !== 'B' ), 'browser receipt not matched to genuine persisted event' );
        if ( $label === 'B' ) { jx_assert( ( $b['pending_failed_ack_verified'] ?? false ) === true, 'real pending/failed ACK evidence required' ); }
        $j['browser'][$label] = array( 'sha256' => $pin, 'advertising_payload' => $b['advertising_payload'] ); return array( 'browser_receipt' => 'PASS' );
    }
    if ( $op === 'stage' ) { return jx_stage( $label, $a, $j ); }
    if ( $op === 'form' ) {
        $old = jx_db_row( $t['forms'], $a['form_id'] ); jx_assert( $old['form_fields'] === $j['baseline']['form_fields'], 'form drift' );
        $fields = json_decode( $old['form_fields'], true ); jx_assert( isset( $fields['fields'] ) && is_array( $fields['fields'] ), 'J0 must verify actual FF fields schema' );
        jx_assert( count( $a['marker_fields'] ?? array() ) === 2 && count( array_unique( array_column( array_column( $a['marker_fields'], 'attributes' ), 'name' ) ) ) === 2, 'two owner-reviewed hidden-field definitions required' );
        foreach ( $a['marker_fields'] as $field ) {
            $name = $field['attributes']['name'] ?? ''; jx_assert( in_array( $name, array( 'se_joint_marker', 'se_joint_case' ), true ) && $field['element'] === 'input_hidden', 'field template invalid' );
            foreach ( $fields['fields'] as $existing ) { jx_assert( ( $existing['attributes']['name'] ?? '' ) !== $name, 'field collision' ); }
            $field['attributes']['value'] = $name === 'se_joint_marker' ? $a['marker'] : ''; $fields['fields'][] = $field;
        }
        $j['patched_fields'] = wp_json_encode( $fields );
        $j['pending']['expected_form_sha256'] = hash( 'sha256', $j['patched_fields'] ); jx_intent( $j );
        jx_assert( $wpdb->update( $t['forms'], array( 'form_fields' => $j['patched_fields'] ), array( 'id' => $a['form_id'], 'form_fields' => $old['form_fields'] ) ) === 1, 'guarded form edit failed' );
        return array( 'field_additions' => 2, 'persistent_config_edits' => 0 );
    }
    if ( $op === 'contact' ) {
        jx_assert( in_array( $label, array( 'A', 'C' ), true ) && ! isset( $j['contacts'][$label] ) && count( $j['contacts'] ) < 2, 'contact ceiling/label' );
        $email = $a['marker'] . '.' . strtolower( $label ) . '@example.invalid';
        jx_assert( (string) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t['contacts']}` WHERE email=%s", $email ) ) === '0', 'contact collision before create' );
        $j['pending']['expected_email'] = $email; jx_intent( $j );
        $s = new \FluentCrm\App\Models\Subscriber(); $s->email = $a['marker'] . '.' . strtolower( $label ) . '@example.invalid';
        $s->first_name = $a['marker']; $s->last_name = 'Synthetic'; $s->status = 'subscribed';
        jx_assert( $s->save() && $s->id > 0, 'real Subscriber save failed' ); $j['contacts'][$label] = (int) $s->id;
        return array( 'owned_contact_id' => (int) $s->id );
    }
    if ( $op === 'attest' ) {
        jx_assert( in_array( $label, array( 'A', 'B', 'C' ), true ) && ! isset( $j['entries'][$label] ) && count( $j['entries'] ) < 10, 'entry ceiling/label' );
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t['submissions']}` WHERE form_id=%d AND id>%d AND response LIKE %s ORDER BY id LIMIT 11", $a['form_id'], $j['baseline']['max_submission'], '%' . $a['marker'] . '%' ), ARRAY_A );
        jx_assert( is_array( $rows ) && count( $rows ) <= 10, 'submission ceiling/read' ); $matches = array();
        foreach ( $rows as $row ) { $v = json_decode( $row['response'], true ); if ( ( $v['se_joint_case'] ?? '' ) === $label && ( $v['se_joint_marker'] ?? '' ) === $a['marker'] ) { $matches[] = $row; } }
        jx_assert( count( $matches ) === 1, 'exactly one real browser submission required' ); $row = $matches[0];
        $j['entries'][$label] = array( 'id' => (int) $row['id'], 'contact' => $label === 'C' ? 'C' : 'A', 'response_sha256' => hash( 'sha256', $row['response'] ) );
        $details = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t['details']}` WHERE submission_id=%d ORDER BY id LIMIT 129", $row['id'] ), ARRAY_A );
        jx_assert( is_array( $details ) && count( $details ) > 0 && count( $details ) <= 128 && ! $wpdb->last_error, 'owned serialized details absent/over bound' );
        $j['entries'][$label]['details_sha256'] = hash( 'sha256', wp_json_encode( $details ) );
        $j['entries'][$label]['details_count'] = count( $details );
        jx_audit( $a, $j, $row );
        jx_assert( $j['head']['revision'] === array( 'A' => 1, 'B' => 2, 'C' => 3 )[$label], 'capture sequence must be fresh grant/withdraw/regrant on one chain' );
        return array( 'owned_entry_id' => (int) $row['id'], 'chain_revision' => $j['head']['revision'] );
    }
    if ( $op === 'milestone' ) {
        jx_assert( in_array( $label, array( 'A', 'C' ), true ), 'milestone label' ); $s = jx_contact( $a, $j, $label );
        jx_assert( ! isset( $j['milestones'][$label] ), 'milestone edit already consumed' );
        $j['pending']['original_custom'] = $s->custom_fields(); $j['pending']['original_tags'] = array();
        foreach ( $s->tags as $tag ) { $j['pending']['original_tags'][] = (int) $tag->id; } jx_intent( $j );
        $cfg = SiteEngine_Ads_Bridge_Config::instance();
        $fields = array( 'submission_id' => (string) $j['entries'][$label]['id'], 'qualified_at' => $a['fixture_qualified_at'], 'closed_date' => $a['fixture_closed_date'], 'invoiced_amount' => '125.00' );
        $fields[$cfg->get( 'exports.qualified_leads.date_field', 'qualified_at' )] = $a['fixture_qualified_at'];
        $fields[$cfg->get( 'exports.won_jobs.date_field', 'closed_date' )] = $a['fixture_closed_date'];
        $fields[$cfg->get( 'exports.won_jobs.value_field', 'invoiced_amount' )] = '125.00';
        $s->syncCustomFieldValues( $fields, false );
        $s->detachTags( array_values( array_diff( $a['lifecycle_tag_ids'], array( $a['won_tag_id'] ) ) ) );
        $s->attachTags( array( $a['won_tag_id'], $a['form_lead_tag_id'] ) ); $j['milestones'][$label] = true;
        return array( 'owned_contact_milestone_edits' => 1 );
    }
    if ( $op === 'adopt' ) {
        jx_assert( in_array( $label, array( 'A', 'C' ), true ) && ! isset( $j['inquiries'][$label] ) && count( $j['inquiries'] ) < 4, 'inquiry ceiling' );
        $s = jx_contact( $a, $j, $label ); $entry = jx_owned_entry( $a, $j, $label );
        // Identity generated and journaled by caller BEFORE this adapter may write.
        $id = $j['pending']['inquiry_id'];
        $body = array( 'contract_version' => '1', 'event_id' => $j['pending']['event_id'], 'site_instance_id' => $a['site_instance_id'],
            'contact_id' => (int) $s->id, 'inquiry_id' => $id, 'head_version' => 1,
            'source' => array( 'type' => 'fluent_form', 'reference' => 'form:' . $a['form_id'] . ':entry:' . $entry['id'], 'form_id' => $a['form_id'], 'entry_id' => (int) $entry['id'] ) );
        $j['pending']['exact_body'] = $body; jx_intent( $j );
        $r = seab_inquiry_adopt_current( $body ); jx_assert( $r['status'] === 'adopted', 'adoption did not commit; keep exact pending IDs' );
        $j['inquiries'][$label] = $id; return array( 'owned_inquiry_id' => $id );
    }
    if ( $op === 'rejection' ) {
        $row = jx_owned_entry( $a, $j, 'B' );
        $p = SiteEngine_Ads_Bridge_Consent::current( (int) $row['id'], $a['form_id'], $t['submissions'], $wpdb, (string) SiteEngine_Ads_Bridge_Config::instance()->get( 'consent.required_policy_version', '' ) );
        $b = SiteEngine_Ads_Bridge_Runtime::inject_confirmation_payload( array(), (object) array( 'id' => $a['form_id'] ), array(), (int) $row['id'], json_decode( $row['response'], true ) );
        jx_assert( ! $p['allowed'] && ! isset( $b['siteengine_ads_bridge'] ), 'real B rejection not suppressed' );
        return array( 'canonical_B_rejection' => 'PASS', 'builder_negative_veto' => 'tested with real B negative over C in fault negative, not rebound to A' );
    }
    if ( $op === 'prepare' || $op === 'prepare-effective' ) {
        $key = $op === 'prepare' ? 'prepared' : 'effective'; jx_assert( ! isset( $j[$key][$label] ), 'prepared retry body is immutable' );
        $r = jx_probe( $a, $j, $label ); jx_expect( $r, true ); $j[$key][$label] = array( 'values' => $r['values'], 'context' => $r['context'] ); return array( 'prepared' => 'PASS', 'slot' => $key );
    }
    if ( $op === 'probe' ) {
        $r = jx_probe( $a, $j, $label ); $allowed = $a['_argument'] === 'allow';
        jx_assert( in_array( $a['_argument'], array( 'allow', 'deny', 'dnc' ), true ), 'expected verdict required' );
        if ( $a['_argument'] === 'dnc' ) { jx_assert( $r['browser'] && ! $r['qualified'] && ! $r['converted'] && $r['all_leads'], 'DNC builder/operational distinction' ); }
        else { jx_expect( $r, $allowed ); }
        if ( $label === 'A' && ! $allowed ) { jx_assert( ( $r['authority']['available'] ?? null ) === true && ( $r['authority']['reason'] ?? '' ) === 'withdrawn', 'old A requires actual withdrawn decision' );
            $b = jx_owned_entry( $a, $j, 'B' ); $negative = SiteEngine_Ads_Bridge_Consent::current( (int) $b['id'], $a['form_id'], $t['submissions'], $wpdb, (string) SiteEngine_Ads_Bridge_Config::instance()->get( 'consent.required_policy_version', '' ) );
            jx_assert( $negative['allowed'] === false, 'frozen negative B revived' ); }
        return array( 'assertions' => 4, 'verdict' => $a['_argument'] );
    }
    if ( $op === 'dispatch' || $op === 'dispatch-effective' ) {
        $key = $op === 'dispatch' ? 'prepared' : 'effective';
        jx_assert( isset( $j[$key][$label] ), 'prepared body missing' ); jx_owned_entry( $a, $j, $label );
        $r = jx_append( $j[$key][$label] ); $want = $a['_argument'] === 'allow'; jx_assert( in_array( $a['_argument'], array( 'allow', 'deny' ), true ), 'expected verdict required' );
        jx_assert( $r['allowed'] === $want && $r['synthetic_calls'] === ( $want ? 1 : 0 ), 'actual append boundary verdict/transport mismatch' ); return $r;
    }
    if ( $op === 'fault' || $op === 'missing' || $op === 'policy' ) {
        $row = jx_owned_entry( $a, $j, $label ); $db = $wpdb; $last = $db->last_error; $view = new JX_Read_View( $db );
        if ( $op !== 'missing' ) { jx_expect( jx_probe( $a, $j, $label ), true ); }
        $config = SiteEngine_Ads_Bridge_Config::instance(); $prop = new ReflectionProperty( $config, 'config' ); $prop->setAccessible( true ); $cfg = $prop->getValue( $config );
        $a2 = $a; $j2 = $j;
        jx_scope( $config, function() use ( $op, $a, $j, $label, $row, $view, $prop, $cfg, $config, $a2, $j2 ) {
            global $wpdb;
            if ( $op === 'missing' ) { jx_assert( ! is_callable( array( 'SiteEngine_Consent_Authority', 'current_eligibility' ) ), 'Consent was not request-skipped' ); }
            elseif ( $op === 'policy' ) { $bad = $cfg; $bad['consent']['required_policy_version'] = 'joint-test-mismatch'; $prop->setValue( $config, $bad ); }
            elseif ( $a['_argument'] === 'unavailable' ) { $view->unavailable = true; $wpdb = $view; }
            else {
                $negative = $a['_argument'] === 'negative' ? json_decode( jx_owned_entry( $a, $j, 'B' )['response'], true )['_se_consent_server'] : null;
                $source = json_decode( $row['response'], true ); $view->entry = (int) $row['id'];
                $view->response = wp_json_encode( jx_fault_response( $source, $a['_argument'], $source['_se_consent_server']['receipt_id'], $negative ) );
                $wpdb = $view; $j2['entries'][$label]['response_sha256'] = hash( 'sha256', $view->response );
            }
            $r = jx_probe( $a2, $j2, $label ); jx_expect( $r, false );
            jx_assert( isset( $j['prepared'][$label] ), 'fault dispatch needs genuine prepared body' );
            $sent = jx_append( $j['prepared'][$label] ); jx_assert( ! $sent['allowed'] && $sent['synthetic_calls'] === 0, 'fault reached append transport' );
        } );
        jx_owned_entry( $a, $j, $label ); jx_assert( $prop->getValue( $config ) === $cfg && $wpdb === $db, 'request restoration failed' );
        return array( 'fault' => $op === 'fault' ? $a['_argument'] : $op, 'assertions' => 5, 'stored_evidence_restored' => true, 'persistent_fault_writes' => 0 );
    }
    if ( $op === 'dnc-on' || $op === 'dnc-off' ) {
        $s = jx_contact( $a, $j, $label ); $id = (int) $a['dnc_list_id'];
        if ( $op === 'dnc-on' ) {
            jx_assert( empty( $j['dnc'] ), 'DNC operation already pending' );
            foreach ( $s->lists as $list ) { jx_assert( (int) $list->id !== $id, 'DNC baseline already present; cannot detach it later' ); }
            $j['dnc'] = $label; jx_intent( $j ); $s->attachLists( array( $id ) );
        }
        else { jx_assert( ( $j['dnc'] ?? '' ) === $label, 'DNC restore ownership' ); $s->detachLists( array( $id ) ); unset( $j['dnc'] ); }
        $found = false; foreach ( jx_contact( $a, $j, $label )->lists as $list ) { if ( (int) $list->id === $id ) { $found = true; } }
        jx_assert( $found === ( $op === 'dnc-on' ), 'DNC mutation readback' ); return array( 'owned_DNC_membership' => $found );
    }
    if ( $op === 'rebind' ) {
        jx_assert( $label === 'A' && in_array( $a['_argument'], array( 'B', 'restore' ), true ), 'owned pointer probe only' );
        $s = jx_contact( $a, $j, 'A' ); $fields = $s->custom_fields();
        $target = $a['_argument'] === 'B' ? $j['entries']['B']['id'] : $j['entries']['A']['id'];
        jx_assert( (string) ( $fields['submission_id'] ?? '' ) === (string) ( $a['_argument'] === 'B' ? $j['entries']['A']['id'] : $j['entries']['B']['id'] ), 'pointer baseline/restore conflict' );
        $j['pending']['before_submission_id'] = $fields['submission_id']; $j['pending']['target_submission_id'] = $target; jx_intent( $j );
        $s->syncCustomFieldValues( array( 'submission_id' => (string) $target ), false );
        jx_assert( (string) jx_contact( $a, $j, 'A' )->custom_fields()['submission_id'] === (string) $target, 'pointer readback failed' );
        $j['pointer_rebound'] = $a['_argument'] === 'B'; return array( 'owned_pointer' => $target );
    }
    if ( $op === 'archive' || $op === 'unarchive' ) {
        jx_assert( isset( $j['inquiries'][$label] ), 'owned inquiry required' ); $id = $j['inquiries'][$label];
        $from = $op === 'archive' ? 'active' : 'archived'; $to = $op === 'archive' ? 'archived' : 'active';
        $others = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t['inquiries']}` WHERE contact_id=%d AND inquiry_id<>%s AND state IN ('active','prepared','activating')", $j['contacts'][$label], $id ) );
        jx_assert( (string) $others === '0' && ! $wpdb->last_error, 'competing active/pending inquiry prevents fixture state probe' );
        $j['pending']['before'] = SiteEngine_Ads_Bridge_Inquiries::get( $id ); $j['pending']['target_state'] = $to; jx_intent( $j );
        jx_assert( $wpdb->update( $t['inquiries'], array( 'state' => $to ), array( 'inquiry_id' => $id, 'contact_id' => $j['contacts'][$label], 'state' => $from ) ) === 1, 'owned fixture state patch failed' );
        jx_assert( SiteEngine_Ads_Bridge_Inquiries::get( $id )['state'] === $to, 'state readback failed' ); return array( 'fixture_only_state' => $to );
    }
    if ( $op === 'recover' ) { throw new RuntimeException( 'no automatic replay: inspect pending op/owned marker, compare readbacks, obtain Alma recovery decision; never rotate IDs' ); }
    if ( $op === 'close' ) { return jx_close( $a, $j ); }
    throw new RuntimeException( 'unrecognized operation' );
}
function jx_close( array $a, array &$j ) {
    global $wpdb; $t = $a['tables']; jx_assert( empty( $j['dnc'] ) && empty( $j['pointer_rebound'] ), 'restore owned pointer/DNC membership before closure' );
    $entries = array_column( $j['entries'], 'id' ); $contacts = array_values( $j['contacts'] ); $inquiries = array_values( $j['inquiries'] );
    foreach ( $j['entries'] as $label => $e ) { jx_owned_entry( $a, $j, $label ); }
    if ( isset( $j['entries']['A'] ) ) { jx_audit( $a, $j, jx_owned_entry( $a, $j, 'A' ) ); }
    foreach ( $j['contacts'] as $label => $id ) { jx_contact( $a, $j, $label ); }
    // J0 supplies reviewed logical references; discover actual FK edges too. Unknown references stop deletion.
    $fk = $wpdb->get_results( $wpdb->prepare( 'SELECT TABLE_SCHEMA,TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE REFERENCED_TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IN (%s,%s,%s) LIMIT 65', $t['contacts'], $t['submissions'], $t['inquiries'] ), ARRAY_A );
    jx_assert( is_array( $fk ) && count( $fk ) < 65 && ! $wpdb->last_error && ( $a['reference_map_reviewed'] ?? false ) === true, 'reference coverage incomplete' );
    foreach ( $fk as $edge ) {
        jx_assert( $edge['TABLE_SCHEMA'] === $wpdb->get_var( 'SELECT DATABASE()' ), 'cross-database foreign reference prevents cleanup' );
        $seen = false; foreach ( $a['logical_references'] as $ref ) { if ( $ref['table'] === $edge['TABLE_NAME'] && $ref['column'] === $edge['COLUMN_NAME'] ) { $seen = true; } }
        jx_assert( $seen, 'unreviewed foreign-key edge' );
    }
    $owned_tables = array( $t['details'], $t['meta'], $t['pivot'], $t['inquiries'] );
    foreach ( $a['logical_references'] as $ref ) {
        $ids = $ref['kind'] === 'entry' ? $entries : ( $ref['kind'] === 'contact' ? $contacts : $inquiries );
        foreach ( $ids as $id ) {
            $n = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$ref['table']}` WHERE `{$ref['column']}`=%s", $id ) );
            jx_assert( $n !== null && ! $wpdb->last_error && (int) $n <= 512, 'reference read/ceiling' );
            jx_assert( in_array( $ref['table'], $owned_tables, true ) || (int) $n === 0, 'foreign references require retention, no delete' );
            if ( $ref['table'] === $t['inquiries'] && (int) $n > 0 ) {
                $rows = $wpdb->get_results( $wpdb->prepare( "SELECT inquiry_id FROM `{$ref['table']}` WHERE `{$ref['column']}`=%s LIMIT 5", $id ), ARRAY_A );
                jx_assert( is_array( $rows ) && count( $rows ) < 5, 'inquiry reference ceiling' );
                foreach ( $rows as $r ) { jx_assert( in_array( $r['inquiry_id'], $inquiries, true ), 'foreign inquiry references owned fixture; retain it' ); }
            }
        }
    }
    foreach ( $j['inquiries'] as $label => $id ) {
        $r = SiteEngine_Ads_Bridge_Inquiries::get( $id );
        jx_assert( is_array( $r ) && (int) $r['contact_id'] === $j['contacts'][$label] && (int) $r['submission_id'] === $j['entries'][$label]['id'], 'cleanup inquiry provenance changed' );
    }
    seab_joint_observe( array( 'site' => $a['site'], 'prefix' => $a['prefix'], 'form_id' => $a['form_id'], 'cases' => array() ) );
    $cfg = SiteEngine_Ads_Bridge_Config::instance(); $cp = new ReflectionProperty( $cfg, 'config' ); $cp->setAccessible( true );
    jx_assert( hash( 'sha256', serialize( $cp->getValue( $cfg ) ) ) === $j['baseline']['config_sha256'], 'configuration drift; retain resources' );
    $form = jx_db_row( $t['forms'], $a['form_id'] );
    $j['pending']['cleanup_ids'] = array( 'entries' => $entries, 'contacts' => $contacts, 'inquiries' => $inquiries ); jx_intent( $j );
    jx_assert( $form['form_fields'] === ( $j['patched_fields'] ?? $j['baseline']['form_fields'] ), 'form restore conflict' );
    if ( isset( $j['patched_fields'] ) ) { jx_assert( $wpdb->update( $t['forms'], array( 'form_fields' => $j['baseline']['form_fields'] ), array( 'id' => $a['form_id'], 'form_fields' => $j['patched_fields'] ) ) === 1, 'form restore failed' ); }
    jx_assert( jx_db_row( $t['forms'], $a['form_id'] )['form_fields'] === $j['baseline']['form_fields'], 'form restoration readback failed' );
    jx_assert( hash( 'sha256', serialize( get_option( 'active_plugins' ) ) ) === $j['baseline']['active_plugins_sha256'], 'activation drift: do not overwrite active_plugins' );
    foreach ( $inquiries as $id ) { jx_assert( $wpdb->delete( $t['inquiries'], array( 'inquiry_id' => $id ) ) === 1, 'owned inquiry cleanup failed' ); }
    foreach ( $entries as $id ) {
        jx_assert( $wpdb->delete( $t['details'], array( 'submission_id' => $id ) ) !== false, 'detail cleanup failed' );
        jx_assert( $wpdb->delete( $t['submissions'], array( 'id' => $id, 'form_id' => $a['form_id'] ) ) === 1, 'owned entry cleanup failed' );
    }
    foreach ( $contacts as $id ) {
        jx_assert( $wpdb->delete( $t['pivot'], array( 'subscriber_id' => $id ) ) !== false && $wpdb->delete( $t['meta'], array( 'subscriber_id' => $id ) ) !== false, 'owned related-row cleanup failed' );
        jx_assert( $wpdb->delete( $t['contacts'], array( 'id' => $id ) ) === 1, 'owned contact cleanup failed' );
    }
    foreach ( $entries as $id ) { jx_assert( jx_db_row( $t['submissions'], $id ) === null, 'entry remains' ); }
    foreach ( $contacts as $id ) { jx_assert( jx_db_row( $t['contacts'], $id ) === null, 'contact remains' ); }
    foreach ( $entries as $id ) { jx_assert( (string) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t['details']}` WHERE submission_id=%d", $id ) ) === '0', 'details remain' ); }
    foreach ( $contacts as $id ) {
        foreach ( array( 'meta', 'pivot' ) as $key ) { jx_assert( (string) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM `{$t[$key]}` WHERE subscriber_id=%d", $id ) ) === '0', 'contact references remain' ); }
    }
    foreach ( $inquiries as $id ) { jx_assert( SiteEngine_Ads_Bridge_Inquiries::get( $id ) === null, 'inquiry remains' ); }
    $j['closed'] = true;
    return array( 'fixture_cleanup' => 'PASS', 'audit_retained' => array( 'chain_id' => $j['chain_id'] ?? null, 'receipt_ids' => array_keys( $j['audit'] ) ), 'cleanup_owner' => 'Alma Codex', 'retention' => 'Consent review; never delete heads/barriers/events' );
}
function jx_stage( $stage, array $a, array &$j ) {
    $has = static function( $op, $label, $arg = null, $minimum = 1 ) use ( &$j ) {
        $n = 0; foreach ( $j['results'] as $r ) { if ( $r['operation'] === $op && $r['label'] === $label && ( $arg === null || ( $r['argument'] ?? null ) === $arg ) && $r['status'] === 'PASS' ) { $n++; } }
        jx_assert( $n >= $minimum, 'stage missing executable evidence ' . $op . '/' . $label );
    };
    switch ( $stage ) {
        case 'J1': jx_assert( isset( $j['browser']['A'] ), 'browser A evidence' ); $has( 'adopt', 'A' ); $has( 'prepare', 'A' ); $has( 'dispatch', 'A', 'allow' ); break;
        case 'J2': $has( 'rejection', 'B' ); foreach ( array( 'pending', 'failed', 'null', 'malformed', 'unknown', 'unsigned' ) as $mode ) { $has( 'fault', 'A', $mode ); } $has( 'fault', 'C', 'negative' ); break;
        case 'J3': jx_assert( isset( $j['browser']['B'] ), 'browser B evidence' ); $has( 'dispatch', 'A', 'deny', 2 ); $has( 'probe', 'A', 'deny' ); break;
        case 'J4': jx_assert( isset( $j['browser']['C'] ), 'browser C evidence' ); $has( 'adopt', 'C' ); $has( 'dispatch', 'C', 'allow' ); $has( 'probe', 'A', 'deny' ); break;
        case 'J5': $has( 'missing', 'C' ); $has( 'fault', 'C', 'unavailable' ); break;
        case 'J6':
            $has( 'policy', 'C' ); $has( 'dnc-on', 'C' ); $has( 'probe', 'C', 'dnc' ); $has( 'dnc-off', 'C' );
            $has( 'rebind', 'A', 'B' ); $has( 'rebind', 'A', 'restore' ); $has( 'archive', 'A' ); $has( 'unarchive', 'A' ); $has( 'dispatch', 'C', 'deny' ); $has( 'dispatch-effective', 'C', 'allow' );
            jx_assert( $j['prepared']['C']['values'][17] === 'Granted' && $j['effective']['C']['values'][17] === 'Denied', 'effective personalization boundary evidence missing' ); break;
        case 'J7':
            jx_assert( ! empty( $j['closed'] ), 'owned cleanup not complete' );
            foreach ( array( 'J1', 'J2', 'J3', 'J4', 'J5', 'J6' ) as $prior ) { jx_assert( $j['stages'][$prior] === 'PASS', 'earlier stage incomplete' ); }
            $file = $a['run_dir'] . '/closure-owner.json'; $v = jx_json( $file );
            jx_assert( hash_file( 'sha256', $file ) === trim( file_get_contents( $file . '.sha256' ) )
                && $v['assignment'] === $a['assignment'] && $v['external_deliveries'] === 0 && $v['pending_owned_jobs'] === 0
                && $v['scoped_controls_restored'] === true && $v['cleanup_owner'] === 'Alma Codex' && $v['audit_retention_owner'] === 'Consent', 'owner closure/evidence required' ); break;
        default: throw new JX_Stop( 'unknown stage' );
    }
    $j['stages'][$stage] = 'PASS'; return array( 'stage' => $stage, 'status' => 'PASS', 'all_stages' => $j['stages'] );
}
if ( ! defined( 'JX_LIBRARY_ONLY' ) ) {
    $path = null; $j = array(); $lock = null;
    try {
        jx_assert( defined( 'WP_CLI' ) && WP_CLI && isset( $args ) && count( $args ) === 5, 'WP eval-file: op label argument allocation.json SHA256' );
        list( $op, $label, $argument, $file, $sha ) = $args;
        jx_assert( preg_match( '/^[a-f0-9]{64}$/D', $sha ) && hash_file( 'sha256', $file ) === $sha, 'allocation hash mismatch' );
        $a = jx_allocation( jx_json( $file ) ); $a['_sha256'] = $sha; $a['_argument'] = $argument;
        jx_assert( ! is_link( $file ) && realpath( $file ) === $a['run_dir'] . '/allocation.json' && realpath( dirname( $file ) ) === $a['run_dir'] && posix_geteuid() === $a['owner_uid'] && ( fileperms( dirname( $file ) ) & 0777 ) === 0700, 'owner/private path mismatch' );
        jx_assert( ( fileperms( $file ) & 0777 ) === 0600, 'allocation must be0600' );
        global $wpdb; jx_assert( home_url( '/' ) === $a['site'] && $wpdb->prefix === $a['prefix'], 'target mismatch' );
        $lock = fopen( $a['run_dir'] . '/operations.lock', 'c' ); chmod( $a['run_dir'] . '/operations.lock', 0600 ); jx_assert( flock( $lock, LOCK_EX | LOCK_NB ), 'another owned operation running' );
        $path = $a['run_dir'] . '/journal.json'; $GLOBALS['jx_path'] = $path; $j = is_file( $path ) ? jx_json( $path ) : array();
        if ( $op !== 'admit' ) {
            $review = jx_json( $a['run_dir'] . '/fixture-release.json' );
            $review_pin = trim( file_get_contents( $a['run_dir'] . '/fixture-release.sha256' ) );
            jx_assert( $review['status'] === 'RELEASED_J1_J6_J7' && $review['allocation_sha256'] === $sha
                && preg_match( '/^[a-f0-9]{64}$/D', $review_pin )
                && hash_file( 'sha256', $a['run_dir'] . '/fixture-release.json' ) === $review_pin
                && $review['operations_manifest_sha256'] === hash_file( 'sha256', __DIR__ . '/OPERATIONS-MANIFEST.json' ), 'fixture execution not released for this allocation/package' );
            jx_assert( ! empty( $j ) && $j['allocation_sha256'] === $sha && ( empty( $j['closed'] ) || ( $op === 'stage' && $label === 'J7' ) ), 'journal identity/closure' );
            jx_assert( count( $j['results'] ) < 64, 'finite operation ceiling' );
            jx_assert( empty( $j['pending'] ) && empty( $j['stopped'] ), 'pending/failure requires explicit reviewed recovery; no replay' );
            $j['pending'] = array( 'operation' => $op, 'label' => $label, 'argument' => $argument, 'at' => gmdate( 'c' ) );
            if ( $op === 'adopt' ) { $j['pending']['inquiry_id'] = 'inq_' . wp_generate_uuid4(); $j['pending']['event_id'] = 'evt_' . wp_generate_uuid4(); }
            jx_save( $path, $j );
        }
        // CLI request scope only. Ordinary web requests and stored activation state are untouched.
        $no_http = static function() { return new WP_Error( 'joint_cli_no_delivery', 'joint test request blocks HTTP' ); };
        $no_mail = static function() { return true; };
        add_filter( 'pre_http_request', $no_http, PHP_INT_MAX, 3 ); add_filter( 'pre_wp_mail', $no_mail, PHP_INT_MAX, 2 );
        try { $r = jx_execute( $op, $label, $a, $j ); }
        finally { remove_filter( 'pre_http_request', $no_http, PHP_INT_MAX ); remove_filter( 'pre_wp_mail', $no_mail, PHP_INT_MAX ); }
        $j['pending'] = null; $j['results'][] = array( 'operation' => $op, 'label' => $label, 'argument' => $argument, 'status' => 'PASS', 'at' => gmdate( 'c' ), 'result' => $r ); jx_save( $path, $j );
        echo json_encode( array( 'operation' => $op, 'label' => $label, 'status' => 'PASS', 'result' => $r ) ) . "\n";
    } catch ( Throwable $e ) {
        $reason = $e instanceof JX_Stop ? $e->getMessage() : 'unexpected exception; stop and retain private pending evidence';
        if ( $path && $j ) { $j['stopped'] = true; $j['stages'][$j['active_stage'] ?? 'J0'] = 'FAIL'; $j['failure'] = array( 'status' => 'FAIL', 'reason' => $reason, 'at' => gmdate( 'c' ) ); try { jx_save( $path, $j ); } catch ( Throwable $secondary ) { /* retain earlier durable pending journal */ } }
        fwrite( STDERR, json_encode( array( 'status' => 'FAIL', 'reason' => $reason, 'remaining' => 'journal/controls retained; Alma Codex recovery owner' ) ) . "\n" ); exit( 1 );
    } finally { if ( is_resource( $lock ) ) { flock( $lock, LOCK_UN ); fclose( $lock ); } }
}
