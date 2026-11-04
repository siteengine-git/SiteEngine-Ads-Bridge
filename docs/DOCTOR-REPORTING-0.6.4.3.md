# Doctor failure reporting, 0.6.4.3

Install the versioned ZIP directly using Plugins > Add New Plugin > Upload Plugin;
replace the existing Ads Bridge version. No intermediate upgrade, migration, settings
reset or transport activation is required for this reporting correction.

On the SiteEngine dashboard, Ads Bridge's Needs Attention status lists every failing
Doctor check by its readable name and original explanation. The Ads-owned error
notice links to Ads Bridge > Diagnostics and, for recognized checks, to the existing
corrective destination. The same notice appears on Ads Bridge pages. Diagnostics
retains all PASS/WARN/FAIL/PENDING rows. Failures remain errors; they are not hidden,
downgraded or replaced with generic Business Data instructions.

Examples: Google Sheets value-input/sink/credential checks point to Ads Bridge >
Google Sheets; export checks to Exports; lifecycle checks to CRM & Lifecycle;
inquiry storage checks to Storage; core shared timezone/CID/currency checks to
Business Data; missing plugin/API dependencies to Plugins. Unknown checks retain
their complete explanation and Diagnostics link without inventing a correction.
Follow the actual explanation; opening a destination never applies a correction.

The initial Alma inspection was33 PASS/9 WARN/0 FAIL/0 PENDING.
The previously reported single failure was not present and its underlying cause
remains unknown. The demonstrated code defect was count-only module reporting.
No unrelated functional repair, configuration change, Consent repair, journal
reset or export enablement is included.

Bounded installed verification uses actual WordPress administrator HTTP requests
with one and multiple real Doctor failures generated from request-local malformed
configuration. No such malformed value is saved. Original Ads configuration,
storage, inquiry/Consent data, journals and disabled flags remain protected.
Final executed results and cleanup are recorded in releases/0.6.4.3 alongside the
exact package checksum. Prior setup/Consent acceptance is retained evidence and
is not repeated by this correction. No client or production deployment is included.
