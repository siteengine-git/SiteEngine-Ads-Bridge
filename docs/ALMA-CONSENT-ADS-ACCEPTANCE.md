> Updated dependency/code preparation: Consent implementation
> 1c0a42000927db720cfc60934ea5e2b31db28533 and docs
> b8ceb8c292ff3a198bd34548ec2e5a2d13afe98f now supply the real rc.2 authority;
> Ads enforcement is complete in this maintenance successor. The former API block
> below is historical. Bind the final source/package manifests from
> MAINTENANCE-RELEASE.md before authorizing J0-J7. J4 must assert old withdrawn
> submissions remain blocked after re-grant while newly captured eligible ones
> pass. Consent owns its new InnoDB chains/events schema installation; Ads adds no
> schema. All installed cases remain NOT RUN and require the coordinator.

# Finite joint Consent/Ads installed acceptance — PREPARATION ONLY

Owning projects: Consent (authority, capture/persistence) and Ads Bridge (consumer,
browser payload, advertising builders). Alma coordinator records the assignment,
conflicts, executor, exact resources, rollback and cleanup owners through Aubrey.
Infrastructure belongs to Alma Codex; project testing requires explicit delegation.
No execution, bootstrap or server configuration is authorized by this document.

## Entry gate and exact pins

Ads reviewed code: ba5aff0a94fd490819aaec91f3125b344bef4687, maintenance preparation
on its scoped descendant. Consent inspected pin:
37cb66d56b1039255ff2348ef5e5a94edc353db2, 0.6.1-rc.1; authority SHA-256
7c3b419fcc2b33563dc0fde9f917ac44ad620b905bb7cd3a9ec4cfb35586fc54.
Neither is a completed withdrawal-capable joint candidate. **Entry blocked** until
the Consent owner supplies the actual authority and Ads integrates/tests it.

Before execution, replace these planning pins with final Ads/Consent commits,
versions, ZIP/member-manifest hashes and fixture harness hash. Coordinator records
the actual profile, effective owner/web+CLI PHP, plugin/dependency versions, form
ID/field mapping, actual prefix/table names and fixture IDs; never borrow Golden
identities. Verify candidate bytes and preserve restricted installed-file backups.
Do not allocate another database or resume the Windows initializer to satisfy this
matrix. Read-only installed inspection and any application bootstrap also require
the coordinator's recorded task scope.

## Minimum isolation proof

Use one operator-only fixture window, authority-defined synthetic subject context,
reserved non-deliverable contact data and exact marked resource IDs. A subject
UUID alone is not trusted correlation: Consent's final API must define it. Record
only evidence needed for these cases, no customer rows/secret values.

| Reachable path | Required scoped control and proof before the first fixture |
| --- | --- |
| Form notifications and feeds | Suppress fixture form notifications, SMTP/provider dispatch, CRM delivery, Sheets/webhooks/payment feeds through supported, verified scoped controls. Show effective matching/priority and zero bypass routes. wp_mail alone does not cover direct provider SDK/SMTP/socket feeds. |
| Queues and in-flight workers | Record relevant launchers/queues, ensure no already-running writer/delivery can consume test IDs, and owner-confirm a held/drained state without running queues. Defer if fixture-scoped exclusions cannot survive delayed retries. Do not blanket disable cron or stop unrelated maintenance. |
| Browser analytics/callbacks | In the controlled browser, prevent advertising/analytics/provider network delivery before page load; retain the actual generated response/dataLayer evidence locally. Capture a bounded request ledger showing zero external sends. Include relevant Call Bridge callback/feed paths if the chosen form/page can reach them; no calls or provider tests. |
| External delivery fallback | Where owner-supported fixture controls cannot cover a reachable direct path, Alma administrator supplies a reviewed site-scoped egress/relay control, accounting for privileged/shared launchers and in-flight work. That specific uncovered path is a blocker; hooks alone are not proof. No server-wide mail/network fence assumed. |
| Cloudflare and OLS/LSCache | Verify that fixture form/AJAX/confirmation responses are not cached/shared and do not replay stale consent state. Use an authorized narrow bypass if required; account owner handles account-level cache authority. Record origin versus cached evidence. Do not purge unrelated sites or assume query strings bypass cache. |

The full historical isolation implementation is **not automatically required**.
Only the reachable fixture paths need coverage, but every such path must be proven
covered, including shared/privileged delivery. Existing evidence may satisfy a
control when its applicability/current state is verified by the assigned owner.
No unbounded mail queue scan is part of this assignment. Unknown reachable worker,
relay, feed or cache behavior blocks fixtures until that specific gap is resolved.
Control implementation and verification are separate coordinator-authorized work.

## Finite resource and mutation ceiling

One configured test form within the assigned site's supported master-form context,
one controlled browser subject and at most two synthetic contacts; at most ten new
Consent event rows, ten submissions with owned details, and four Ads inquiry rows.
One grant/withdraw/re-grant sequence only; no load loop. Mark and collision-check
all test identities before use. No broad scans, exports, Sheet provisioning/writes,
real numbers/calls, messages, invoice changes on real contacts or production API
transport. Any required form-definition/configuration/CRM lifecycle writes must be
enumerated and owner-delegated in the assignment, including allowed change count.
Do not create extra forms or increase these ceilings after a failure.

Use actual hooks and vendor persistence for the positive flow. Failure/legacy
fixture injection must be separately reviewed, test-ID-only and restored from its
owned snapshot; never tamper with a customer's receipt or an original table row.
Inspect Qualified/Converted builders directly through a reviewed bounded private
test harness, with owned IDs and in-memory results; **do not enable export families
or run export commands to test eligibility**. Exact harness must be pinned/reviewed
once the real withdrawal API exists; this document is not an executable harness.

## Ordered cases and required evidence

| Case (run once, stop at first failure) | Pass evidence | Stop/recovery |
| --- | --- | --- |
| J0 Provenance/isolation | Both packages match final manifests; source/runtime dependencies recorded; scoped controls verified; real form/schema/policy mapping recorded, original configuration/table totals/GC/queues captured without contents. | Missing schema or isolation: no fixtures. Request only the exact prerequisite; no implicit migration. |
| J1 Grant A and actual persistence | Explicit acknowledged event G1, real form submission A, form-bound signed stored stamp and one exact digest row. Actual response-array/submission_form_data transforms, stored response, serialized details and confirmation order recorded. Browser payload for canonical A only when current authority permits. | Persisted/request disagreement or early payload: preserve owned rows/evidence, stop; do not upgrade submission later. |
| J2 Rejection/conflicting evidence | Rejection B plus attempted conflicting flat/detail/browser grant cannot override the stored negative decision. Pending/failed ACK, null/malformed/unknown stamp and absent unsigned legacy fixture each block both advertising builders and browser payload; internal All_Leads remains usable. Local tests cover corruption; installed injection only through the reviewed fixture harness. | Any advertising eligibility/payload is a failure, even if delivery fence caught it. No receipt repair or fallback. |
| J3 Subsequent withdrawal | Same authority-defined subject/scope withdraws after G1. Current callable returns withdrawn; old A and new rejected B produce no browser advertising payload and are ineligible for Qualified/Converted. A's stored receipt/signature/digest/attribution remain byte-identical. | Unknown identity/read/result blocks, not permission. Preserve controls and unresolved recovery record. |
| J4 Subsequent re-grant | New acknowledged event G2 and submission C. Compare old A and new C against the final Consent contract: explicitly assert its old-inquiry revival or sticky-withdrawal rule. Never replace A's evidence with G2. | No documented rule or conflicting result: fail, do not infer latest receipt. |
| J5 Authority failures | Fixture-scoped missing callable and failed/unavailable current-permission reads deny browser payload and both builders, while intact historical proof remains stored. Actual real authority handles success; no always-successful stub. | Restore only reviewed fixture override; verify restoration. Any uncertainty retains isolation. |
| J6 Policy, DNC, personalization, inquiry binding | Reversible test-contact DNC toggled once on/off and required-policy mismatch tested in the reviewed fixture harness; both builders deny appropriately. Independent personalization denied/granted remains independent of ad_user_data. One active and one archived reached-stage inquiry keep canonical A/B binding despite mutable CRM submission changes. 21/94 column contracts unchanged. | No real contact edits, head release or new CRM transitions implied. Existing holds/adopt-before-replace remain CRM-owned and must already be proven if that path is used. |
| J7 Closure | Zero external deliveries; original file/configuration/schema/cron/GC state compared; original rows unchanged outside normal attributed maintenance. All fixture resource IDs accounted for, bounded cleanup verified or explicit retained-evidence ownership recorded. | Failed evidence preserved; no retry outside procedure. Do not lift controls while a fixture worker/delivery can remain in flight. |

No assertion may pass solely because a worker exited0. Require stored identity,
actual callable results and suppression/eligibility evidence for each case. Do
not count isolated tests as these installed results. Any extra case requires a
separate scope decision; no automatic hardening or initializer continuation.

## Recovery, cleanup and completion

Stop after any unexpected effect. Keep the test gate/holds and record partial
fixture IDs, pending jobs and last completed case before recovery. Do not run a
queue to discover whether it is dangerous. Owners confirm cessation and handle
only assigned workers/resources; never kill unrelated processes or delete mail.

Preserve restricted results before cleanup. Delete only collision-checked marked
fixture contacts/submissions/details/inquiries/events by their owning plugin's
reviewed method and exact ID allowlist, subject to foreign-reference and audit
retention checks. If Consent audit semantics disallow deletion, retain those
synthetic events with an explicit owner/retention disposition rather than bypass
the contract. Do not purge tables or restore a whole database/global cron. Restore
only recorded scoped form/test overrides and file backups when authorized; a file
rollback to older Ads cannot authorize advertising delivery with the known defect.

Return actual pins/hashes, J0-J7 pass/fail/not-run, evidence paths, independent
read/persistence results, zero-delivery ledger, restored settings and every
remaining resource/cleanup owner to Alma Helper through Aubrey. Release promotion
and delivery enablement remain separate decisions after this finite review.
