[CmdletBinding()]
param([switch]$VerifyCurrentZip)
$ErrorActionPreference = 'Stop'
# Canonical delivery root: ..Deliverables is a literal child of Plugins.
$directory = Join-Path 'D:\SiteEngine\Plugins\..Deliverables' ('PLUGIN ' + [char]0x2014 + ' SiteEngine Ads-Bridge')
if (!(Test-Path -LiteralPath $directory -PathType Container)) { throw 'Canonical Ads delivery directory is missing.' }
if ($VerifyCurrentZip) {
    $zips = @(Get-ChildItem -LiteralPath $directory -Filter '*.zip' -File -Recurse)
    if ($zips.Count -ne 1) { throw 'Expected exactly one current installable ZIP; preserve archives outside this directory.' }
    $release = Join-Path $PSScriptRoot ('..\releases\' + ($zips[0].BaseName -replace '^siteengine-ads-bridge-', '') + '\DELIVERY.json')
    $pin = Get-Content -LiteralPath $release -Raw | ConvertFrom-Json
    if ($zips[0].Name -ne $pin.package -or (Get-FileHash -LiteralPath $zips[0].FullName -Algorithm SHA256).Hash.ToLowerInvariant() -ne $pin.sha256) { throw 'Delivery ZIP does not match its release pin.' }
}
$directory
