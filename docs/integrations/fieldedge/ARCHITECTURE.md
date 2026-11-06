# FieldEdge foundation — draft 1, dormant

The original foundation branch was based on accepted Ads0.8.0. The current
Climatrol successor branch is based directly on accepted Ads0.8.1; only FE paths
were transplanted in the same worktree, preserving the old branch. See
CLIMATROL-RUNBOOK.md for the running workflow and supplied AB contract binding.
No version/header, loader, existing contract, schema, settings, export or CRM source
changes are made by the connector. The original design below remains context.

`includes/connectors/fieldedge/Foundation.php` implements pure signature/envelope
validation, explicit identity/value normalization and revision classification.
`Retry.php` classifies failures without making requests or scheduling activity.
Neither file is loaded by the plugin. No activation setting or runtime path exists.
No general provider framework, OAuth transport, public endpoint or worker exists.

## Identity and economic outcome

Provider identities are scoped by reviewed FieldEdge company/tenant and destination.
Relationships are records, never a single overwritten CRM contact field:

* inquiry -> lead: explicit local association with immutable evidence ID;
* lead -> customer/location -> work order: verified links, not contact proximity;
* work order -> invoice: explicit invoiceId from fetched record and reviewed link;
* invoice revision -> approved value: approval ID/policy plus exact invoice digest;
* inquiry -> canonical advertising source: resolved exclusively by Ads core.

The draft link contains tenant_id, inquiry_id, lead_id, customer_id, work_order_id,
invoice_id, basis=reviewed_explicit and evidence_id. These are **local proposal
members**, not invented FieldEdge response fields. The live resolver must read
persisted reviewed evidence itself; an incoming webhook cannot supply this link.
Missing, multiple or customer/invoice-mismatched links hold the outcome. Same email,
phone or customer and 'most recent inquiry' never establish job attribution.
Billing customer is distinct from service customer; properties need explicit stable
provider identities before implementation. No address hash is an invented property ID.

A normalized candidate preserves provider/tenant, customer/work/invoice IDs, lead
and inquiry IDs, source snapshot digest, original completion/invoice timestamps,
association evidence, exact approved decimal/currency and approval/policy IDs.
No PII, raw financial detail or provider marketing opt-in passes through the model.
Call inquiries need a reviewed deterministic Call Bridge source; no fabricated ff ID.

The draft converted economic key hashes provider + tenant + stage + work-order ID.
Completion, invoice and future payment notices must reconcile to one economic event,
not three conversions. This work-order grouping is proposed, not accepted policy:
multiple invoices per job, partial payments and multiple jobs per inquiry require
explicit value/aggregation policy. Existing Ads allows one transaction per inquiry
per action; CoreDouble holds a second job for the same inquiry/action. It does not
invent a new Google transaction ID or silently sum unrelated jobs.

The value approval is separately supplied trusted local evidence, bound to tenant,
invoice ID and exact invoice digest, with amount, currency, approval_id and policy_id.
No actual client value definition exists. Foundation accepts only positive canonical
two-decimal strings for a candidate; float, exponent and negative values reject.
This is a deliberately narrow proposed convention, not universal currency support.
Zero is held; refunds/negative adjustments need a separate accepted correction path.
Approved amount can differ from gross invoice total under a future client policy;
the foundation does not decide tax, discounts, payment basis or currency itself.
Real JSON numeric money needs a lossless decoder before transport is implemented.

## Revisions and eligibility

Snapshots are SHA256 over sorted object members with list order preserved. This is
local comparison evidence, not a provider ETag or monotonic version. Record fetch
time must remain separate from business/event time. Original offsets and up to seven
fractional timestamp digits are retained; invalid/missing-zone dates reject.

Any changed economic outcome is revision_review, including reassociation, cancellation,
reduced value or changed completion evidence. Prior versions and the original conversion
remain. No new conversion, historical overwrite, correction export or head move is
authorized by revision detection. Without reliable revision ordering a changed/older
snapshot is held and re-fetched under the future contract, never last-write-wins.

All real candidates have export_eligible=false and core_review_required. Foundation
never calls the three inquiry adapters: they orchestrate inquiry transitions, not
external outcomes. Core must independently resolve frozen inquiry binding, canonical
Consent receipts/current withdrawal, current DNC, reached stage, pending holds and
existing ledger state. Provider consent/marketing flags cannot establish permission.
Archived inquiry evidence must never reset/project over an unrelated current inquiry.

## Future boundaries and durable processing (not implemented)

1. A secret-safe provider client owns OAuth/expiry, fixed HTTPS origin, strict TLS,
   redirects disabled, response size/time bounds and read-only endpoint allowlist.
   Do not log tokens, raw responses, HMAC secrets, financial details or contact data.
2. A verified receiver preserves bounded exact bytes, validates HMAC before decode,
   binds company to reviewed target/destination and inserts an atomic inbox record.
   Only supported event families are dispatched. Foundation currently supports
   work-order/invoice/customer events. Lead/job/quote envelopes remain deferred
   until their record linkage is verified. No subscription is registered here.
3. Durable inbox key: destination/provider/company/event ID, exact body SHA256.
   Same ID/body is replay; different body conflicts. Unique constraints and checked
   receipt persistence are mandatory before acknowledgment. Bounded raw evidence,
   if retained, needs private access/retention policy; public diagnostics use codes.
4. Fetch immutable record snapshots through one partner-wide budget shared by tenants
   and processes. Store GET path/record ID, original event time, fetched time, digest
   and retry state separately. Recheck association/value decisions under protection.
5. Ads-owned submission accepts exact stable operation/body with CAS revision and
   correlation. Inbox completion references the persisted core receipt. A crash
   after core commit must recover by identical submission/readback, never new ID.
   One local DB atomicity scope must be agreed; no cross-site atomicity is claimed.
6. Reconciliation checkpoint binds destination, tenant, endpoint/filter/sort/page,
   scan generation, last durable receipt and bounded retry budget. Advance only
   after all page records are persisted. Customer updatedAt overlap + ID dedupe is
   possible; no unsupported work/invoice updatedAfter filter is invented. Offset
   scans can miss records during concurrent changes; stable paging/backfill design
   requires provider evidence before a completeness claim.

Retry classifier is GET-only: 429 waits at least Retry-After, network/5xx defer with
bounded exponential delay, quota-specific 403 defers to next UTC hour, other 401/403/
404/validation failures hold. Five attempts maximum; malformed/unreasonable delay
requires review. Future dispatcher adds jitter without shortening not-before and
atomically enforces the shared minute/hour budget. No automatic retry loop exists.

## Verification boundary

`tests/fieldedge-foundation-acceptance.php` uses synthetic JSON and an in-memory
CoreDouble. Its consent, persistence, ledger and operation receipts are **test
doubles**, not WordPress integration. It proves local proposed behavior only.
No restart durability, race safety, provider API behavior or installed acceptance
is established. Real schema/transport/consent/export changes remain owner work.
