# Climatrol FieldEdge: historical-contact production read demonstration

**Date:** 2026-11-13

**Scope:** One bounded, read-only production API session. No new scopes, site changes, CRM writes, lead POST, invoice mutation, messaging, customer communications, advertising rows or Google export.

**Source:** Uploaded FluentCRM export `contacts_export_1794581111886.csv`, SHA-256 `3a9653b36859f7cbf06872ab764af04fc7c407ef35d2d8b85f90012e8fac6b49`. Raw source and private contact identifiers are **not** retained in Git.

## Method

CRM historical Won/Lost tags explicitly rejected as unreliable imported data. Candidate contact `#334` was selected because its `Source=FluentForms`, source timestamp and Google click identifier *exist*, and its email is unique inside this export. This evidence does **not** establish a Fluent Forms submission ID or FE-origin job attribution. A second unrelated contact `#194` was reserved as fallback, **not queried** because the first candidate returned a bounded operational relationship.

The established Windows DPAPI bundle supplied OAuth to the fixed official endpoint. The new `HistoricalRead` action is separate from the completed form-39 entries 892/893 pass and has its **own** locked pre-request journal and private findings file. Maximum six business GET requests. No automatic retry, page sweep, bulk enumeration, or writes. No raw provider response or customer contact details in stdout/Git.

## Actual API evidence

| Call | Endpoint class | HTTP | Result |
|---|---|---|---|
| OAuth | Provider OAuth token | 200 | Authorized token, not persisted |
| 1 | GET customer search by **exact candidate email** | 200 | Unique exact customer match; `total=1`, no truncated page |
| 2 | GET customer-associated work orders | 200 | `total=3`, three records in bounded page |
| 3 | GET **one sampled** work order by its returned `workOrderId` | 200 | `customerId` matched the searched customer; `completed=true`; completion date present; explicit `invoiceId` supplied |
| 4 | GET invoice by the **explicit** work-order `invoiceId` | 200 | Returned `invoiceId` matched requested identity; `total`, `balance`, `payStatus`, `invoiceDate` present |

No invoice amount, customer email, name, telephone, UUID or provider body has been published. The three work orders belong to a single provider customer; **only one sample work order was inspected**. Selected sample is not evidence that all three were completed, invoiced or attributable.

**Result:** Verified one source CRM candidate -> provider customer -> work order -> invoice *operational chain*. **No verified provider `leadId`; no explicit original form entry -> provider lead -> job link.** The chain is not proof of Google Ads attribution or conversion eligibility. No `Won` or `Lost` action was created.

## Controls and verification

- Old form 39 entries 892/893 and their closed five-GET journal remain untouched.
- New private journal recorded one token and four business GET attempts, all HTTP 200/valid JSON, and then closed as `selected_case_completed`.
- Source FE branch includes GET-only `invoice` allowlist by exact UUID (no invoice search/list), `HistoricalContactDiscovery`, standalone private stdin CLI and `HistoricalRead` action. Native PHP lint and PowerShell parse pass.
- Synthetic offline regressions: 45 targeted route assertions, 43 OAuth assertions, 12 new historical-contact assertions, all PASS. No provider requests during tests.
- Existing granted `customers:read`, `workorders:read`, `invoices:read` were exercised. `leads:read` was **not** exercised; `leads:write` was **not** used. No additional scopes requested.
- Provider target binding is the established operator-supplied `Production_ClimatrolAirLLC` credential target. Independent `company:read` account-name verification remains unavailable.

## Remaining shortest path

1. **Completed:** Verified original form 39, entry 889, and successful native FluentCRM delivery to candidate contact `#334`. **Not an AB inquiry:** current master form is 40, and no inquiry ledger row exists for 889/334. Do not create an attribution record from this customer match.
2. Seek an explicit provider `leadId` and authoritative `leadId <-> workOrderId` relation. Matching email/customer ID is not sufficient.
3. Only after explicit identity evidence, apply AB's original-inquiry and Consent/DNC gates, approved value stage and replay policy. Historical CRM tags are never proof of outcome.
4. Other read-only FE calls should be individual, independently journaled samples rather than replaying or widening this completed pass.

## Follow-up: original Fluent Forms evidence — 2026-11-13

Read-only canonical WP-CLI inspection of `https://climatrol.siteengine.space` (verified site owner `clima6647`, PHP 8.4) confirmed original **form 39 / entry 889**. Entry timestamp `2026-11-04 06:14:36` exactly matches CRM contact `#334` creation. The entry email matches the existing CRM contact email exactly (no email or other PII printed). The original response contains populated `gclid`, `gbraid`, and `transaction_id` fields. The stored response transaction ID is **not** `ff:39:889`; do not reinterpret it as canonical AB inquiry proof.

The actual native Fluent Forms log for source `submission_item`, source ID `889`, records `component=fluentcrm`, `status=success`, at `2026-11-04 06:14:36`. The form 39 FluentCRM feed is currently enabled and maps `{submission.id}` to `submission_id`, but the existing contact `#334` does **not** currently contain a saved `submission_id` custom field. This is a historic feed success, not proof of current field population.

The current Ads Bridge configuration uses **form 40** as the master form; an exact read of `se_ads_inquiries` yielded **zero** rows for `(form_id=39, submission_id=889)`, `transaction_id=ff:39:889`, or `contact_id=334`. This is a historical form 39 entry, not an already adopted AB inquiry. No configuration change or backfill was made. **No provider `leadId` or originating inquiry-to-specific-work-order link was found.** Existing same-customer three work orders and one read invoice do not create attribution by association.

**Status:** Bounded production API demonstration PASS for operational chain. Historical form-to-CRM delivery confirmed independently in WordPress. AB inquiry attribution and automatic FE synchronization NOT VERIFIED.
