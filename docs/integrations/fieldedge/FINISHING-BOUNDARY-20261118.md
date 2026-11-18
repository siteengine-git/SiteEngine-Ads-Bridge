# FieldEdge Climatrol pilot — exact completion boundary, November 18, 2026

## What is installed and working now

- Climatrol DEV site: `https://climatrol.siteengine.space`, with Ads Bridge 0.9.1 and FieldEdge Connector **0.1.1 active** (confirmed owner-scoped WP-CLI).
- FieldEdge OAuth credentials held in Windows DPAPI; existing bounded reader can retrieve real customers, work orders and invoices. Three Climatrol work orders with invoice references were projected into FluentCRM contact 334, with persisted exact replay.
- Six-hour Windows DEV scheduler has completed some full unattended provider GET + CRM replay passes, including November 18 00:23 local; a later 06:23 pass was held before HTTP. It is **intermittently operational**, not reliable 24/7 production scheduling.
- An orphaned plugin folder left by an earlier failed 0.1.0 ZIP install was backed up outside webroot and removed on DEV; the active 0.1.1 installation remains intact.

## What the provider actually confirmed

FieldEdge Integrations Team (November 18 written response) confirms **no supported API GET or webhook returns a work-order ID for a lead**. Do not keep searching for a lead-workorder crosswalk.

- Optional `trackingId` must be a **GUID** and becomes the FieldEdge `leadId`.
- `GET /v1/leads/{leadId}` returns lead-specific business states: Accepted, Rejected, QuoteAccepted, QuoteRejected, QuoteExpired, JobCanceled, JobCompleted, JobFinalized.
- Repeated POST with the same GUID returns HTTP 400 (`Tracking ID already exists`) without returning the old lead. Safe response is hold + independent GET of the **original stored GUID**, never mint a new GUID or retry blindly.
- Quote and invoice IDs equal their work-order IDs per provider. Invoice exposes total, balance, payStatus, not verified individual payments. No lead-to-invoice identity is furnished.
- Auth is HTTPS with JWT bearer. `X-Correlation-Id` (printable ASCII <=128 chars) is accepted and echoed when requests reach the API. Pre-HTTP transport failures have no API response/requestId.
- Partner/Marketplace approval is a **separate commercial process** requiring SiteEngine's actual signed application; it does not block existing customer-authorized Climatrol read-only DEV work.

## Current actual code boundaries

**Ads Bridge prospective code** in current AB source `includes/class-siteengine-ads-bridge-prospective.php` is **synthetic-transport-only**. Read-only native Climatrol DEV inspection on Nov 18 confirmed the installed 0.9.1 exposes the `seab_prospective_prepare/claim/observe/read` functions and `SiteEngine_Ads_Bridge_Prospective::by_lead`, but the contract still does not provide an accepted live dispatch. These functions being present is **not** evidence that a real provider POST can occur. Both `prepare` and `claim` enforce synthetic envelopes/dispatch mode. Its real Climatrol DEV installation is **0.9.1**, whereas current AB source has progressed toward 0.9.5. Do **not** force-update AB or replace its durable evidence logic from an FE branch without reconciling the installed schema, runtime and active core ownership.

**FieldEdge branch** now has:
- `LeadCreateDryRun`: optional caller-prebound, GUID-validated trackingId, but real lead POST remains disabled.
- `LeadStatusProjection`: pure interpretation of eight provider-confirmed statuses, no lifecycle or Ads side effects.
- `LeadStatusDiscovery`: exact existing-lead-GUID -> one allowlisted GET -> status evidence, no invented work-order ID, revenue or conversion.
- `OAuthClient::selectedCase` plus `SelectedCaseJournal`: secret-safe 1 token + budgeted GET path. `FieldEdge-Connection.ps1 -Action LeadStatus -RunId ... -EvidenceFile ... -EvidenceSha256 ...` runs only with a real, privately pinned existing lead GUID. No valid API-created leadId is known at this time.
- Tests run entirely on synthetic transport and assert token/PII isolation, one GET, replay/invalid-GUID rules, no fake work-order link.

## Acceptance criteria for genuinely finishing the Climatrol lead-outcome pilot

1. **Native AB authority:** AB must durably reserve and bind a GUID to a real `inquiry_id`, originating form/call event, tenant and validated request **before** dispatch; enforce exact replay/at-most-once; no guessed click IDs or fabricated inquiry. Work from active AB 0.9.x authority and site schema, not parallel FE storage.
2. **FieldEdge lead creation:** Bind the prepared GUID to exactly one authorized new lead POST; persist a verifiable response or an uncertain receipt. Duplicate HTTP 400 -> GET original GUID and reconcile. No auto-repeat with another GUID.
3. **Lead-status polling:** Using that same GUID, authenticate and GET the exact lead (never search by email or a person's other work orders), preserve accepted provider state transitions and provider response digests; hold mismatches, unknown statuses and regressions.
4. **CRM business policy:** Choose the explicit stage definitions (e.g. what constitutes Qualified, Won, Lost). Do not assume `QuoteAccepted` means paid. Native CRM automations can subscribe to an accepted stage, not to an unverifiable job association. Preserve unrelated contact state, DNC and disabled sending until an authorized automation is configured.
5. **Google Ads:** Ads Bridge alone exports eligible original inquiries with their captured click IDs, controlled consent/export checks and accepted conversion stage. If an exact invoice/work-order relationship is absent, **do not fabricate a revenue value**. Lead-status conversion count may be possible while revenue attribution remains deliberately excluded.
6. **End-to-end live proof:** One test inquiry from current configured master form 40 (not historical form39 entry889), exact AB inquiry + GUID, a **single approved FieldEdge lead create**, GET initial status, then test staff workflow to advance status, bounded poll to CRM, replay and eligible Google Sheets dry-run. No live Ads submission without separate authorization. Historical entry 889 has no known API-created provider lead GUID and cannot serve as this test.
7. **Pilot reliability:** Schedule independently, ensure a successful unattended complete run after deploy and safe recovery of token/HTTP/CRM failures. Current Windows InteractiveToken scheduler works intermittently and should not be described as always-on.
8. **Commercial expansion:** Separate partner application/approval, per-customer OAuth/tenant credential separation, installation/onboarding and production-grade transport after Climatrol acceptance. Marketplace approval is outside the DEV technical acceptance.

## Action and external-effect boundary

Local code, synthetic tests and scoped Climatrol DEV installs are covered by existing authorization. A new `POST /v1/leads` to the Climatrol FieldEdge tenant is an external operational change that might alert staff or create a customer/appointment. **No real lead POST has been made.** To perform the live proof, obtain explicit approval of a specific controlled test lead and its recipient/contact characteristics, or use a separately provisioned FieldEdge sandbox tenant.

The project cannot honestly be declared **complete, integrated and externally verified** without that live test and an accepted AB native pipeline. A current source pass is not equivalent to a production outcome or Marketplace certification.
