## Subsequent Climatrol setup completion - November 12, 2026

The initial site configuration issues below have now been corrected. Ads Bridge
reports Ready with zero setup issues. The selected form feed is configured and
enabled without forced subscription; timezone is saved; the corrected existing
spreadsheet passes authenticated read access. Referral no longer maps to Qualified.
No contacts, email, enrollment or advertising export was used. Write permission
and actual form delivery are not claimed. The remaining warning is no recorded
scheduler run. See SITE-CONFIGURATION-RESULTS-20261112.json for the exact scope.
The ZIP/manifest are unchanged; initial observations below remain historical.

# SiteEngine Ads Bridge 0.9.0

One Setup page with one Save settings button replaces the numbered wizard.
Normal CRM ZIP import is removed. Set up CRM requirements creates only missing
required lists, lifecycle tags and contact fields. Existing content, settings,
custom mappings, native identities and import history are retained.

Base supplies business data; Consent supplies policy identity. Manual input
attestations and optional contact/submission verification IDs are removed.
The selected form feed and lifecycle are real readiness checks; incomplete
settings remain saveable. Ads account timezone is a separate explicit setting.

Test connection authenticates and reads spreadsheet identity using entered
draft values; it does not save, read rows, append rows or prove write permission.
API Setup retains FieldEdge diagnostics and its explicit missing-only field
utility. Settings retains field overrides, diagnostics and scheduler evidence.

Backed-up installation and actual authenticated CRM utility/save/reload passed
on climatrol.siteengine.space with PHP 8.4.27 and all 50 native PHP lints.
The current selected form has a disabled CRM feed; Ads account timezone is blank.
The saved spreadsheet returned HTTP 404 after authentication. These require
configuration correction before live delivery; no live conversion success is
claimed. See RESULTS.json for exact evidence and retained limits/failures.

Source: 0dc681518f607b59a293656ea57d523e223b4bd1
ZIP SHA-256: 43bda23c2b45e7ebc177f69d1535f6a30cdc10609cfda3aee0f1ed6913be6a60
Manifest SHA-256: a26bb29541dbd55af5dde8686f9b32f7687e296a5608efbda8408df8437abb88

This is the verified scoped setup delivery. No separate stable GitHub promotion
or production client deployment was performed. Historical RC metadata is
superseded by this release note and RESULTS.json; original archives remain intact.
