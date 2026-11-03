# Canonical Ads delivery directory

Use `tools/Get-DeliveryDirectory.ps1` to resolve the directory. Its construction
keeps the directory separator explicit:

```powershell
$delivery = & .\tools\Get-DeliveryDirectory.ps1 -VerifyCurrentZip
```

The canonical path is:
`D:\SiteEngine\Plugins\..Deliverables\PLUGIN — SiteEngine Ads-Bridge`.
`..Deliverables` is a literal directory immediately inside `Plugins`. Do not append
that name to `Plugins` without a separator. Historical one-off build commands and
receipts are evidence, not the current build procedure.

Release source uses `releases/<version>/MANIFEST.json` and `DELIVERY.json`. Preserve
their exact payload/embedded-manifest bytes and independent package hashes. A pinned
package must be verified, not reconstructed for deployment. Publish only its exact
installable ZIP and matching neutral metadata into the canonical directory. Keep
exactly one current ZIP there, including subdirectories; preserve older artifacts
under the existing project's delivery-history outside the delivery directory.
No active configuration, credentials, logs or client data belong in an archive.

For 0.6.4.1 the runtime source pin is b2dacd9d5b70c28fd705f87a3c57de35bda84b6e and
ZIP SHA-256 is f1325169c411bdf54a717d2100b9cea6be9a0b284181792855e5f4963361c5d7.
This path/tooling correction does not rebuild or change that reviewed package.
