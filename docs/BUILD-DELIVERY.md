# Canonical Ads delivery directory

Use `tools/Get-DeliveryDirectory.ps1` to resolve the exact directory:

```powershell
$delivery = & .\tools\Get-DeliveryDirectory.ps1 -VerifyCurrentZip
```

The canonical path is:
`D:\SiteEngine\Plugins..Deliverables\PLUGIN — SiteEngine Ads-Bridge`.
This is the exact task-specific destination explicitly named in the November 4
Doctor-reporting assignment. It supersedes the earlier general child-directory
default for this correction. Populate repositories normally; keep one latest
installable ZIP and matching release documents here. Preserve historical packages
outside delivery. Do not change a task's literal destination based on old receipts.

Release source uses `releases/<version>/MANIFEST.json` and `DELIVERY.json`. Preserve
their exact payload/embedded-manifest bytes and independent package hashes. A pinned
package must be verified, not reconstructed for deployment. Publish only its exact
installable ZIP and matching neutral metadata into the canonical directory. Keep
exactly one current ZIP there, including subdirectories; preserve older artifacts
under the existing project's delivery-history outside the delivery directory.
No active configuration, credentials, logs or client data belong in an archive.

The prior accepted 0.6.4.2 ZIP remains hash-preserved in project archives and its
GitHub release. The 0.6.4.3 correction's exact package hash and installed reporting
evidence are in `releases/0.6.4.3/`. Packaging never regenerates an accepted ZIP
just to change its delivery location.
