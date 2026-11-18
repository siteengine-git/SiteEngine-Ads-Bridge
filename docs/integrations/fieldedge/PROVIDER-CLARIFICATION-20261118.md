# FieldEdge provider clarification — 2026-11-18

**Source:** Written response from FieldEdge Integrations Team to SiteEngine on November 18, 2026. This is provider-supplied contract clarification, not an independently observed lead lifecycle or approval for Marketplace distribution. Raw email retained outside this public source; no credentials or customer payloads.

## Confirmed API limits and semantics

| Subject | Provider confirmation | Implementation consequence |
| --- | --- | --- |
| Lead to work order | No supported GET or webhook returns a work-order ID for a lead | Stop planning for a provider-derived leadId-to-workOrderId join. Customer/email equality does not attribute any specific job. |
| `trackingId` | Optional; **must be a GUID**. Stored as the lead ID and returned by `GET /v1/leads/{leadId}`. Duplicate `POST` with the same GUID returns **400 "Tracking ID already exists"**, without returning the original lead | Reserve one GUID per original inquiry **durably before any POST**; bind tenant/inquiry/GUID immutably. Never create a new GUID on retry. On known duplicate 400, hold and use an independently authenticated GET by the stored GUID; do not call the duplicate a successful creation without a matching GET. |
| Lead statuses | `Accepted`, `Rejected`, `QuoteAccepted`, `QuoteRejected`, `QuoteExpired`, `JobCanceled`, `JobCompleted`, `JobFinalized` via `GET /v1/leads/{leadId}` | Use the known lead GUID to poll lead-specific operational outcome. This may support lead-stage conversion **without** work-order ID, after verifying the transitions and obtaining Climatrol's Won/Lost policy. Unknown status values remain held for review. |
| Quote | `GET /v1/quotes/{quoteId}`; provider states `quoteId` equals the work-order ID | A known work-order ID can retrieve the quote. Does **not** establish any lead-to-work-order link. |
| Assignment | Includes `completed` and `canceled` | Operational work-order context only; cannot attribute by customer alone. |
| Invoice | `GET /v1/invoices/{invoiceId}`; provider states `invoiceId` equals the work-order ID; fields `total`, `balance`, `payStatus`. No payment transaction endpoint | Existing code continues to use the explicitly returned work-order `invoiceId`, not a guessed ID. This claim matched the three Climatrol work-order snapshots in the 2026-11-13 private provider pass, but the join still does not identify the originating lead. `payStatus` is not independent proof of an underlying payment transaction. |
| API transport | HTTPS; `Authorization: Bearer <JWT>`. Requests that reach the API accept `X-Correlation-Id` (printable ASCII, up to 128 characters), echoed in response; errors have `requestId` | Pre-HTTP TLS/transport failure gives no provider request ID. Use locally bounded diagnostics. Do not publish JWTs, contact data or internal vendor diagnostics. Correlation IDs can help once an HTTP request arrives. |
| Partnership | SiteEngine may apply for partner/Marketplace approval through the form below; applicant must sign and submit and undergo vetting | Start partner application in parallel, not as a dependency for the already functioning Climatrol DEV connector. No partner status until actually approved. |

**Application form supplied by FieldEdge:** https://entsellingsolutions.jotform.com/262085302073954

## Revised supported architecture

1. **Original inquiry (Ads Bridge) -> stored GUID -> FieldEdge lead.** Store original form/call identity, advertising click IDs and separate consent evidence under existing Ads authority. Prepare a durable, tenant-scoped GUID before first lead POST; never regenerate it during dispatch. The original Climatrol DEV form 39 entry 889 is historical and has **no verified FieldEdge lead GUID**; this does not retroactively create one.
2. **FieldEdge lead GUID -> its status.** `GET /v1/leads/{leadId}` is the official lead-specific outcome source. `JobCompleted`/`JobFinalized` can become qualified outcome *candidates*, not automatic CRM Won or conversion without policy/evidence acceptance. `QuoteAccepted` is not a verified invoice payment. Unknown provider statuses stay unclassified.
3. **FieldEdge customer -> work orders -> quotes/invoices.** Keep the already accepted 0.1.1 real-data CRM projection as operational customer intelligence. Invoice totals remain unassigned to specific ad leads absent independently confirmed linkage.
4. **Explicit human-approved job association if revenue attribution is required.** A Climatrol office review of one inquiry and actual work-order ID (plus immutable recorded evidence) can establish a one-to-one case. Never batch-match historical jobs by customer, email or completion date.
5. **Google Ads and CRM lifecycle remain owned by Ads Bridge/CRM consumers.** FieldEdge returns evidence; the consumer enforces consent/export and business conversion definitions. No automatic sales conversion or ad export from this new FE-only interpreter.

## Changes executed with this clarification

- `includes/connectors/fieldedge/LeadStatusProjection.php`: pure deterministic status interpreter for the eight confirmed labels. It does not emit work-order IDs, invoice IDs, revenue, lifecycle writes or export eligibility.
- `includes/connectors/fieldedge/LeadCreateDryRun.php`: optional, caller-supplied and validated GUID in synthetic lead intent; a GUID is not generated during dispatch. The existing live POST remains disabled.
- `includes/connectors/fieldedge/LeadResponse.php`: exact duplicate-tracking-GUID HTTP 400 response is now held as **uncertain / lookup required**, rather than an ordinary validation failure or fictitious successful replay. No automatic POST retry.
- New synthetic tests cover status preservation, untrusted work-order-ID rejection, replay stability, tenant/inquiry binding, GUID validation and duplicate 400 handling. No provider requests, CRM mutations or Google uploads.

## Open validation and product decisions

**Technical:** Obtain a sandbox or explicit authorized one-lead pilot to verify `trackingId` GUID round trip and actual status progression. We have never issued a real FieldEdge lead POST and do not yet have a known API-originated Climatrol leadId. Test whether `JobCompleted` and `JobFinalized` are reliably updated for leads created by the API, including those handled by office staff.

**Business:** Climatrol must decide whether `QuoteAccepted`, `JobCompleted`, `JobFinalized`, or another threshold counts as Won, and how Lost is defined. Invoiced/paid value-based Google reporting cannot be assumed from lead statuses alone.

**Commercial:** SiteEngine may enter partner/Marketplace vetting independently. The form requires the applicant's submission/signature, and any other customer onboarding requires separately approved credentials, tenant identity and permissions.

## Scheduler status check (Nov 18)

The Climatrol DEV Windows Task Scheduler task remains installed. Some actual unattended passes have succeeded and returned `replayed` after four GETs (latest success observed **2026-11-18 00:23 local**); the subsequent 06:23 task failed at pre-HTTP transport with no business GET. It is therefore **functioning intermittently**, not accepted for always-on production use. No automated revenue or Ads export is enabled.
