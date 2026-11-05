# SiteEngine Ads Bridge 0.7.0

Stable release of the destination CRM configuration importer. Upload the producer's
portable ZIP in Ads Bridge, choose destination form/field/service mappings, review,
then import automatically in bounded batches. Native FluentCRM content stays editable
and inactive. Destination sites do not require the separate CRM Configuration plugin.
Compatible definitions are reused; destination edits require Keep or Replace.
Incomplete disabled setup and Unused optional source mappings can save.

Storage reporting separates schema faults, live ownership, retained operations and
unknown evidence. Saved Ads imports resume with their original pins/IDs/receipts;
predecessor CRM journals remain with their owner. No journal reset or unknown-create
replay. Consent, inquiry, lifecycle, timezone and coordination behavior is preserved.

Completed evidence: actual isolated browser/native import,66 relationships,zero-save
repeats,Keep/Replace,interrupted-operation recovery and setup readback. The original
main-Alma import completed126/126 with its identities/receipts/checkpoints preserved.
The corrected package is main-installed with50 exact members and28 PHP8.3.36 lints;
configuration,native content,inquiry/Consent and journals preserved. Doctor35PASS,
7WARN,0FAIL;all four export families disabled. Local affected coverage203 assertions:
55 fresh recovery/setup assertions plus148 unchanged checks carried forward.

CRM0.3.16 coexistence PASS at evidence7da32a653cc0453ace4f623aadeb1ed21e632918:
normal authenticated administrator HTTP export/current and previous downloads plus
native readback of93 objects,24 dependencies,three sequences,six emails,66 relations.
This was not a new visual browser test. Full volatile fc_meta byte identity is not
claimed;managed definitions were compared directly and no timestamp was reset.
Producer completed/released ownership. Prior accepted evidence/failures are retained.

Requirements:PHP8.3+,mysqli/ZIP,Base,Fluent Forms,and supported FluentCRM3.2.6/3.3.1.
The producer package's Pro sequences require Pro3.3.1. Complete site-specific Base,
Consent/form/feed,account and credentials prerequisites separately. Import completion
is not permission to send,enroll,append Sheets or deliver advertising. No client
rollout or real delivery is part of this release. UI simplification is a later patch.

ZIP SHA256:ac4636f8348c7bc5897f2883d0f19d7fe04c4670350751fed9a35ad0cd03aeaa
Manifest SHA256:a8fd3a2eb76e374804eaa4ab0e149c5146e0f66cd38c5473616a4b3cccaa125f
Implementation:45c253d3cc35402301734d313a1717675917a849
CRM evidence:https://github.com/siteengine-git/siteengine-crm-configuration/blob/7da32a653cc0453ace4f623aadeb1ed21e632918/docs/ADS-070-COEXISTENCE-0316-RESULTS.json
