# SiteEngine Ads Bridge 0.9.2

Corrects the customer_rating collision with legitimate Customer Rating options Excellent, Good, Fair and Poor. Keep that field and its contact values unchanged.

Fresh setup uses se_ads_unqualified_reason. The ordinary Set up CRM requirements action resolves an incompatible legacy customer_rating mapping to the separate field, preflights all definitions and saves the mapping through the existing protected handler. Compatible explicit legacy reason mappings remain. Dedicated/custom conflicts still stop before definition writes. No values are copied or reinterpreted.

New-inquiry activation preserves unrelated Customer Rating. Historical inquiry reason snapshots remain authoritative with the new CRM mapping; existing inquiry/worksheet schema names remain unchanged. No automatic loss inference, migration of history, importer, provider request or sending.

INSTALL: Back up the site, upload this ZIP as an update to the existing Ads Bridge plugin, then use Setup > Set up CRM requirements. It creates only missing definitions and retains existing ones. On the reported rating collision, the six disqualification choices go in the separate Unqualified Reason field. Repeating setup must not duplicate definitions.

VERIFICATION: 80 setup, 34 product, 16 form, 35 reporting, 45 inquiry, 18 lifecycle, 6 internal timing and 5 historical-row assertions passed against actual algorithms with vendor/DB doubles. All 50 shipped PHP files lint under PHP 8.3.36 -n. 79 ZIP entries and 78 payload hashes verified; one plugin root, forward-slash member paths. No live site installation or native/browser acceptance of 0.9.2 is claimed. SiteEngine.io has no registered owner/root/profile in the inspected Access inventory. Current accepted 0.9.1 delivery was preserved; this separately versioned candidate is ready for installation and native acceptance. No stable GitHub publication.

BUILD ATTEMPTS: Local initial ZIP creation hit Windows UTF-8 path decoding; the next attempt used Windows ZIP separators and was rejected/retained privately. Explicit assembly loading and explicit forward-slash entries produced the final verified ZIP. No server operation or existing delivery was affected.

Source: bd0c57ef073b63f5c3f8c79077f77ad59a4e02eb
ZIP SHA-256: 73af3771918fa2ca4a0b319df7218010a385c30decf31005e461a6f9f0b561b2
Manifest SHA-256: 8943c0a3a219ae0e3a82bcf6bb04448699660687314cffaf6958711708e7cd5c
