# Climatrol running synthetic connector — November 6, 2026

Target: reproducible integrated demonstration Monday, November 9. This deliverable
implements the local connector path. AB owns installation, durable storage/reviews,
Consent/DNC decisions, test worksheet and final integration. Local fixture passes
are never installed WordPress or provider API acceptance.

## Local demonstration

Run from any PowerShell directory using the existing local PHP runtime:

```powershell
$php = 'D:\SiteEngine\Plugins\.PLUGIN — SiteEngine Ads-Bridge\local-test-runtime\php-8.3.36\php.exe'
& $php -n 'D:\SiteEngine\Plugins\.PLUGIN — SiteEngine Ads-Bridge\fieldedge\tools\fieldedge\climatrol-demo.php'
& $php -n 'D:\SiteEngine\Plugins\.PLUGIN — SiteEngine Ads-Bridge\fieldedge\tests\fieldedge-foundation-acceptance.php'
& $php -n 'D:\SiteEngine\Plugins\.PLUGIN — SiteEngine Ads-Bridge\fieldedge\tests\fieldedge-climatrol-acceptance.php'
```

Expected demonstration JSON:

| Scenario | Attribution from fixture receipt | Submission | Value evidence |
| --- | --- | --- | --- |
| attributed | Attributed | recorded / outcome_persisted | USD 1250.00 |
| unattributed | Unattributed | held / association_missing | USD 1250.00, not attributed revenue |
| review | Needs Review | held / association_ambiguous | USD 1250.00, not attributed revenue |

Each run uses one form inquiry, one FieldEdge customer/work order/invoice and one
explicit synthetic invoice-value approval. Three distinct synthetic invoice notice
IDs exercise the association cases; no extra jobs/invoices are fabricated. The
same contact evidence is present in all cases; it never determines attribution.
All provider IDs, source timestamps and body/snapshot hashes survive normalization.
Invoice-value evidence remains separate even when attribution is unresolved.

The runner resends the first exact body: `replay.status=recorded`, `same_receipt=true`.
Changing its amount while retaining operation identity yields
`conflict/operation_identity_reused`. The fixture double reports exactly three
receipt inserts total; replay/conflict creates none. This is an in-memory contract
fixture, not evidence of durable idempotency. Exit zero and JSON output constitute
a runnable local demonstration; no WordPress/network/Sheet effect occurs.

Expected tests: foundation 62 PASS, Climatrol 51 PASS, zero failures. The latter
includes absent/wrong/corrupt/throwing receipt reads, exact-byte conflict, event
identity reuse, uncertain response readback, and strict candidate contract binding.

## Binding to the supplied AB contract

The exact candidate handoff was received from **Implement Climatrol outcome API**.
Its source contract is `source/docs/EXTERNAL-OUTCOME-CONTRACT.md` in the canonical
repository, contract version `1`, pinned at
`68cc49bd85945567afc92f7621e3d696d2cfab68` on
`codex/climatrol-outcome-20261106`. This replaces
the earlier proposed method names in CORE-HANDOFF; that historical proposal is
retained for provenance, not an interface to invoke. The pin remains an unaccepted
integration candidate pending the dedicated real test worksheet.

The connector calls only these supplied surfaces:

```php
seab_external_outcome_capability(): array
seab_external_outcome_submit(string $body): array
seab_external_outcome_read(string $receipt_id): ?array
```

SubmissionAdapter prepares exactly the supplied 18 string fields; FE work_order_id
maps to job_id. It carries the original event SHA256, the Foundation work/invoice
snapshot digest, original completion/invoice timestamps, explicit inquiry subject,
review evidence ID and `synthetic-usd-demo-1`. No attribution/consent boolean or
caller-selected Google transaction ID is submitted. A requested inquiry subject
does not establish a relationship. Only AB's separately stored operator review
and immutable inquiry can establish that association.

Prepare once and retain the **exact** UTF-8 body before calling. A retry uses that
same string, operation/event IDs, destination and evidence identity. No automatic
retry, review creation, migration, durable connector ledger or exporter exists.
Changing whitespace/key order/value under the same operation conflicts. Do not
rotate the operation or event to bypass an uncertain result.

Recorded/held responses require `seab_external_outcome_read()` to return the exact
body/digest and a matching stored response. That receipt governs status/association,
including after an uncertain reply. Missing/failed/mismatched readback yields retry,
not success. Conflict remains conflict; it cannot adopt the previous operation's
receipt. The adapter keeps export_eligible=false. Attributed is independent of
advertising consent, DNC, eligibility and actual export.

Only hashes/references for some richer local evidence fit contract 1. The original
event time, lead identity and approval reference remain in the normalized packet;
its packet SHA256 can be used by AB's operator review provenance. No unknown fields
are added to the shared request schema. Provider entitlement and production value
policy remain outside this synthetic assignment.

## AB-owned installed entry point (not executed by FE)

`tools/fieldedge/climatrol-wordpress-demo.php` is a `wp eval-file` entry point. Place
the connector, tests/support fixtures and tool together with their relative layout
in the isolated integration candidate. It requires an authenticated administrator
CLI context and six explicit arguments:

```text
stable-operation reviewed-evidence actual-inquiry actual-form actual-entry exact-destination
```

AB must create/verify its isolated schema, actual form inquiry and independent
operator review first. The tool does none of those operations. Supply the actual
inquiry/form/entry IDs from that installation; the destination must exactly match
discovery or the tool refuses before submission. Keep resulting body/receipt in
AB's private execution records. Use the existing shared Invoke-Site-Wp runner and
the actual isolated profile/remote path that AB records; no new connection script.

The actual `body` and adapter `result` are printed for controlled synthetic evidence.
AB's review must bind the twelve outcome members from that body and use packet
provenance. The tool submits through AB and reads its receipt. It never creates a
review or enables/executes any export. Repeat with the same arguments and frozen
component pins only; for an uncertain retry use the retained original body directly.

AB established a fresh isolated installation and private identity/profile under the
parent project's `climatrol-demo-20261106` directory. Its operational DB/prefix and
server paths are intentionally not copied into public source. FE has not executed
an installed test there. AB executed the actual installed FE normalization/submission
path and fresh-process replay; FE independently inspected its private native output
files. Both returned recorded/Attributed with matching durable receipt readback.
This is separate from the local API double. Installed packet SHA256:
`ccbd1a3f90beb2d006c2ee539e80893d32291484d9422696ff13aeaa57194641`.

The pinned AB driver `tools/outcome-demo-acceptance.php` has modes `fixture-fe`,
`fe-outcome` and `fe-readback`. These are AB-owned installation operations: retain
the original fixture and body, and use readback instead of resaving setup. AB also
reported native contention/uncertain-write tests; they are not counted as FE local
assertions. No real Sheet append has occurred. A separate authority preview built
21 columns with original form identity, synthetic USD1250.00 and personalization
Denied; receipt export_eligible remained false. Service-account/test-worksheet
setup and final real worksheet verification remain AB's concrete dependencies.

## Source integration ownership

Incorporate only connector-specific paths and this integration documentation;
never merge older shared STATUS.md, AGENTS.md or release history into AB's source.
Keep the original foundation branch/history and approved worktree. A transition
to accepted 0.8.1 must use a separate preserved branch in that isolated worktree,
transplanting only FE paths, not replacing shared current source with 0.8.0 files.
No core loader/schema/lifecycle/settings change belongs to the FE changeset.
