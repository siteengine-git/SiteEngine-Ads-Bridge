# Ads Bridge 0.8.1

Fixes ordinary setup blocked by an external configuration flag pointing to a missing
or unreadable file. Setup now offers an explicit WordPress-settings choice without
requiring legacy import, CRM import, credentials access or complete readiness.
Saved values and original files are preserved. Explicit settings authority survives
later legacy-file changes/removal; prior recorded imports retain their intent.

Intentional external overrides remain protected until explicitly changed.
Malformed/throwing legacy files produce bounded diagnostics rather than a fatal
settings page. Optional imports require readable, successfully loaded, hash-reviewed
content. Existing executor/configuration guards and Consent/export behavior remain.
Obsolete Migration directions now point to Setup > Configuration authority.

Alma-only verification: released 0.8.0 rejection reproduced; seven authenticated
administrator scenarios plus three final-package delta cases passed. Final package:
51 members, 29 native PHP 8.3.36 lints; main ordinary administrator save/fresh readback
and exact original settings preservation verified. 140 focused local assertions.
WordPress/vendor/SQL doubles are identified in local results.

Main Alma has one genuine credentials-access failure with its original sink flag
preserved: Doctor35 PASS/5 WARN/1 FAIL; all four export families disabled. No claimed
service-account access, live delivery or client fix. Final CRM0.3.18 compatibility,
normal authenticated administrator HTTP export/download and exact Ads binding pass.
This is carried-forward HTTP/native evidence, not a new browser-click test.
The reported installation itself was not inspected; its correction is not asserted.
See RESULTS.json for exact coverage and retained test-tool failure/restoration receipts.
