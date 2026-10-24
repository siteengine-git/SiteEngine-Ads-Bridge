# Accepted 0.6.3 source baseline import

This checkout preserves the repository's original README/main history and
imports the retained accepted release. It does not change plugin behavior or
version, deploy a site, migrate a schema, change settings or execute an export.

Original ZIP: siteengine-ads-bridge-0.6.3.zip, retained in the parent project.
SHA-256: c77c9f2f50aea5bd37ccdd51221e4384c80dbb6fa18da2cd84c265d4f0b8c404

All 38 entries in the original RELEASE-MANIFEST.sha256 verified before import.
The original manifest is retained unchanged for archive provenance. All runtime
PHP files, the neutral configuration example and header fixtures remain byte
identical to the accepted release. The original archive is unchanged.

## Public-copy neutralization

Seven imported files have intentional test/documentation-only changes:

- tests/config-settings-acceptance.php
- tests/static-acceptance.php
- tests/static-acceptance-060.php
- docs/0.6.0-to-0.6.1.diff
- docs/0.6.1-to-0.6.2.diff
- docs/0.6.2-to-0.6.3.diff
- docs/INQUIRY-ACCEPTANCE-STATUS.md

Real environment account/spreadsheet identifiers were replaced with synthetic
sentinels, and internal site fixture identifiers were removed from narratives.
The resolver test's expected value and its synthetic Base return value were
changed together. These are publication hygiene edits, not runtime fixes.
Historical diff copies are redacted references and no longer exact originals.
The untouched originals remain available in the retained local release ZIP.

SOURCE-BASELINE-MANIFEST.sha256 verifies the 39 imported payload files,
including the original manifest, as published after neutralization. Added
repository guidance and Git hygiene files are outside that payload manifest.
The original RELEASE-MANIFEST.sha256 still describes the original ZIP and will
show the seven documented differences when checked against this public copy.

## Validation and acceptance limits

Fresh local checks: original ZIP hash; 38 original manifest entries; imported
payload comparison with exactly the seven permitted differences; all runtime
files unchanged; 23 PHP files have opening tags with no BOM; CSV contracts
remain All_Leads 94 and Google_Ads_Import 21 columns. Staged blobs are checked
against working bytes to prevent Windows line-ending conversion.

No local PHP executable was found on PATH or in the inspected existing runtime
locations. An attempted in-memory Alma validation command was rejected by
automatic approval review before execution because it transferred checkout
contents. No fresh PHP syntax execution or isolated suites are claimed.
There were no server writes or database tests during this import.

Historical release results remain 373 PASS / 0 FAIL across 11 suites, including
21 native SQL assertions. Subscriber persistence was simulated. The packaged
reports predate later installed Golden evidence; do not rewrite their dates
or treat them as new checks. Live repeat-inquiry integration remains unaccepted.

The original synthetic fixture remains in tests/fixtures/client.php; no active
config/client.php, service-account JSON, credentials, client records, logs,
dumps, install archives or local environment handoffs are published.

Git staged audit: all 45 allowlisted blobs equal working bytes, with publication exclusions checked. Whitespace checks pass outside historical .diff artifacts; those preserve literal unified-diff context-prefix whitespace.
