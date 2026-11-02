# Ads shared-configuration coordination — contract 1

Successor 0.6.4-rc.1, UNACCEPTED until installed/native review. Integrates retained
4c448ec48bda60bcbba87dc97255fe215502b9a3 into Consent-repaired source1eec02e,
rather than replacing that runtime. Inquiry adapters remain contract1/schema2;
configuration tree2; coordination storage1. Preserves capability/inspection/batch/
step/finish/schema_sql and same-session/interruption semantics below. New explicit
administrative migration is additive-only; never runs on activation or traffic.

Consumer reviewed: CRM Configuration docs/PROTECTED-INSTALLER.md (local0.3.2),
tools/coordinated_phase.php and tools/local_protected_session.php. The consumer
still pins old implementation69bc74d9cdf81384415083f2445ffe20ea8c4ab39b4e6a6426e760f0a5e7576d.
CRM's owner must review/bind this successor's exact new implementation/package hashes
and establish its installed destination profile. No CRM source was edited, and Ads
packaging does not complete CRM installation. See docs/COORDINATION-SUCCESSOR.md.

## Callable surface

```php
seab_configuration_capability(): array
seab_configuration_inspect_pending(array $request): array
seab_configuration_with_batch(array $request, callable $callback): array
seab_configuration_schema_sql(): string // DDL text only, no SQL execution
seab_configuration_migrate(bool $apply=false): array // additional explicit admin path

// Passed only to the protected callback; these are not portable lock tokens.
SiteEngine_Ads_Bridge_Configuration_Batch::step(
    string $id, callable $write, callable $readback
): void
SiteEngine_Ads_Bridge_Configuration_Batch::finish(callable $readback_and_checkpoint): void
SiteEngine_Ads_Bridge_Configuration_Batch::finished(): bool
```

Capability fields: `contract_version="1"`, `destination` (opaque 64-hex hash),
`scope="destination_shared_configuration"`, `inquiry_schema="2"`,
`coordination_schema="1"`, `session_model="single_php_process_single_wpdb_session"`,
`max_steps=64`. Discovery is structural, not a readiness/authorization result.
Destination hashes the ordered DB_HOST, DB_NAME and WordPress table prefix.
Changing a host alias changes this identity even if it routes to the same server;
review/rebind the target explicitly. Clones need their own reviewed target binding.
No raw database/server/prefix name is returned in inspection/batch results.

Inspection request must contain the exact capability's `contract_version`,
`destination`, `scope`, `inquiry_schema` and `coordination_schema`. Result includes the capability fields plus:

| Member | Meaning |
|---|---|
| state=none, complete=true | All required reads succeeded, supported physical schema, full destination-wide inquiry/configuration coverage, no pending reservation observed under protection. |
| state=present, complete=true | Prepared/activating inquiry or reserved/inflight configuration batch exists; block installation. |
| state=unknown, complete=false | Incomplete/unsupported/failed/busy/mismatched inspection or unconfirmed release. Block installation. |
| write_permission=false | Always false, including a successful empty result. |
| reason_code | Fixed safe implementation reason, never a raw error/row/payload. |

Required reads include physical InnoDB table/column checks, primary-slot index,
direct schema-option SQL read (not an object-cache option), all persisted inquiry
states/contracts grouped across the whole table, and durable coordination state.
All canonical inquiry DDL columns/types/nullability are checked. State groups must
be active/archived/prepared/activating with contract 1; null/unknown/future states
or schema versions fail closed. The coordination journal must pass structural,
binding, state/receipt/history consistency checks. Missing storage is unknown,
never a safe fresh install. Each read's error/null/type outcome is captured before
another query can clear last_error. Results contain no inquiry counts/rows,
contact identifiers, journal bodies, source attribution or projection plans.

This replaces **only** the consumer's use of ambiguous zero in status_summary().
The legacy display method remains unchanged; do not use it for write authorization.

## Actual protection and session requirements

One nonblocking `GET_LOCK(name,0)` covers the entire destination; name is
`seab_c_` plus the first 48 hex characters of the destination identity. Different
WordPress prefixes have different scopes. Lock order is strictly:

1. Destination configuration/reconciliation lock.
2. Existing per-contact `seab_i_...` lock, for inquiry APIs/sync only.
3. Existing checked inquiry transaction, for pointer activation only.

Acquire before pending inspection; retain through dependency validation, every
save, readback and durable checkpoint. Neither a timestamp nor the standalone
inspection authorizes a later unprotected save. Global acquisition is exclusive
even for different contacts; this intentionally serializes cooperating inquiry
operations during a finite configuration phase. Busy returns blocked/unknown;
existing inquiry adapters return retry with coordination reason codes. Sync
returns false and performs no Ads history write. No automatic retry is scheduled.

Calls must start outside SQL transactions, with autocommit=1. The authoritative
server must be writable with innodb_flush_log_at_trx_commit=1; otherwise reads
return unknown without changing those settings. Assume truthful durable storage
and a single authoritative writer server; replication/failover or multi-primary
coordination is outside contract 1. Configuration
boundaries also enforce those conditions throughout the callback. Existing inquiry
transactions remain inside their original contact/global lock scope. Release
contact before destination. Nested batches, transitions and inspection within an
owned scope are refused, not recursively acquired. The callback uses its handle,
not the public inspection API. Hooks triggered by a vendor save cannot reenter
reconciliation/sync while configuration owns protection.

The implementation pins **the same database object/session**, so vendor references
to it remain valid. For stock exact-class `wpdb` + PHP >=8.3 + mysqli, it temporarily
sets protected reconnect_retries=0 and suppresses DB error printing, restoring them
only after a confirmed release. This prevents stock wpdb's error-2006 path from
reconnecting and replaying a failed mutation without its named lock. Every boundary
checks object, prefix/destination, connection ID, current database, named-lock owner
and continued retry suppression. Loss/replacement/reconnect/unconfirmed acquire or
release poisons the request, closes involved sessions and refuses subsequent work.
Stock wpdb is also marked not-ready; it is not silently repaired/re-enabled.

Trusted custom/test drivers can implement
`SiteEngine_Ads_Bridge_Coordination_Driver::seab_coordination_pin_session()`;
it must disable all automatic reconnect/replay and return a restoration closure.
The isolated SQL driver uses PDO without reconnect/replay. This extension is a
driver implementation obligation, not a JSON capability or arbitrary boolean
that enables a production transport. Unrecognized drivers fail closed.

SQL reads/persistence use MariaDB `SET STATEMENT max_statement_time=2 FOR ...`.
GET_LOCK wait is zero. Unsupported syntax/driver/read permissions produce unknown.
This is a server execution deadline, not a guaranteed client/network wall-clock
deadline; caller/runtime must bound its finite request and vendor operations.
The intended native target is MariaDB 10.11; native execution is still unproved.
WordPress reconnect reference: [wpdb source](https://github.com/WordPress/WordPress/blob/master/wp-includes/class-wpdb.php).
MariaDB references: [GET_LOCK](https://mariadb.com/docs/server/reference/sql-functions/secondary-functions/miscellaneous-functions/get_lock),
[SET STATEMENT](https://mariadb.com/docs/server/reference/sql-statements/administrative-sql-statements/set-commands/set-statement).

**One PHP process and one pinned session are mandatory for the entire phase.**
The current CRM controller's separate WP-CLI invocations cannot carry protection
between saves. Do not serialize the handle, retain an inspection result as a token,
or hold a lock in one session while ORM saves use another. Current production
transport remains disabled. CRM's owner must consolidate a finite phase inside a
single PHP evaluation and prove that all involved ORM/global-field operations and
hooks use that same pinned wpdb; a separate vendor connection is outside this
guarantee and blocks write enablement. No new transport is implemented here.

## Batch request and caller obligations

In addition to the five identity/version members, request:

```php
$request = seab_configuration_capability() + [
    'operation_id' => 'cfg-stable-operation-uuid',
    'plan_hash' => $exact_approved_plan_sha256,
    'steps' => [
        'field.example' => ['kind' => 'create', 'intent_hash' => $exact_step_sha256],
        // kind: create | update | adopt | reuse; at most 64 approved steps
    ],
];
```

IDs are 1..80 ASCII letters/digits/underscore/dot/colon/hyphen, not purely numeric.
Hashes are lowercase 64-hex. Step keys are canonicalized; kind/intent_hash order
does not matter. IDs/hashes are approved configuration identities, never customer
data. The request is bound to destination/capability/scope, operation ID, full plan
hash and normalized manifest. Change any pending binding and entry is blocked.

After lock/schema/pending verification, a `reserved` journal is durably written
and read back **before caller dependency checks or vendor writes**. Callback must
perform fresh complete inventory, exact-plan/dependency/side-effect/quarantine
checks under protection before its first step. It must preserve CRM holds and
disabled lifecycle/export/email settings. This library does not inspect vendor
definitions, authorize their content, or replace CRM's approval/journal model.

```php
$result = seab_configuration_with_batch($request, function ($batch) use ($request) {
    // Consumer validates current full inventory, exact approved plan and gates.
    $batch->step('field.example', function () {
        // One exact approved native operation; same pinned wpdb, no reconnect.
        // Record native identity/result in CRM-owned durable evidence.
    }, function () {
        // Independently reread identity, intended definition, preserved members,
        // unrelated configuration and disabled-state/side-effect requirements.
        // Only when proven: ['state'=>'verified','hash'=>actual_receipt_sha256].
        // For recovery of UPDATE only: ['state'=>'before'] is allowed after
        // proving exact unchanged-before state and safe exact resubmission.
        // Otherwise return ['state'=>'unknown']; never guess a create identity.
    });
    $batch->finish(function () {
        // Fresh full readback + durable CRM checkpoint MUST finish here,
        // while protection is still held. Return actual final receipt SHA-256.
        // Do not postpone the checkpoint until a later Python/WP-CLI call.
    });
    // Return immediately; no direct unguarded writes after finish.
});
```

Write and readback closures take no arguments; the consumer retains the exact
approved operation body/identity. The library writes intent before invoking write,
checks ownership before/after callbacks, and checkpoints only validated hash-shaped
readback evidence. `verified`/`before` are trusted consumer attestations: their
vendor-specific truth must be tested by CRM's owning adapter. A matching title or
unique-looking created object is **not** proof of an uncertain create outcome.
Native create identity markers/side-effect proof remain unestablished.

Final result is capability plus `status=complete|blocked`, fixed reason_code.
Only complete confirms final readback/checkpoint and confirmed release in that
call; it grants no workflow enrollment, contact-hold release or environment change.
Callback return without finish is blocked and retains the reservation.

## Durable crash recovery

New table `{prefix}se_ads_configuration_guard`: slot=1 primary singleton,
schema_version, opaque destination, state, journal LONGTEXT. State is reserved,
inflight or complete. Journal contains binding/plan hashes, stable operation ID,
approved step-kind/intent hashes, current inflight step, verified receipt hashes,
final checkpoint hash and bounded retired-operation bindings. It contains no
configuration bodies, credentials, contacts or inquiry plans. Maximum encoded
journal 64 KiB, 64 steps, 128 retired operation IDs; capacity exhaustion blocks,
never silently evicts/recycles history. No operator-clearing/cancellation API.

1. On interruption retain the exact request/step identities, caller journal,
   approval and quarantine/holds. Do not delete the Ads guard row or rotate IDs.
2. Terminate/confirm the former writer is no longer in flight using separately
   authorized operational evidence; no active stale process/vendor callback may
   be presumed safe merely from elapsed time. Re-enter with_batch in a new PHP
   request/session and the identical request. Reacquire destination protection,
   recheck physical schema and pending inquiries and validate binding/journal.
3. Reserved-before-step may continue after fresh dependency/plan checks. An
   inflight step must be retried by the **same step ID** and manifest. Another
   step cannot bypass it. Readback happens first; no mutation occurs beforehand.
4. Proven intended outcome stores a receipt without resaving. An exact update
   with independently proven unchanged-before state may resubmit that same update,
   then must read back. Unknown create/adopt/reuse outcomes never resubmit. Third
   state, receipt drift, missing proof, different intent or raw exception blocks.
5. Previously checkpointed steps repeat readback and must match their receipt;
   no write is repeated. Finish requires receipts for the entire manifest, no
   inflight step, final complete readback and durable consumer checkpoint under
   protection. Only then does Ads persist/read back complete and release the lock.
6. Complete replay of the current operation verifies readback without new saves.
   Starting another approved operation retires the former binding; later replay
   of that retired operation is refused rather than becoming a new create. CRM
   retains its own long-term completion receipts. No operation ID reuse.

An uncertain save remains inflight after session loss: reconciliation cannot
resume simply because GET_LOCK is free. Unconfirmed persistence/acquisition/release
is not success. No cross-vendor transaction, rollback, automatic compensation,
exactly-once vendor hook side effects or automatic recovery worker is claimed.

## Participation and excluded writers

| Path | Participation / installation restriction |
|---|---|
| Inquiry adopt/prepare/activation, all same-body retries/projection recovery | Destination guard before contact lock; existing mutation assertions now verify both protections. Contract-1 payloads and frozen plans unchanged. |
| Active-inquiry tag/custom-data synchronization | Same destination/contact ordering; contention/pending configuration causes no history mutation. Quarantine/CRM holds must prevent edits/feeds during batch; no missed-sync recovery worker added. |
| Private unused legacy snapshot helper | No public entry/call site; now also asserts contact/destination ownership immediately before insert. |
| Ads settings save/delete and admin save/import | **Excluded unchanged writers** (`Settings::save/delete`, `Admin::handle_save/handle_import`); installation must prohibit these routes and direct option edits for the full protected/recovery window. They are not falsely described as fenced. |
| Ads CLI provision-fields --apply | **Excluded unchanged global-field writer**; prohibit during installation/recovery. Its whole-collection save can conflict; CRM must not invoke it inside the batch as an unreviewed side effect. |
| Inquiry migration and ledger/schema maintenance | Explicit administrative provisioning, excluded; no concurrent DDL/migration/repair while coordination is used. Returned new DDL is not applied by existing inquiry migration. |
| Ads lifecycle compliance/front-end runtime | Not a configuration installer. Ordinary contact projection/browser delivery is outside this exclusion guarantee and must remain quarantined/held; no workflow behavior changed. |
| Export/ledger/Sheets paths | Unchanged and outside vendor configuration coordination; retain existing disabled/quarantined execution. No export/API/Sheet contract change. |
| Base shared facts, external/local PHP overrides, vendor editors, other plugins, direct SQL, alternate DB clients | Noncooperating writers; operational exclusive-writer restriction is mandatory. No guarantee over arbitrary editors/SQL or separate ORM sessions. |

If an excluded owner cannot establish the restriction, installation remains blocked;
do not compensate by weakening pending/coordination checks. Preserve CRM holds and
environment quarantine independently; this interface never releases either.

## Explicit additive migration / release gates

`seab_configuration_schema_sql()` still returns DDL only. Additional administrative
`seab_configuration_migrate(bool $apply=false)` and CLI
`se-ads configuration-admin migrate [--apply]` preview by default; no --dry-run flag.
`configuration-admin inspect` calls protected inspection, not write authorization.
Migration authenticates/pins the same supported authoritative session, takes the
destination lock and validates inquiry schema2/direct option/states before DDL.
It creates ONLY an absent guard using canonical InnoDB DDL. Existing physical schema
and journal must already validate; there is NO ALTER, DROP, TRUNCATE, reset, seed,
option update or repair. Pending inquiry rows are preserved; they still block CRM
batches. An existing reserved/inflight guard refuses migration and requires exact
operation recovery. The new guard starts empty, never with a forged complete row.

A lost CREATE reply or failed physical proof is blocked with creation_state=unknown;
`created=false` is NOT absence proof. Preserve table/evidence/holds and obtain fresh
native readback before an explicitly reviewed continuation. Never reuse a poisoned
request/session. A valid existing table verifies without rewriting rows; unconfirmed
release never returns applied success. No old active/inflight operation is cleared.

PHP package minimum is8.3, matching the stock wpdb/mysqli protected-session profile.
MariaDB10.11 statement deadlines/primary/autocommit/flush-at-commit requirements are
unchanged; unsupported conditions block rather than changing server settings.
Native installation/migration/SQL/concurrency checks belong to Alma's assigned slot
and were NOT RUN locally. Fresh local source/bootstrap/inquiry/coordination/Consent
checks are documented in successor/RESULTS.json; SQL/OS/WordPress/transport boundaries
are synthetic. Retained native test source is not native acceptance; do not point
its dedicated-database runner at the WordPress database or provision a new database
from this assignment. No Windows database investigation was restarted.

Stable designation follows installed verification. No deployment, site settings or
schema write, fixture, transport enablement, enrollment, customer send or hold release
was performed by the local successor work. Old archives/manifests remain preserved.
