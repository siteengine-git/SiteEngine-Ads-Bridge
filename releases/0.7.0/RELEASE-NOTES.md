# Ads Bridge 0.7.0 — destination configuration import

Adds an ordinary administrator workflow: upload the CRM producer ZIP, choose destination mappings, review, import automatically in bounded batches, then edit native FluentCRM objects. Destination sites no longer require the separate CRM Configuration plugin. Existing Consent, inquiry, lifecycle, timezone and coordination implementations are byte-identical to released 0.6.4.5.

Actual fresh browser/native verification imported 117 producer definitions plus eight standard Ads fields, checked 66 relationships, repeated with zero native saves and demonstrated deliberate Keep/Replace. All optional source mappings can be Unused; incomplete lifecycle setup can save while automation stays disabled. Import never enables sending, enrollment, Sheets or exports.

Requirements: PHP 8.3 with mysqli/ZIP, Base, Fluent Forms and supported FluentCRM 3.2.6/3.3.1. This producer's Pro sequences require FluentCRM Pro 3.3.1. Complete real Base, Consent/form, account and credentials prerequisites separately. See the administrator guide for exact setup/recovery boundaries.

The exact package is installed on Alma and its 49 members verified; 27 PHP lints and 182 affected local assertions pass. Two owned isolated destinations were removed after evidence retention. Normal Alma still reports its real credentials-context failure. No client rollout or real delivery is authorized.

Release is currently held for CRM's 0.3.16 installed producer/export compatibility check. The prior stable deliverable is preserved until that check completes.
