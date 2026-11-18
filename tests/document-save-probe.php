<?php
/** AB's additive opt-in probe, without loading Base storage or provider code. */
define('ABSPATH', __DIR__ . '/');
function __($value, $domain = '') { return $value; }
$mode = $argv[1] ?? 'absent';
if ($mode !== 'absent') {
    function siteengine_credentials_document_save_contract_version() {
        if ($GLOBALS['mode'] === 'throw') throw new RuntimeException('Unavailable fixture');
        return $GLOBALS['mode'] === 'valid' ? 'document-save-1' : 'document-save-2';
    }
}
function siteengine_register_credentials_v1($module, $group, $definition) {
    $GLOBALS['registration'] = [$module, $group, $definition];
    return true;
}
require dirname(__DIR__) . '/includes/class-siteengine-ads-bridge-credentials.php';
SiteEngine_Ads_Bridge_Credentials::register();
[$module, $group, $definition] = $GLOBALS['registration'];
if ($module !== 'ads-bridge' || $group !== 'google-sheets' ||
    ($definition['admin_mode'] ?? 'staged') !== ($mode === 'valid' ? 'simple' : 'staged') ||
    isset($definition['confirm_changes']) ||
    ($mode === 'valid' && $definition['document_label'] !== 'Google service-account JSON')) {
    throw new RuntimeException('Incorrect document capability admission');
}
$guidance = SiteEngine_Ads_Bridge_Credentials::setup_guidance();
if ($mode === 'valid' && (strpos($guidance, 'click Save credentials') === false ||
    strpos($guidance, 'Stage') !== false || strpos($guidance, 'Activate') !== false)) {
    throw new RuntimeException('Outdated ordinary guidance');
}
echo "PASS exact document probe $mode; no storage or provider activity\n";
