**TO:** Primary Ads Bridge Helper / Primary AB Codex

**FROM:** Codex — SiteEngine Ads Bridge — FieldEdge Integration

**SUBJECT:** Review external-outcome contract before shared implementation

Prepared handoff only; no transmission or acknowledgment claimed.

The isolated foundation changes no shared runtime. Existing contract-1 inquiry
adapters adopt/prepare/activate inquiry heads; none accepts external business
outcomes. Reusing them for an invoice would violate their purpose and protections.

## Proposed Ads-owned callable contract — not implemented

`external_outcome_capability()` should report supported draft/version, destination,
provider/source/stage support and storage compatibility. Discovery grants no writes.

`external_outcome_submit(array $request): array` should accept a trusted same-site
caller outside any caller transaction. Request must bind contract/destination,
stable operation ID, provider/company, source event identity/exact-body digest,
fetched snapshot digest/provenance, expected outcome revision and economic key.
The referenced evidence includes explicit inquiry/lead/job/invoice association and
reviewed invoice-value approval. Core reads authoritative evidence and inquiry
source itself; untrusted request booleans cannot establish association or consent.
Timestamp/amount members use the foundation's validated representation. Do not
accept a caller-chosen Google transaction ID or overwrite a CRM current head.

Response proposal: status=recorded|held|retry|conflict, fixed reason_code, durable
receipt_id, actual outcome_revision and correlation/body digest. Recorded means
outcome evidence persisted, **not exported or eligible**. Held is a durable
diagnosable business/consent/ambiguity result. Retry requires identical operation
and complete body. Conflict means identity/body or revision mismatch. Receipts need
safe exact readback after uncertain commit; no exception text/PII in public results.
An explicit review operation must govern reevaluation after consent/evidence changes;
a replay cannot silently become permission to export.

## Storage and ownership decisions required

* Ads-owned relationship/evidence and append-only outcome revisions, scoped by
  destination/provider/company. Separate tenant mapping, association revisions,
  value-policy approval and fetched snapshots. Do not edit CRM tables or repurpose
  contact custom fields as a lifetime revenue ledger.
* Durable inbox operations with uniqueness for event identity/body; outcome CAS;
  unique economic event/stage and inquiry/action export linkage. Decide a single
  transaction/receipt boundary under existing destination/contact locks, durable
  pending configuration checks, pinned session and recovery rules.
* Source migrations must be explicit, additive and separately owned/reviewed.
  No schema change implemented here. No foreign plugin hooks, REST routes, callbacks,
  background jobs or version changes required to review the pure proposal.
* Archived outcomes must never trigger normal current-contact lifecycle projection.
  If a CRM lifecycle view update is needed, CRM owner must accept a narrow conditional
  projection contract. Do not copy its operational application into this connector.
* Existing Qualified/Converted sinks, 21/94 columns, canonical ff identity and
  authoritative Consent/current-DNC checks remain unchanged. Define monetary basis,
  stage evidence, multiple-job aggregation, currency support and correction handling
  before a candidate can reach the exporter. Completed job alone is not value proof.
* Phone-call source support, payment/refund adjustments, multiple invoices per job,
  partner throttle infrastructure and provider subscription ownership remain separate
  bounded decisions. No parallel exporter or invented adjustment API.

## Required acceptance for that owner implementation

Use installed isolated WordPress dependencies and private synthetic records. Prove
same operation/body replay after crash, conflict refusal, two-executor contention,
lost commit readback, atomic inbox/receipt completion and monotonic revision guards.
Prove frozen archived/current inquiry separation across repeated customers/jobs;
fresh canonical consent/withdrawal/current DNC refusal; unfinished configuration or
inquiry operations hold submission; no CRM head transition or unrelated projection.
Prove multiple job/invoice/payment notices cannot duplicate an inquiry/action
conversion and revised/canceled/refunded evidence cannot rewrite accepted history.
Retain disabled real delivery and exports. Existing local CoreDouble proves none of
these installed/durable guarantees.

Recommended next assignment: primary AB owner reviews/narrows this outcome interface
and value/aggregation policy; FieldEdge owner then implements a separate persisted
contract fixture against the accepted interface. Provider sandbox and field-level
read scope must be approved before any authenticated API verification.
