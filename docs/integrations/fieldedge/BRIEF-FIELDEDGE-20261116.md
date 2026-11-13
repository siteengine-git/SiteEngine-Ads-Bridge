# SiteEngine–FieldEdge Integration — Technical Progress and Questions

**From:** SiteEngine Web Services LLC
**To:** FieldEdge Integrations Team
**Prepared:** November 13, 2026 | **Requested review:** Monday, November 16, 2026
**Pilot tenant:** Climatrol Air (authorized customer integration)
**Product objective:** reusable, independently onboarded SiteEngine connector for other consenting FieldEdge customers; **no Marketplace/partner approval is claimed.**

## Summary

We have a functioning, scoped read-only API integration and have demonstrated delivery of genuine FieldEdge work-order data into Climatrol's **development** WordPress/FluentCRM system. We are not asking for new permissions merely to begin work; we used the existing grants first. Our remaining uncertainty is the **authoritative link between an original lead and a specific FieldEdge work order**, which is necessary before safely attributing job outcomes and invoice values to advertising.

Our source and sanitized acceptance evidence are reviewable at:

- **Reviewer guide:** https://github.com/siteengine-git/SiteEngine-Ads-Bridge/blob/review/fieldedge-20261116/FIELD-EDGE-REVIEW-START-HERE.md
- **Connector branch:** https://github.com/siteengine-git/SiteEngine-Ads-Bridge/tree/review/fieldedge-20261116
- **Installable DEV plugin:** `releases/0.1.1/siteengine-fieldedge-connector-0.1.1.zip` on that branch, SHA-256 `33fd49a1e92ccf0f72299d6b03588e535a12b09a82b71fdaaaa93f5703a9bcac`.

The repository is currently **public**. Please do not reply with credentials, tenant secrets, real customer data or privileged internal information in a public GitHub issue.

## What we actually exercised

| Capability | Production API result | What it proves |
| --- | --- | --- |
| OAuth client-credentials with tenant target | HTTP 200 | Existing credentials can authenticate |
| Customer search by exact email | HTTP 200 | Bounded customer resolution |
| Customer-associated work-order listing | HTTP 200 | Three work orders associated with one verified customer |
| GET work order by exact returned ID | HTTP 200 (three separate records in later pass) | Provider customer/work-order identities and completion fields |
| GET invoice by explicit work-order invoice ID | HTTP 200 (one earlier invoice) | An explicit work-order-to-invoice reference works |
| FluentCRM DEV projection | Installed connector 0.1.1; persisted/read back/replayed | Genuine API records normalized into three isolated CRM fields |
| FieldEdge lead creation | Not attempted | No production POST or customer-facing side effects |
| Lead detail by `leadId` | Not attempted | No genuine verified `leadId` available in the examined records |
| Webhook registration and live notifications | Not attempted; `webhooks:write` not granted | Not a proven event-driven sync |

The permission set reported as available is `customers:read`, `workorders:read`, `invoices:read`, `leads:read`, `leads:write`. The table above distinguishes successful endpoint tests from merely granted scopes. The work-order/customer/invoice association is **not evidence** that a given website inquiry caused one of several jobs for the same customer.

Safety: OAuth credentials are stored with Windows DPAPI outside Git; the reader uses a strict HTTPS allowlist and fsynced request budget. No raw customer record, credentials, token, monetary value or identifying provider data is published in the acceptance report. No FieldEdge writes, customer messages, Google uploads or historical CRM tag changes occurred.

## Precise questions for FieldEdge

**1. Lead-to-work-order continuity — primary blocker.** After a lead is submitted via `POST /v1/leads` (and `leadId` returned), what supported GET endpoint, event field, or workflow record provides an **authoritative `leadId` ↔ `workOrderId`** relationship when office staff schedule/convert that lead? We reviewed the public lead-detail and work-order schemas and did not find a guaranteed cross-reference. Is this relationship available for historical/manual website leads that were not created through the Open API?

**2. External tracking identifier.** Is caller-supplied `trackingId` retained uniquely and immutably, can it be queried later, and is it carried from the lead into the resulting job/invoice or another lookup? Please provide the exact supported field/endpoint, collision policy and retention behavior. We do not want to assume duplicate-lead prevention or lineage without a documented guarantee.

**3. Safe end-to-end test.** Can we use a FieldEdge-provided sandbox tenant and test credentials to create one synthetic lead, observe its appointment/job conversion, fetch the resulting work order and invoice, and verify replay behavior **without contacting real customers**? Which scopes and approved workflow are needed? We will not issue a real lead POST without the customer's explicit authorization.

**4. Event delivery and permissions.** We understand the documented `POST /v1/webhook` requires registration and sends notification-only events with an HMAC signature. When appropriate, can `webhooks:write` be enabled for the sandbox and later for customer installations? Can the notification chain carry an explicit lead/job relationship, or must it be resolved through separate GETs?

**5. Operational status and financial semantics.** Which API fields should be treated as authoritative for accepted quotes, completed jobs, canceled/lost opportunities and financial outcome? The invoice `total`, `balance` and `payStatus` were observed, but these are **not independently verified payment transactions**. Please clarify whether separately scoped payment transaction APIs are available and recommended for a later paid-revenue phase.

**6. Authentication transport / diagnostics.** We had successful OAuth responses and successful business GETs, but later local Windows OAuth calls failed before an HTTP response; DNS and TCP/443 checks succeeded. We are investigating the client-side transport. Are there recommended diagnostic headers, endpoint transport requirements, tenant throttling considerations or correlation IDs we should collect without disclosing secrets? We are not asserting a FieldEdge outage.

**7. Multi-customer integration path.** We intend to offer this connector to other FieldEdge members, with separate tenant identities, secure onboarding, rate-budget coordination and a no-code owner experience. Please confirm the Marketplace **partner app + Install URL** flow, the review/approval process, sandbox enrollment, any publication restrictions, and the appropriate technical/commercial contact. We understand the public guide describes a **shared partner-wide** rate budget and do not assume that separate customers receive independent quotas.

## Proposed Monday outcome

A short technical response identifying the **exact lead/work-order relationship mechanism** and whether a **sandbox test** is available would let us finish the decisive acceptance test. The Marketplace review/onboarding path and optional webhook/payment scopes can be phased separately; they should **not block** the Climatrol read-only pilot or its verified CRM synchronization.

## Documentation we consulted

- Partner authentication: https://docs-dev.fieldedge.com/dev/partner-authentication-guide-1981952m0
- Lead creation: https://docs-dev.fieldedge.com/leads
- Lead detail: https://docs-dev.fieldedge.com/fetches-lead-details-based-on-the-provided-lead-identifier-42492410e0
- Work-order creation: https://docs-dev.fieldedge.com/create-a-new-work-order-42525006e0
- Webhook contract: https://docs-dev.fieldedge.com/register-a-webhook-endpoint-to-receive-event-notifications-42492420e0
- Shared rate limits: https://docs-dev.fieldedge.com/rate-limits-2483544m0

**Status statement:** Implemented, packaged, DEV installed and real-data synchronized; full autonomous lead-to-invoice attribution and commercial Marketplace distribution are **not yet implemented/approved**.
