# Build and delivery

Resolve the physical destination with `tools/Get-DeliveryDirectory.ps1`.
The canonical path is `D:\SiteEngine\Plugins\..Deliverables\PLUGIN — SiteEngine Ads-Bridge`.
`..Deliverables` is a literal child of Plugins. `Plugins..Deliverables` is a
different directory and must not receive deliveries.

Freeze a scoped source commit. Build from exact Git blobs and an explicit payload
allowlist, with each payload size/SHA-256 in embedded SUCCESSOR-MANIFEST.json.
Record source, ZIP and manifest hashes in releases/<version>/DELIVERY.json.
Exclude credentials, active config, private evidence, logs and development launchers.
Lint packaged PHP and verify every archive member before installing the same ZIP.
Keep later publication/installed evidence alongside the package without repacking.

Stage and hash the successor before archiving the existing delivery outside this
directory. Leave exactly one installable ZIP recursively, with matching manifest,
checksums, instructions and results. Verify remote tag/source and published asset
digests. Preserve historical ZIPs, frozen demonstrations and GitHub releases.
