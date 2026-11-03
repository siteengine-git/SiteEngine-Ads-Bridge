# Canonical Ads delivery directory

Use `tools/Get-DeliveryDirectory.ps1` to resolve the exact directory:

```powershell
$delivery = & .\tools\Get-DeliveryDirectory.ps1 -VerifyCurrentZip
```

The canonical path is:
`D:\SiteEngine\Plugins..Deliverables\PLUGIN — SiteEngine Ads-Bridge`.
`Plugins..Deliverables` is one literal directory name immediately inside
`D:\SiteEngine`. It is a sibling of `Plugins`. The older directory
`D:\SiteEngine\Plugins\..Deliverables\PLUGIN — SiteEngine Ads-Bridge` is not the
delivery destination. Historical build commands and receipts remain evidence,
not the current build procedure.

Release source uses `releases/<version>/MANIFEST.json` and `DELIVERY.json`. Preserve
their exact payload/embedded-manifest bytes and independent package hashes. A pinned
package must be verified, not reconstructed for deployment. Publish only its exact
installable ZIP and matching neutral metadata into the canonical directory. Keep
exactly one current ZIP there, including subdirectories; preserve older artifacts
under the existing project's delivery-history outside the delivery directory.
No active configuration, credentials, logs or client data belong in an archive.

Accepted stable 0.6.4.2 is pinned by tag `v0.6.4.2`; ZIP SHA-256 is
`4c130a7ca7dd0914535743964d6c9e269663604039df04dd722e21c55ba76380`.
This path/tooling correction does not rebuild the accepted ZIP, change its
manifest/release assets, alter runtime, repeat acceptance or perform site operations.
