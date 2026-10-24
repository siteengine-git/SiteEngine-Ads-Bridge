# CRM Ops / Ads Bridge Inquiry Contract — v1

Ads Bridge 0.6.3 and SiteEngine CRM Ops coordinate repeat inquiries through same-site PHP adapter functions. CRM Ops owns operational inquiry identity and automation. Ads Bridge owns immutable advertising attribution history. Neither plugin writes the other's tables directly.

## Implemented transport

The three documented same-site PHP adapter functions are implemented. Neither proposed `reconciliation_requested` nor `activated` event has an Ads Bridge listener. The event mentioned below describes the intended CRM Ops protocol; emitting it alone does not invoke Ads Bridge. CRM Ops must call the adapters explicitly from its trusted integration layer. No reconciliation REST endpoint or automatic retry worker is registered.

0.6.3 adds durable reset plans and completion checkpoints to the version/transition guards; this contract is not yet accepted for live repeat-inquiry activation. Read `INQUIRY-ACCEPTANCE-STATUS.md` for the remaining guarantees and acceptance gaps.

## Activation gate

CRM Ops runtime has not been implemented. Start New Inquiry and inquiry email sequences are planned capabilities, not existing Ads Bridge settings. Their implementation must require the following before activation or enrollment:

- Ads Bridge inquiry schema 2 is explicitly migrated and verified;
- every pre-0.6 current inquiry that may be replaced has been adopted;
- the reconciliation integration has passed prepare/activate/retry tests;
- CRM Ops holds automation until final Ads Bridge `committed` acknowledgment.

## 0. Upgrade adoption

Use for an inquiry that is already current when 0.6.0 is introduced.

Adapter: `seab_inquiry_adopt_current( $payload )`

```json
{
  "contract_version": "1",
  "event_id": "evt_<stable id>",
  "site_instance_id": "<stable site instance id>",
  "contact_id": 23,
  "inquiry_id": "inq_<UUID>",
  "head_version": 1,
  "source": {
    "type": "fluent_form",
    "reference": "form:3:entry:37",
    "form_id": 3,
    "entry_id": 37
  }
}
```

`source` may be omitted during controlled migration; Ads Bridge then freezes the contact's currently resolvable canonical `submission_id`. A nonblank but unresolvable current submission fails closed.

Success: `status=adopted`. Retry with identical event/body is idempotent.

## 1. Reconciliation requested / prepare

Adapter: `seab_inquiry_prepare_reconciliation( $payload )`

```json
{
  "contract_version": "1",
  "event_id": "evt_<reconciliation requested>",
  "site_instance_id": "<stable site instance id>",
  "contact_id": 23,
  "previous_inquiry_id": "inq_<old UUID>",
  "proposed_inquiry_id": "inq_<new UUID>",
  "expected_head_version": 3,
  "request_id": "<CRM Ops request id>",
  "source": {
    "type": "fluent_form",
    "reference": "form:3:entry:54",
    "form_id": 3,
    "entry_id": 54
  }
}
```

For `fluent_form`, `form_id` and `entry_id` are required. Ads Bridge validates the configured master form and computes `ff:{form_id}:{entry_id}`. Caller-supplied Google transaction IDs are not accepted.

A non-null `previous_inquiry_id` must already exist durably in Ads Bridge. Missing previous adoption returns `status=retry`, `reason_code=previous_inquiry_not_adopted`.

Success: `status=ready` plus `reconciliation_id`. Ready means Ads Bridge durably prepared its side; it does **not** mean the new inquiry is active.

## 2. CRM Ops activates its head

After matching `ready`, CRM Ops must perform its own atomic archive/new-head operation with a compare-and-swap on `expected_head_version`. It must still hold customer automation. Persisting/emitting a CRM Ops activation event does not invoke Ads Bridge; call `seab_inquiry_commit_activation()` explicitly after the CRM Ops head commits.

## 3. Ads Bridge activation commit / acknowledgment

Adapter: `seab_inquiry_commit_activation( $payload )`

```json
{
  "contract_version": "1",
  "event_id": "evt_<activated>",
  "site_instance_id": "<stable site instance id>",
  "reconciliation_id": "abr_<id returned by prepare>",
  "contact_id": 23,
  "previous_inquiry_id": "inq_<old UUID>",
  "proposed_inquiry_id": "inq_<new UUID>",
  "head_version": 4
}
```

Ads Bridge idempotently resets the current Ads projection, archives the old Ads inquiry row, activates the new row as `New`, and returns `status=committed`. CRM Ops may consider explicitly enabled workflow enrollment only after the matching committed acknowledgment.

Retries with the same event/body return the prior successful result. Identity/body conflicts are rejected. Transient persistence/contact failures return retry.

## Attribution invariants

- `Google_Ads_Import` remains 21 columns.
- Google transaction ID remains `ff:{form_id}:{entry_id}`.
- Qualified and Converted remain separate conversion actions and ledger sinks.
- `Inquiry ID` exists only in internal `All_Leads` (column 94).
- Immutable inquiry binding, not current contact `submission_id`, owns attribution once a contact enters inquiry mode.
- Current DNC remains a poison pill.
- Consent is resolved from the bound canonical submission.
- Archived inquiries remain eligible for unexported reached stages.

## Non-form/manual inquiries

A manual inquiry may exist operationally without a canonical Fluent Forms binding. It is not eligible for the current form-based Google export contract until/unless a supported deterministic advertising source is introduced (for example a future Call Bridge contract). Do not synthesize an `ff:` transaction ID.

## 0.6.1 projection failure responses

Activation first verifies the persisted `activating` marker, then selectively deletes explicitly supplied blank fields, sets the new canonical submission, and makes New the sole configured lifecycle tag. It re-reads the contact before proceeding to archive/activate the inquiry rows.

Additional `status=retry` reasons include `activation_state_not_persisted`, `projection_lifecycle_tags_unresolved`, `projection_lifecycle_tags_not_unique`, `projection_field_mapping_invalid`, `projection_readback_unavailable`, `projection_fields_not_persisted`, and `projection_lifecycle_not_persisted`. A projection exception also returns retry.

A failed projection can leave a partially changed current-contact view. It does not intentionally archive the previous inquiry or activate the new inquiry. Ads Bridge's lifecycle and active-inquiry sync callbacks skip that contact while an `activating` row exists, preserving the prior inquiry snapshot. CRM Ops must retain its automation hold and must not allow the contact to progress while recovery is pending. Retry uses the identical activation body. In 0.6.3, the frozen reset plan governs safe resumption as described below. No automatic rollback of the CRM projection is provided.

## 0.6.2 version, reservation and lock rules

- An existing previous inquiry must be the active Ads inquiry for that contact, and its `committed_head_version` must equal prepare's `expected_head_version`.
- Initial creation with no previous inquiry requires no existing Ads history, no active pointer and `expected_head_version=0`.
- Prepare reserves at most one `prepared`/`activating` reconciliation per contact through the supported serialized API. A different pending event returns `retry / pending_reconciliation_exists`; it is not automatically expired or discarded.
- Activation requires `head_version = expected_head_version + 1`, the same site identity used at prepare, the same previous/proposed identities, and a still-matching active previous head. These checks precede the reset.
- Versions/contact IDs must be nonnegative/positive integer values or canonical integer strings, below PHP_INT_MAX. Floats, booleans, exponent strings, negative values and overflow are rejected. Correlation strings must be nonempty and unpadded. Event IDs are limited to 80 bytes; reconciliation IDs to 64 bytes.
- The three public functions retain their names and contract version 1. Ads Bridge does not verify or write the CRM Ops head; CRM Ops must enforce its own atomic head version and durable automation hold.

Call each adapter outside any existing SQL transaction. CRM Ops must commit or roll back its own phase transaction before invoking Ads Bridge; the adapter manages its own inquiry transaction and is not a nested transaction boundary.

Adoption, prepare and activation acquire a nonblocking GET_LOCK name derived from DB_NAME, the WordPress prefix and contact ID. Active-history mirroring uses that same lock and refreshes the contact after acquiring it. Lock contention returns `retry / contact_transition_busy`. Unsupported/unavailable locks return `contact_lock_unavailable`; loss returns retry. A transition cannot reenter itself through a callback (`contact_transition_reentrant`). These locks protect cooperating Ads Bridge API operations; they do not lock arbitrary external CRM writes or create an atomic cross-plugin transaction.

### Completed event replay

A matching prepare replay in the prepared state returns `ready / already_prepared`. Once activation is in progress it returns `retry / activation_in_progress`; resume the matching activation phase. After activation completes, prepare replay returns `committed / already_activated` plus `inquiry_state` (`active` or `archived`) and `committed_head_version`.

That terminal prepare response is a historical receipt, not permission to move the CRM Ops head or enroll workflows. Only the matching activation acknowledgment, correlated to CRM Ops' committed current head and retained hold, can satisfy the activation handshake. Matching activation replays on active or archived inquiries return `committed / already_committed` without projecting/resetting again. Conflicting event bodies remain rejected.

### Persistence failures

The inquiry table must use InnoDB. Activation checks START TRANSACTION, guarded archive/new-head updates, COMMIT, ROLLBACK and the final persisted pointer/version/event state. Zero-row pointer changes do not count as success. Adoption and prepare also read back their new rows before success.

Additional retry reasons include `inquiry_table_requires_innodb`, `inquiry_transaction_start_failed`, `previous_inquiry_archive_failed`, `new_inquiry_activation_failed`, `inquiry_transaction_commit_failed`, `inquiry_transaction_rollback_failed`, `inquiry_commit_readback_failed`, `contact_lock_release_failed`, and `inquiry_connection_unusable`. Unconfirmed rollback or release closes the failed database session and refuses further transitions in that PHP request. Retry only in a new request with identical phase/event/body after the underlying failure is resolved.

The CRM projection remains outside the inquiry-table transaction. A failed/terminated activation can leave it partly or fully reset while the durable state remains activating. Caller automation and current-inquiry edits must remain held through recovery. Version 0.6.3 adds the plan/checkpoint protocol below; live CRM Ops integration acceptance is still required.


## 0.6.3 durable projection plan and recovery

The three PHP adapter names, payloads and contract version 1 are unchanged. This adds no event listener, REST endpoint, recovery worker, cancellation operation or compensation adapter.

### Explicit schema migration

Inquiry schema version 2 adds nullable `projection_plan` (longtext), `projection_plan_hash` (char(64)) and `projection_verified_at_utc` (datetime). Migration uses the existing explicit `inquiry-admin migrate` operation; it does not run on installation or traffic. Existing inquiry rows and attribution bindings are retained. Adapters require both the recorded version and the physical recovery columns with matching types/nullability; otherwise they return `retry / inquiry_schema_migration_required` before changing the contact. Doctor reports the migration requirement without blocking legacy exports.

The plan contains original inquiry-specific field values, so treat database backups with the same access controls as the inquiry table. Inspection does not print the raw plan.

### First activation

1. Validate the identity, stored head, reservation, InnoDB storage, lifecycle tags, reset mapping and required Subscriber methods under the existing contact lock.
2. Store `state=activating`, the activation identity/hash, and the reset plan/hash in one guarded update. Read back the exact saved plan before any contact write. The plan binds the intended field reset and lifecycle IDs to this inquiry/reconciliation and activation body; it also records the original relevant field values and lifecycle membership.
3. Re-read all relevant fields/tags. Apply only values that differ from the intended result and still equal their saved original. Preserve unrelated fields and non-lifecycle tags. Freshly verify the field reset before tag changes, and verify the complete projection afterwards.
4. Store/read back `projection_verified_at_utc`. Failure returns retry with the previous Ads inquiry still active.
5. Verify the contact once more, then perform the checked archive/new-head inquiry transaction. A rollback of that transaction leaves the already-saved reset checkpoint available for the next retry.

### Explicit resumption

The trusted integration layer resumes by calling `seab_inquiry_commit_activation()` again with the **identical activation event and complete body**, outside any caller SQL transaction and while the same CRM Ops head/automation hold remains in force. There is no separate recovery payload and no timer-based retry.

- With a saved completion checkpoint, the adapter checks the intended fields/lifecycle and performs **no CRM writes**. It retries only the inquiry pointer commit. Drift returns `retry / projection_checkpoint_drift`.
- With a plan but no completion checkpoint, it first validates every relevant current field/tag against the original or intended result. It sends only unfinished original-to-intended field changes and missing tag operations. Completed work is not resubmitted. If the reset was fully completed immediately before process loss, retry persists the missing checkpoint without any CRM write.
- A field with a third value or an unexpected configured lifecycle tag returns `retry / projection_recovery_conflict` before further reset writes. Configuration/tag-ID changes return `projection_plan_configuration_changed`. Plan corruption returns `projection_plan_corrupt`. Keep the hold, investigate and restore the expected source/configuration only after review; do not rotate IDs or invent a new activation event to bypass the pending inquiry.
- An exception or database-session loss still withholds acknowledgment and releases/discards locks as in 0.6.2. Resume in a new request when the connection was discarded.
- An older `activating` row without a saved plan returns `legacy_activation_projection_unknown`. Do not infer its pre-reset state from the mutable contact or clear it automatically. It requires an operator-specific recovery review; this release provides no override/cancellation operation.
- A checkpoint write/readback failure returns `projection_checkpoint_not_persisted`. No inquiry pointer switch is acknowledged; the next matching retry checks the real persisted state.

The only success acknowledgment remains the matching `status=committed`. A retry reason or a projection checkpoint alone never authorizes enrollment.

### Limits

These checks detect values outside the saved original/intended states. They cannot identify external writes that happen to reproduce either state, or make arbitrary third-party FluentCRM writes atomic with Ads Bridge. The per-contact named lock protects cooperating Ads Bridge calls; CRM Ops must coordinate all permitted lifecycle/field edits and incoming form projection while its hold is retained. An unrelated field/tag may change without blocking resumption.

Recovery currently completes the accepted activation; it does not restore the former CRM contact projection or abandon a prepared reservation. CRM Ops still needs durable phase/outbox persistence, head compare-and-swap, explicit hold/edit coordination, and installed FluentCRM/live inquiry acceptance.
