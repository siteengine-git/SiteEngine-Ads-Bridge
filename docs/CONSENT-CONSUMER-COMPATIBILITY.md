# Consent consumer compatibility checkpoint — 2026-10-29

Unreleased Ads-owned repair; accepted installable Ads Bridge remains 0.6.3,
inquiry schema 2, adapter contract 1 and configuration tree schema 2. No version,
schema, package, transport, configuration or historical-data change is included.

## Dependency and eligibility

Reviewed Consent: 0.6.1-rc.1 at
`37cb66d56b1039255ff2348ef5e5a94edc353db2`, stored authority contract v1.
Requires the real callable `SiteEngine_Consent_Authority::verify_stamp($stamp,
(int)$canonical_form_id)` and `receipt_digest($exact_row)`. No bundled substitute
or version-string-only acceptance. A missing API, invalid signature or rotated
site signing key blocks advertising; historical records are never re-signed.

The shared read-only consumer loads the canonical persisted submission and checks
its ID/form. Presence of `_se_consent_server`, including null/scalar/invalid
values, is conclusive: only structurally valid, verified, signed form-bound v1
evidence can proceed. Flat fields and entry details cannot override it.
Absent stamps and unsigned legacy receipt claims also block; no backfill,
deduplication or upgrade of old submissions occurs.

The receipt query selects all 13 digest-bound columns and at most two rows
matching either signed identity component. Exactly one row must match both row
ID and receipt ID, the real authority digest and the signed metadata/choices.
Missing columns, failed reads, changed data and duplicate identity block. No
newest-row lookup or mutable CRM consent authority is used. Active and archived
inquiries retain their original canonical submission, including reached-stage
eligibility. Existing configured required-policy and current DNC gates remain;
`ad_user_data=granted` is required and personalization is independent.

Browser confirmation now uses the same persisted proof and a nonempty matching
required policy rather than trusting submitted flat receipt identity. Existing
hashed payload/event semantics are unchanged. Browser confirmation has no new
CRM/DNC lookup; the existing current DNC gates remain in advertising export
builders. All_Leads remains an internal operational report, including blocked
advertising records; its 94 columns and Google_Ads_Import's 21 columns remain.

## Historical choice and unresolved current eligibility

Historical verification calls `verify_stamp`, never browser `resolve(cookie)`:
today's ACK expiry must not erase stored historical choice. Tests explicitly
show an expired real ACK alongside independently valid historical evidence.
Historical grant is not proof of current revocation eligibility or legal
permission. The pinned contract supplies no trusted subject/revocation authority.
The consumer reports `revocation_check=unavailable`; this is an unresolved
integration/release gate, not a new successful revocation check. No inferred
visitor identity, latest-choice query or invented revocation API is introduced.

## Isolated validation and reproducibility

Before edits, the exact pinned Consent consumer-contract test executed the old
Ads resolver with synthetic storage. Five expected-demonstration checks included
negative-stamp bypass through both flat and existing detail grants and reused-ID
newest-grant drift. Those unsafe outcomes were blockers, not acceptance passes.
Detailed before/after logs are retained locally outside public Git.

After repair: focused suite **104 PASS / 0 FAIL**, including a separately executed
missing-authority assertion (counted through its supervisor assertion, not twice).
Eleven existing isolated suites **407 PASS / 0 FAIL**; total **511 / 0**, no
stderr. Seven changed/new PHP files lint successfully with PHP 8.3.36 `-n`.
The focused suite uses actual pinned authority `save`, `resolve`, `seal_stamp`,
`verify_stamp` and `receipt_digest`, with synthetic WP functions/storage only.
No always-successful signature verifier is substituted. All test identities,
keys, rows, URLs and subscriber data are synthetic.

Run from the repository root, with PHP 8.3 and an externally supplied read-only
copy of the exact pinned authority implementation:

```powershell
$env:SEAB_CONSENT_AUTHORITY = '<pinned-authority-file.php>'
# SHA-256 must be 7c3b419fcc2b33563dc0fde9f917ac44ad620b905bb7cd3a9ec4cfb35586fc54
php -n tests/consent-consumer-acceptance.php
php -n tests/static-acceptance.php
php -n tests/static-acceptance-040.php
php -n tests/static-acceptance-050.php
php -n tests/static-acceptance-051.php
php -n tests/static-acceptance-060.php
php -n tests/config-settings-acceptance.php
php -n tests/lifecycle-acceptance.php
php -n tests/inquiry-acceptance.php
php -n tests/inquiry-transition-acceptance.php
php -n tests/inquiry-recovery-acceptance.php
```

The combined local checkpoint additionally runs
`php -n tests/configuration-coordination-acceptance.php` (55 assertions). That
unpublished suite/dependency is intentionally excluded from the scoped branch;
its 55 checks are local combined-candidate evidence only. Scoped-branch results
must be reported separately from this combined total.

The independently assembled scoped publication tree was executed from a
test-owned read-only blob snapshot (no second Git checkout): **456 PASS / 0 FAIL
across 11 suites**, zero stderr. This includes all104 focused checks and352
existing isolated assertions. It excludes the55 local-only coordination checks.
No unrelated dependency had to be imported to make the scoped repair pass.

Existing static acceptance now needs that hash-checked real authority input and
uses genuinely signed complete synthetic receipts; the older receipt-ID-only
fixture can no longer establish advertising eligibility. One static browser
contract assertion now checks the shared consumer call. Other existing suites
retain their assertions and counts. Missing/wrong authority bytes fail the test
setup rather than silently skipping verification.

Not run: native database suites, WordPress/Fluent Forms hook/persistence ordering,
real engine durability/locking, browser/cache lifecycle, actual installed signing
key behavior, CRM end-to-end hold/release and external delivery. These are
separate integration gates, not covered by the 511 isolated assertions.

## Review handbacks through the project coordinator

Consent Helper: review the exact signed/digest consumer behavior against pinned
v1, prospective persisted hook ordering and the absent current-revocation
authority. Preserve blocked legacy evidence. No historical stamp repair assumed.

CRM Ops Helper: the existing Ads binding
`4c448ec48bda60bcbba87dc97255fe215502b9a3` remains historical and unchanged;
this new repair is not covered by that pin. Review and explicitly bind a combined
candidate before integration. The scoped published branch deliberately excludes
unpublished coordination/native-diagnostic commits. It cannot substitute for
the existing combined CRM-bound candidate. Transport remains disabled; current
DNC, inquiry holds/CAS and matching-current-head activation ownership stay with
their established owners. No deployment or release promotion is authorized.
