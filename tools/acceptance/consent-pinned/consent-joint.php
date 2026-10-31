<?php
/** Test operations only. No bootstrap, database writer, transport or cleanup. Alma executor only. */
function scj_require($condition, string $reason): void { if (!$condition) throw new RuntimeException($reason); }
function scj_allocation(array $a, array $j): void {
    scj_require(($a['assignment'] ?? '') === 'ALMA-JOINT-CONSENT-ADS-20261031'
        && ($a['site'] ?? '') === 'https://alma.siteengine.space/'
        && ($a['run_dir'] ?? '') === '/home/alma.siteengine.space/.se-joint-acceptance/joint-consent-ads-20261031-01'
        && is_int($a['form_id'] ?? null) && $a['form_id'] > 0
        && preg_match('/^[A-Za-z0-9_]{1,48}$/D', $a['prefix'] ?? '')
        && preg_match('/^jct_[a-f0-9]{32}$/D', $a['marker'] ?? '')
        && preg_match('/^[a-f0-9]{64}$/D', $a['_sha256'] ?? ''), 'allocation identity invalid');
    foreach (['submissions' => 'fluentform_submissions', 'receipts' => 'se_consent_receipts', 'events' => 'se_consent_events', 'chains' => 'se_consent_chains'] as $k => $suffix) {
        scj_require(($a['tables'][$k] ?? '') === $a['prefix'] . $suffix, 'exact table map required: ' . $k);
    }
    scj_require(($j['assignment'] ?? '') === $a['assignment'] && ($j['marker'] ?? '') === $a['marker']
        && ($j['allocation_sha256'] ?? '') === $a['_sha256'] && empty($j['closed']) && empty($j['stopped'])
        && is_array($j['entries'] ?? null) && count($j['entries']) <= 10
        && count($j['contacts'] ?? []) <= 2 && count($j['audit'] ?? []) <= 10, 'journal ownership/limits/stop state invalid');
    $seen = [];
    foreach ($j['entries'] as $label => $entry) {
        scj_require(in_array($label, ['A','B','C'], true) && is_int($entry['id'] ?? null) && $entry['id'] > 0
            && !isset($seen[$entry['id']]) && preg_match('/^[a-f0-9]{64}$/D', $entry['response_sha256'] ?? ''), 'owned entry identity invalid');
        $seen[$entry['id']] = true;
    }
    scj_require(preg_match('/^[a-f0-9]{64}$/D', $j['chain_id'] ?? ''), 'admitted single browser chain required');
}
function scj_owned(array $a, array $j, string $label): array {
    global $wpdb;
    scj_allocation($a, $j);
    scj_require(home_url('/') === $a['site'] && $wpdb->prefix === $a['prefix'], 'target mismatch');
    scj_require(in_array($label, ['A','B','C'], true) && isset($j['entries'][$label]), 'entry not admitted by exact ID');
    $entry = $j['entries'][$label]; $table = $a['tables']['submissions'];
    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$table}` WHERE id = %d", $entry['id']), ARRAY_A);
    $response = is_array($row) ? json_decode($row['response'] ?? '', true) : null;
    scj_require(!$wpdb->last_error && is_array($response) && (int) $row['id'] === $entry['id']
        && (int) $row['form_id'] === $a['form_id'] && ($response['se_joint_marker'] ?? null) === $a['marker']
        && ($response['se_joint_case'] ?? null) === $label
        && hash_equals($entry['response_sha256'], hash('sha256', $row['response'])), 'foreign/changed entry refused');
    $stamp = $response['_se_consent_server'] ?? null;
    scj_require(is_array($stamp) && SiteEngine_Consent_Authority::verify_stamp($stamp, $a['form_id'])
        && ($stamp['state'] ?? '') === 'verified' && ($stamp['chain_id'] ?? '') === $j['chain_id']
        && isset($j['audit'][(string) ($stamp['receipt_row_id'] ?? 0)]), 'original acknowledged owned evidence required');
    return $row;
}
function scj_snapshot(array $a, array $j, string $label): array {
    global $wpdb;
    $row = scj_owned($a, $j, $label); $hashes = ['response' => hash('sha256', $row['response'])];
    foreach ($j['audit'] as $id => $admitted) {
        scj_require(ctype_digit((string) $id) && (int) $id > 0, 'audit row ID invalid');
        $receipt = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$a['tables']['receipts']}` WHERE id = %d", (int) $id), ARRAY_A);
        $link = SiteEngine_Consent_Continuity::link((int) $id);
        scj_require(!$wpdb->last_error && is_array($receipt) && is_array($link)
            && $link['chain_id'] === $j['chain_id'] && $receipt['receipt_id'] === ($admitted['receipt_id'] ?? null)
            && hash('sha256', wp_json_encode($receipt)) === ($admitted['sha256'] ?? '')
            && hash('sha256', wp_json_encode($link)) === ($admitted['link_sha256'] ?? ''), 'foreign/changed audit row refused');
        $hashes['receipt:' . $id] = hash('sha256', wp_json_encode($receipt));
        $hashes['link:' . $id] = hash('sha256', wp_json_encode($link));
    }
    $head = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$a['tables']['chains']}` WHERE chain_id = %s", $j['chain_id']), ARRAY_A);
    scj_require(!$wpdb->last_error && is_array($head) && (int) $head['revision'] <= 10
        && (int) $head['revision'] === ($j['head']['revision'] ?? null)
        && hash('sha256', wp_json_encode($head)) === ($j['head']['sha256'] ?? ''), 'owned head unavailable/changed/over ceiling');
    $hashes['head'] = hash('sha256', wp_json_encode($head));
    return $hashes;
}
function scj_inspect(array $a, array $j, string $label): array {
    scj_snapshot($a, $j, $label);
    $row = scj_owned($a, $j, $label); $stamp = json_decode($row['response'], true)['_se_consent_server'];
    $r = SiteEngine_Consent_Authority::current_eligibility($stamp, $a['form_id'], SE_CONSENT_POLICY_VERSION);
    return ['entry_id' => $j['entries'][$label]['id'], 'original_stamp_sha256' => hash('sha256', wp_json_encode($stamp)),
        'current' => $r, 'persistent_mutations' => 0];
}
/** Only projection reads are replaced; ordinary reads use the installed implementation. */
final class SCJ_Read_View {
    public $prefix, $last_error = '', $hits = 0;
    private $db, $a, $j, $label, $mode, $response;
    public function __construct($db, array $a, array $j, string $label, string $mode, ?string $response) {
        $this->db = $db; $this->prefix = $db->prefix; $this->a = $a; $this->j = $j;
        $this->label = $label; $this->mode = $mode; $this->response = $response;
    }
    public function prepare(...$args) { return $this->db->prepare(...$args); }
    public function esc_like($s) { return $this->db->esc_like($s); }
    private function read(string $method, string $sql, array $args) {
        scj_require((bool) preg_match('/^\s*(SELECT|SHOW)\b/i', $sql) && strpos($sql, ';') === false, 'fault view refuses non-read/multiple SQL');
        $head_sql = $this->db->prepare("SELECT * FROM {$this->a['tables']['chains']} WHERE chain_id = %s LOCK IN SHARE MODE", $this->j['chain_id']);
        if ($method === 'get_row' && $sql === $head_sql && in_array($this->mode, ['unavailable-head','failed-current-read'], true)) {
            $this->hits++;
            if ($this->mode === 'failed-current-read') throw new RuntimeException('owned test: current head read failed');
            return null;
        }
        $value = $this->db->$method($sql, ...$args); $this->last_error = $this->db->last_error;
        if ($method === 'get_row' && $this->response !== null && is_array($value)
            && (int) ($value['id'] ?? 0) === $this->j['entries'][$this->label]['id']
            && preg_match('/\bFROM\s+`?' . preg_quote($this->a['tables']['submissions'], '/') . '`?\s+WHERE\s+id\s*=\s*' . $value['id'] . '(?:\s|$)/i', $sql)) {
            scj_require((int) $value['form_id'] === $this->a['form_id']
                && hash('sha256', $value['response']) === $this->j['entries'][$this->label]['response_sha256'], 'projection ownership drift');
            $value['response'] = $this->response; $this->hits++;
        }
        return $value;
    }
    public function get_row($sql, ...$args) { return $this->read('get_row', $sql, $args); }
    public function get_results($sql, ...$args) { return $this->read('get_results', $sql, $args); }
    public function get_col($sql, ...$args) { return $this->read('get_col', $sql, $args); }
    public function get_var($sql, ...$args) { return $this->read('get_var', $sql, $args); }
    public function __call($name, $args) { throw new RuntimeException('fault view refuses unsupported/write method: ' . $name); }
}
/** Callback receives projected JOURNAL copy, global WPDB view; no persisted evidence is rewritten. */
function scj_with_fault(array $a, array $j, string $label, string $mode, callable $callback): array {
    global $wpdb;
    scj_require(in_array($mode, ['conflicting-flat','null','malformed','unknown','unsigned','tampered','pending','failed','unavailable-head','failed-current-read'], true), 'unsupported fault');
    $original = $wpdb; $last = $original->last_error;
    $before = scj_snapshot($a, $j, $label); $baseline = scj_inspect($a, $j, $label);
    if ($mode !== 'conflicting-flat') scj_require($baseline['current']['available'] === true && $baseline['current']['allowed'] === true,
        'fault requires currently eligible owned baseline, not an already blocked row');
    $row = scj_owned($a, $j, $label); $response = json_decode($row['response'], true); $projected = $j; $encoded = null;
    if (!in_array($mode, ['unavailable-head','failed-current-read'], true)) {
        switch ($mode) {
            case 'null': $response['_se_consent_server'] = null; break;
            case 'malformed': $response['_se_consent_server'] = 'malformed-owned-fixture'; break;
            case 'unknown': $response['_se_consent_server']['contract_version'] = 999; break;
            case 'unsigned': unset($response['_se_consent_server']); break;
            case 'tampered': $response['_se_consent_server']['signature'] = str_repeat('0', 64); break;
            case 'pending': case 'failed':
                $response['_se_consent_server'] = SiteEngine_Consent_Authority::seal_stamp((new SiteEngine_Consent_Authority([]))->negative($mode), $a['form_id']); break;
            case 'conflicting-flat': scj_require($label === 'B' && !$baseline['current']['allowed'], 'conflict requires genuine rejected B'); break;
        }
        $grant = json_decode(scj_owned($a, $j, 'A')['response'], true)['_se_consent_server'];
        $response['se_consent_receipt_id'] = $grant['receipt_id']; $response['se_consent_advertising'] = 'granted'; $response['se_google_ad_user_data'] = 'granted';
        $encoded = wp_json_encode($response); $projected['entries'][$label]['response_sha256'] = hash('sha256', $encoded);
    }
    $view = new SCJ_Read_View($original, $a, $j, $label, $mode, $encoded); $failure = null; $result = null;
    try { $wpdb = $view; $result = $callback($projected); scj_require($view->hits > 0, 'fault not exercised'); }
    catch (Throwable $e) { $failure = $e; }
    finally {
        $wpdb = $original; $original->last_error = $last;
        try {
            scj_require($wpdb === $original && scj_snapshot($a, $j, $label) === $before, 'original state differs');
            $after = scj_inspect($a, $j, $label);
            unset($after['current']['checked_at'], $baseline['current']['checked_at']);
            scj_require($after === $baseline, 'restored real authority result differs');
        } catch (Throwable $restore) { throw new RuntimeException('STOP: restoration/readback failed; retain controls: ' . $restore->getMessage(), 0, $restore); }
    }
    if ($failure) throw new RuntimeException('CALLBACK_FAILED; RESTORATION_VERIFIED: ' . $failure->getMessage(), 0, $failure);
    return ['fault' => $mode, 'projection_hits' => $view->hits, 'restoration' => 'VERIFIED', 'persistent_mutations' => 0,
        'evidence_kind' => 'request-local synthetic read projection; original rows unchanged', 'result' => $result];
}
function scj_context(array $a): void {
    global $wpdb;
    scj_require(defined('WP_CLI') && WP_CLI && defined('SE_CONSENT_VERSION') && SE_CONSENT_VERSION === '0.6.1-rc.2'
        && rtrim(ABSPATH, '/') === '/home/alma.siteengine.space/public_html'
        && home_url('/') === $a['site'] && $wpdb->prefix === $a['prefix'], 'installed target/runtime mismatch');
    foreach (['SELECT @@read_only' => '0', 'SELECT @@autocommit' => '1'] as $sql => $expected) scj_require((string) $wpdb->get_var($sql) === $expected && !$wpdb->last_error, 'primary/autocommit unavailable');
    foreach (['SiteEngine_Consent_Authority' => '6d49bfe9006fc108d198dea7309b6dae88e123466d2e0767a2aa74391e4488c9',
        'SiteEngine_Consent_Continuity' => '660c83315e50ae6f159ddcb3f97f28b1f960c726bb5c9be62a0cdccd2507fe3d'] as $class => $hash) {
        scj_require(class_exists($class) && hash_file('sha256', (new ReflectionClass($class))->getFileName()) === $hash, 'runtime pin mismatch');
    }
}
if (!defined('SCJ_LIBRARY_ONLY')) {
    $lock = null;
    try {
        scj_require(defined('WP_CLI') && WP_CLI && isset($args) && count($args) === 7, 'eval-file: inspect|fault LABEL MODE allocation.json ALLOCATION_SHA JOURNAL_SHA RELEASE_SHA');
        [$op,$label,$mode,$path,$sha,$journal_sha,$release_sha] = $args;
        foreach ([$sha,$journal_sha,$release_sha] as $pin) scj_require((bool) preg_match('/^[a-f0-9]{64}$/D', $pin), 'independent file pin required');
        scj_require(is_file($path) && filesize($path) <= 1048576 && hash_file('sha256', $path) === $sha, 'allocation pin mismatch');
        $a = json_decode(file_get_contents($path), true); scj_require(is_array($a), 'allocation JSON invalid'); $a['_sha256'] = $sha;
        $dir = $a['run_dir'] ?? ''; scj_require($dir === '/home/alma.siteengine.space/.se-joint-acceptance/joint-consent-ads-20261031-01' && realpath(dirname($path)) === $dir, 'private run path mismatch');
        foreach ([$path, $dir . '/journal.json', $dir . '/fixture-release.json'] as $file) {
            scj_require(is_file($file) && !is_link($file) && filesize($file) <= 1048576 && (fileperms($file) & 0777) === 0600
                && fileowner($file) === ($a['owner_uid'] ?? null) && posix_geteuid() === $a['owner_uid'], 'owner/private file mismatch');
        }
        scj_require((fileperms($dir) & 0777) === 0700 && hash_file('sha256', $dir . '/fixture-release.json') === $release_sha, 'private directory/release pin mismatch');
        $release = json_decode(file_get_contents($dir . '/fixture-release.json'), true);
        scj_require(($release['status'] ?? '') === 'RELEASED_J1_J6_J7' && ($release['allocation_sha256'] ?? '') === $sha, 'J1-J6 fixture release absent');
        scj_require(is_file($dir . '/operations.lock') && !is_link($dir . '/operations.lock')
            && (fileperms($dir . '/operations.lock') & 0777) === 0600 && fileowner($dir . '/operations.lock') === $a['owner_uid'], 'Ads-owned lock must already exist privately');
        $lock = fopen($dir . '/operations.lock', 'r+'); scj_require(is_resource($lock) && flock($lock, LOCK_EX | LOCK_NB), 'joint operation already active');
        scj_require(hash_file('sha256', $dir . '/journal.json') === $journal_sha, 'journal pin mismatch');
        $j = json_decode(file_get_contents($dir . '/journal.json'), true); scj_require(is_array($j) && empty($j['pending']), 'pending operation; no retry');
        scj_allocation($a,$j); scj_context($a);
        if ($op === 'inspect') {
            scj_require(in_array($mode, ['eligible','withdrawn','denied'], true), 'inspect requires explicit expected reason');
            $result = scj_inspect($a,$j,$label);
            scj_require($result['current']['available'] === true && $result['current']['allowed'] === ($mode === 'eligible')
                && $result['current']['reason'] === $mode, 'current eligibility assertion failed');
        }
        else {
            scj_require($op === 'fault', 'unknown operation');
            $result = scj_with_fault($a,$j,$label,$mode, function ($view_j) use ($a,$j,$label) {
                global $wpdb;
                $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM `{$a['tables']['submissions']}` WHERE id = %d", $j['entries'][$label]['id']), ARRAY_A);
                $stamp = json_decode($row['response'], true)['_se_consent_server'] ?? null;
                $r = SiteEngine_Consent_Authority::current_eligibility(is_array($stamp) ? $stamp : [], $a['form_id'], SE_CONSENT_POLICY_VERSION);
                scj_require($r['allowed'] === false, 'fault unexpectedly allowed'); return $r;
            });
        }
        echo wp_json_encode(['status' => 'PASS', 'operation' => $op, 'label' => $label, 'result' => $result]) . "\n";
    } catch (Throwable $e) { fwrite(STDERR, 'STOP: ' . $e->getMessage() . "\n"); exit(1); }
    finally { if (is_resource($lock)) { flock($lock, LOCK_UN); fclose($lock); } }
}
