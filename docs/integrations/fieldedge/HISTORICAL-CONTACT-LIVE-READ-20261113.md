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

1. Resolve a verified **original Fluent Forms submission/Ads Bridge inquiry ID** for CRM contact `#334` via a **separate read-only site query**; the contact export's `Fluent Forms Submission ID` is blank.
2. Seek an explicit provider `leadId` and authoritative `leadId <-> workOrderId` relation. Matching email/customer ID is not sufficient.
3. Only after explicit identity evidence, apply AB's original-inquiry and Consent/DNC gates, approved value stage and replay policy. Historical CRM tags are never proof of outcome.
4. Other read-only FE calls should be individual, independently journaled samples rather than replaying or widening this completed pass.

**Status:** Bounded production API demonstration PASS for operational chain; attribution and automatic FE synchronization NOT VERIFIED.
