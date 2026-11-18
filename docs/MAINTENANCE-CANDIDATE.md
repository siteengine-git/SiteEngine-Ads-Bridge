> Historical checkpoint; superseded by [rc.2 enforcement and current maintenance notes](MAINTENANCE-RELEASE.md).

# Ads maintenance preparation — 2026-10-29 — BLOCKED / UNACCEPTED

Purpose: ship the reviewed Consent consumer repair and the smallest necessary
withdrawal enforcement, retaining accepted inquiry behavior. No general hardening,
configuration-coordination or Windows database investigation belongs in this release.

## Exact available code and dependency

Ads code pin: `ba5aff0a94fd490819aaec91f3125b344bef4687`, based directly on public
main `cfdbba3f765539f4ce0977ef3b6c9ca48603465a`. This scoped maintenance preparation
descends from that repair, not from the unrelated local combined branch. Existing
local main/combined checkpoints and all retained allocations remain preserved.
Accepted installable baseline remains 0.6.3, inquiry schema 2, PHP adapter contract 1,
configuration tree schema 2. The original archive SHA-256 remains
`c77c9f2f50aea5bd37ccdd51221e4384c80dbb6fa18da2cd84c265d4f0b8c404`.

Consent local HEAD and remote main were inspected read-only on 2026-10-29:
`37cb66d56b1039255ff2348ef5e5a94edc353db2`, 0.6.1-rc.1. Remote lists only main.
Read actual CONSUMER-CONTRACT.md, ADS-CODEX-ASSIGNMENT.md and authority methods.
Authority SHA-256:
`7c3b419fcc2b33563dc0fde9f917ac44ad620b905bb7cd3a9ec4cfb35586fc54`.
Available methods: constructor, google, receipt_digest, save, resolve, negative,
seal_stamp, verify_stamp and stamp. None is a current withdrawal authority.
The owner's dirty guidance/acceptance files were preserved, not adopted as code.

`verify_stamp` authenticates historical form-bound evidence; `resolve(cookie)`
handles browser ACK validation/expiry. Neither answers whether the subject has
subsequently withdrawn advertising permission. The contract explicitly disclaims
current revocation eligibility. No newer implemented callable/handoff was found.

**Consequently the dependency set is incomplete. No release ZIP, final package
manifest, tag, installed candidate or acceptance claim is produced.** Current code
still accepts a valid historical grant under its existing policy/DNC gates; a later
rejection on a distinct event does not revoke that older grant. It is not safe to
describe the existing historical test as withdrawal enforcement. Keep advertising
delivery disabled pending the missing authority and the installed gate below.

## One dependency request for Consent

Provide one implemented, commit-pinned read-only callable that determines current
advertising permission/withdrawal for the subject/scope bound to an Ads canonical
signed submission and exact receipt. Supply its actual invocation, request/result
shapes, trusted correlation source, explicit withdrawn/unknown/read-failure outcomes,
and the rule for older inquiries after withdrawal followed by re-grant. Include
real implementation tests with synthetic storage. An arbitrary latest receipt,
untrusted email/cookie claim or historical signature alone cannot supply that link.
Consent owns this authority and correlation; Ads will consume it without rewriting
history. No proposed PHP method name or made-up return contract is adopted here.

Once supplied, the remaining Ads code task is narrowly bounded: call that real
authority at the shared advertising eligibility boundary used by browser
confirmation and Qualified/Converted builders; deny withdrawn, unknown, failed or
unverifiable permission. Preserve signed historical evidence, canonical inquiry
binding, current DNC, required policy, ad_user_data and independent personalization.
Keep operational All_Leads reporting unchanged. Test original grant, withdrawal,
re-grant, absent API and failed reads with the actual new authority, including old
and new inquiry eligibility according to its stated rule. No placeholder gate or
successful verification stub has been added in the meantime.

## Release identity and package boundary

Existing local release plan reserves **0.6.4 for coordination**; no remote tags or
competing maintenance reservation were found in the inspected Ads refs/material.
Tentatively reserve **0.6.3.1-rc.1** for this separate maintenance candidate. PHP
version_compare verified `0.6.3 < 0.6.3.1-rc.1 < 0.6.3.1 < 0.6.4`; this avoids
consuming or overtaking the coordination reservation. No tag, code-version bump
or final release reservation outside this owning project has been published.

When the dependency/code set is complete, update header/SEAB_VERSION/changelog,
package requirements and version assertions together on this scoped branch. Keep
existing PHP7.4 metadata unchanged today; only PHP8.3.36 isolated behavior is
evidenced. Reconcile the final authority's actual minimum before freezing metadata;
do not import the unfinished coordination path's PHP8.3/mysqli restrictions.
No new WordPress minimum or support claim is inferred from isolated tests.

`maintenance/PREPACKAGE-INVENTORY.json` and `.sha256` record only a prospective
allowlist of current source payload bytes. They are **not** a release manifest:
metadata still says 0.6.3 and withdrawal enforcement is absent. No executable
builder is supplied that can accidentally package this incomplete set. The future
ZIP must have one `siteengine-ads-bridge/` root, exact committed payload bytes,
fresh per-member manifest and ZIP hash. Re-open/check every member before delivery.
Exclude active client.php, credentials, tests/fixtures, local evidence, logs/dumps,
original baseline manifests, coordination/native code and unrelated docs/history.
Include the neutral example and required runtime/inquiry modules; do not remove
accepted inquiry functionality to achieve the exclusion.

## Upgrade and rollback consequences

This maintenance scope introduces no inquiry/ledger/configuration schema, DDL,
data migration, grant backfill or history repair. Existing missing accepted schemas
remain a site prerequisite, not permission to migrate in a file update. Keep
unfinished coordination stores/schema/API out of the package; do not delete an
existing coordination store if one is later found on the target. An installation
already running the unfinished combined candidate is not an assumed file rollback
target: coordinator must reconcile its writers/state and CRM binding first.

Unsigned legacy submissions that previously exported through flat/details now
block advertising. Historical missing/malformed signatures, changed site keys,
ambiguous identities or digest failures also block; preserve those rows. Fresh
choice/new submission can produce new evidence, never a retroactive grant. A
rejection in the inquiry's own verified stamp remains ineligible. Older inquiry
behavior after later withdrawal/re-grant remains **undefined until Consent supplies
the actual authority**, and cannot be declared accepted or automatically restored.
All_Leads operational rows remain available; Sheet contracts stay 94/21 columns.

For later installation, verify/install the completed pinned Consent dependency
before Ads, under fixture isolation and owner-controlled quiescence. Preserve
installed files/configuration and fixture ID inventory first. Do not reactivate,
uninstall, run cron, migrate or enable exports merely to replace files. On failure,
stop and preserve evidence; authorized file-only restore may not re-enable unsafe
older advertising delivery. Never restore a whole database to remove fixtures.

## Validation reused and still missing

The scoped repair's existing **456 PASS / 0 FAIL across 11 isolated suites**
(104 focused plus 352 existing) and seven PHP8.3.36 lint passes are reused, not
rerun. Current source/dependency blobs were compared to the tested scoped snapshot
and hash-pinned authority. The combined branch's extra55 coordination assertions
are excluded from maintenance evidence. See CONSENT-CONSUMER-COMPATIBILITY.md.

No PHP/test/runtime bytes change in this preparation. No database provisioning or
native retry. Withdrawal/re-grant/withdrawal-read-failure acceptance is BLOCKED,
not passed/skipped by a successful stub. Real WordPress form persistence/order,
browser/cache, primary-database read behavior and installed current permission
checks remain pending. The finite assignment is ALMA-CONSENT-ADS-ACCEPTANCE.md;
no site operation is authorized by either document.

## Copyable handbacks through Aubrey

**Consent Helper:** Ads repair is complete at ba5aff0; inspected Consent37cb66d
still has no current withdrawal callable. Please provide the one pinned callable
capability and actual request/results/trusted subject correlation above, including
whether re-grant can revive an older inquiry. This is the only code dependency
holding the maintenance candidate; no historical/latest-receipt workaround.

**CRM Ops Helper:** Maintenance excludes unfinished configuration coordination;
accepted inquiry schema2/contract1 behavior stays. Preserve existing4c448ec binding
as historical, not coverage of this scoped successor. Unsigned legacy advertising
now blocks and no records are repaired. Final combined pin/rebind awaits withdrawal
integration and finite installed review; retain holds and disabled delivery.

**Alma Helper:** Preparation only. Please record a future joint Consent/Ads test
assignment/executor after final pins/ZIP hashes exist and Consent withdrawal is
implemented. Use the finite matrix and scoped isolation proof below; no new server
access, deployment, fixture writes, queues or cleanup authorized today. Detailed
site-specific coordination material is retained locally for Aubrey.
