# Install and recovery — 0.7.1

Back up this plugin's files and preserve site settings, original executor directory,
deployment/admin records, import journals, configuration guard and Consent history.
Serialize replacement using the existing executor and Ads coordination admission.
A retained/unknown operation must complete its original supported recovery first.
Do not recreate missing locks, clear journals or use storage initialization as recovery.

Install the exact ZIP listed in DELIVERY.json, replacing only Ads plugin files.
PHP 8.3 and existing dependencies remain required; no new schema migration or activation
operation is needed for this patch. Verify all 50 members against MANIFEST.json,
lint the 28 PHP files and use a fresh request to inspect validation and disabled exports.
Open Ads Bridge > Google Sheets, save through the normal administrator form, then
fresh-read the saved path and its diagnostics. Missing/unreadable credentials remain
separate from ownership failures; this patch does not provision or test credentials.

If replacement fails, restore only the targeted file backup under verified ownership.
Preserve settings/history and uncertain operation evidence. Do not restore the database
or remove withdrawal barriers. CRM's exact 0.7.1 bootstrap binding remains owner-reviewed
work; do not infer producer coexistence from the previous 0.7.0 result.
