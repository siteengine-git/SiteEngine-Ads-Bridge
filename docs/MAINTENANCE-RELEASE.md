# 0.6.3.1-rc.1 — unaccepted Ads maintenance candidate

Historical repair ba5aff0 and maintenance preparation b49e2db are preserved.
This successor implements Consent rc.2 current eligibility and excludes unfinished
configuration coordination. Accepted Ads0.6.3 and all native allocations/receipts
remain untouched. No server operation or external message has occurred.

## Dependency and exact behavior

Consent implementation: `1c0a42000927db720cfc60934ea5e2b31db28533` (0.6.1-rc.2).
Contract/docs: `b8ceb8c292ff3a198bd34548ec2e5a2d13afe98f`, specifically
WITHDRAWAL-CONTRACT.md and ADS-CODEX-ASSIGNMENT.md. Consent candidate ZIP SHA-256
reported there: `8471e4761eef927bb10d44967ab88205ef0890a3b6bf90888b8d1ef2c182b619`.
Actual dependency hashes independently extracted from implementation Git:

- Authority: `6d49bfe9006fc108d198dea7309b6dae88e123466d2e0767a2aa74391e4488c9`.
- Continuity: `660c83315e50ae6f159ddcb3f97f28b1f960c726bb5c9be62a0cdccd2507fe3d`.

`SiteEngine_Ads_Bridge_Consent::resolve` still verifies canonical persisted
submission/form, stamp presence/structure, real v1 signature, exact unambiguous
receipt identity and every digest-bound column. It retains the original stamp
only as input to the next check; no copied flat/details/CRM field supplies authority.

`::current` re-reads that evidence and calls the real
`SiteEngine_Consent_Authority::current_eligibility($original_stamp,$form,$policy)`.
Contract2, strict boolean available/allowed, historical verification, eligible
reason, UTC checked_at, ordered integer revisions, strict categories and consistent
effective Google flags are required. Missing API, exceptions, malformed/unavailable
state, legacy continuity or failed reads deny. No positive result is cached.

The global authority database must match the resolver context. `@@read_only` must
return0 without error; a db.php routing/cache drop-in is unsupported and blocks.
The site's stock WPDB connection must target the authoritative primary. These
guards do not prove primary topology: a writable replica could also report0.
Actual DB_HOST/routing/engine/current-read behavior is an installed acceptance
gate, not an inference from this isolated test. No new connection/reconnect,
credential selection or permission changes are made. Consent's final head read
uses LOCK IN SHARE MODE; no SQL result/permission caching is added by Ads.

Enforcement locations:

1. Runtime `receipt_allows_ad_user_data`, called by
   `inject_confirmation_payload`: fresh current check before producing the
   advertising payload. Existing master-form/required-policy gates remain.
2. CLI `resolve_consent_provenance`: builders/previews get effective current
   flags, in addition to historical evidence, policy and their current DNC gates.
3. CLI `google_sheets_append_row`, immediately before wp_remote_post:
   `advertising_dispatch_error` reloads current contact/DNC and canonical
   submission/current permission for every invocation. Both export_batch and
   complete_family_sheet_batch pass the canonical transaction/contact/sink context
   per item, covering Converted, Qualified, append-row delegation and retries.
   Missing context, mismatched row transaction, wrong master form or stale
   effective consent columns block. Exact prepared/retry bodies are not rewritten.
   OAuth token preparation is not a permission grant. No queue/worker is added.

Only the explicit internal All_Leads sinks with94 values bypass the advertising
gate; other rows must satisfy the21-column advertising contract. All_Leads intake
and lifecycle reporting remain operational even when advertising is blocked.
Google Ads API remains disabled; no Sheet enablement or real append was performed.

An acknowledged same-browser withdrawal advances permanent category barriers.
Re-grant never revives withdrawn old submissions: their event revision is behind
the barrier. Newly captured eligible evidence may proceed. Negative submissions
never upgrade after delayed ACK/re-grant. Personalization is the independent
effective original AND current preference/barrier result; a changed prepared flag
blocks dispatch rather than silently modifying a retry body. Historical receipts,
signatures, attribution and invoice/milestone data remain untouched.

## Upgrade/package boundary

Version0.6.3.1-rc.1 keeps below the separate coordination0.6.4 reservation. Header,
constant, changelog and metadata assertions agree. Existing PHP7.4 source floor
is unchanged; only PHP8.3.36 execution is validated here. No broader PHP/WP support
claim is added. Inquiry schema2, adapter contract1 and configuration tree2 remain;
Ads introduces no new DDL or migration.

The final candidate ZIP/member manifest identify its exact committed source and
dependency pins. The old PREPACKAGE-INVENTORY files remain historical preparation,
not this package's manifest. Original RELEASE/SOURCE-BASELINE manifests describe
the retained baseline and are not substituted for the new candidate manifest.
The candidate package includes one plugin root, runtime, neutral example and
selected neutral docs/headers; excludes tests, diagnostics, active client.php,
secrets, local handoffs and unfinished coordination. No version tag or promotion.

Consent rc.2 requires its original receipts and two new chains/events tables on
InnoDB. Their authorized installation/normal upgrade belongs to Consent/Alma;
Ads does not create them or convert an engine. Missing storage blocks advertising.
Install/verify that dependency before Ads in a later coordinator-recorded window.
Preserve installed files/config first; do not uninstall/reactivate or force cron.
Unsigned legacy stamps, signed pre-continuity records and invalidated keys remain
blocked; no backfill, re-signing or historical repair. New choice/submission may
create new evidence. A target already running unfinished coordination requires
its owning review, not a blind file downgrade to this maintenance branch.

## Actual isolated evidence and limits

PHP8.3.36 -n:32 new dispatch assertions,104 historical/consumer assertions and
352 existing assertions =488 PASS/0 FAIL across12 suites. Metadata-sensitive
static assertions were updated to the exact candidate version; assertions were
not relaxed. Historical fixtures now carry independently signed synthetic rc.2
links/heads using the real integrity methods; the dedicated dispatch suite uses
actual rc.2 save/resolve/capture/current-eligibility algorithms and the pinned
SQL-boundary model, with a synthetic HTTP sink. Historical grant/reject sequences
on independent chains do not pretend to demonstrate same-chain withdrawal.

Nine isolated fault children inject missing/malformed/throwing current responses
while forwarding historical signature/digest verification to the hash-checked real
authority. Those are fault simulations, not native authority/SQL evidence. Each
first proves its historical input is genuinely verified. No always-successful
verifier is used. Positive dispatch cases prevent an always-deny false pass.
The focused suite covers preparation→withdrawal→blocked dispatch, re-grant old/new,
withdrawal between items/retry, fresh locking-read count, policy/DNC changes,
effective personalization, negative veto and operational All_Leads dispatch.

Run from checkout root with SEAB_CONSENT_AUTHORITY pointing to the exact rc.2
authority alongside its pinned continuity file; the dedicated suite also requires
the pinned tests/fixtures/continuity-db.php in the sibling tests directory.
Commands: `php -n tests/consent-dispatch-acceptance.php`,
`php -n tests/consent-consumer-acceptance.php`, `php -n tests/static-acceptance.php`.
No authority is vendored into Ads. Missing/wrong dependency bytes fail setup.

No installed WordPress/Fluent Forms hook, actual browser/Web Locks/cache, primary
SQL topology/locking/durability, real FluentCRM reads or full installed batch/ledger
execution is accepted here. Ambient transactions can retain Consent's shared head
lock; installed executor must demonstrate the supported autocommit/primary path.
The current read orders the decision against committed withdrawals; the later
network operation is not atomic with that read and cannot recall earlier sends.
Same-browser bearer continuity does not establish identity/cross-device withdrawal
or reconnect a lost chain. Do not claim any of these limits were eliminated.

## Compact handback through Aubrey

Consent/CRM review: verify this pinned successor's canonical original-stamp API
call, strict contract2 decision and dispatch placement. Re-grant preserves old
withdrawal barriers; review effective personalization/stale-body blocking. Existing
CRM4c448ec binding is historical, not coverage of this maintenance successor;
coordination remains separate. Keep holds/transport disabled pending installed review.

Alma coordinator: use the existing finite J0-J7 assignment, now with these rc.2
pins and final Ads package/source hashes. Record executor and authorize exact
Consent storage upgrade, scoped isolation, fixture resources and cleanup before
any application bootstrap/deployment. No whole historical isolation project is
automatically required; prove every reachable fixture delivery/cache/worker path.
No server access, fixtures, queue/export/Sheet actions or external messages occurred.
