# FieldEdge capability research — 2026-11-05

Evidence class: official documentation inspected, not observed tenant behavior.
No API authentication, credentials, customer data or entitlement was tested.
Base shown by endpoint examples: `https://api.fieldedge.com/open-api`.
The portal landing page's illustrative `/api/v1/fun/activate` is not an API contract.
Sources below are current documentation, not a pinned provider specification.

| Capability | Documented contract | Foundation use / unresolved issue |
|---|---|---|
| Authentication | OAuth client credentials, form encoding, `client_id`, `client_secret`, `target_id`; token endpoint `https://fieldedge.withgobo.com/oauth/token`; JWT Bearer, example lifetime 28800 seconds | No transport or credential store implemented. Read actual expiry in future; verify target/company binding. [Member guide](https://docs-dev.fieldedge.com/member-authentication-guide-1981868m0). |
| Partnership | Marketplace app, tenant installation metadata, sandbox and provider review described | No SiteEngine partnership, app, sandbox or certification established. [Partner guide](https://docs-dev.fieldedge.com/partner-authentication-guide-1981952m0). |
| Lead by ID | `GET /v1/leads/{leadId}`, `leads:read`; `data.leadId`, `trackingId`, `status`, `customer.customerId`, `accountId` example | Allowlisted ID normalization only. No documented lead-list/search discovered; tracking ID semantics, historic discovery and lead-to-job read links need provider confirmation. [Lead detail](https://docs-dev.fieldedge.com/fetches-lead-details-based-on-the-provided-lead-identifier-42492410e0). |
| Customer search | `GET /v1/customers/search`, `customers:read`; `phone`, `email`, `firstName`, `updatedAfter`, `updatedBefore`, `hasServiceAgreement`; offset/limit (defaults 0/20), sort | Response `total`, `limit`, `offset`, `customers[]`; IDs, `parentCustomerId`, `isJob`, `updatedAt`. Candidate discovery only; never inquiry attribution. Maximum page size, filter boundary/order stability and deletion coverage unverified. [Search](https://docs-dev.fieldedge.com/search-customers-with-optional-filters-42492385e0). |
| Customer/job identity | `GET /v1/customers/{customerId}`, `customers:read` | Customer versus job/billing hierarchy needs tenant fixtures; do not equate every job customer with a billing customer. [Customer detail](https://docs-dev.fieldedge.com/retrieve-customer-details-by-id-42492387e0). |
| Work orders | `GET /v1/work-orders` and `/{workOrderId}`, `workorders:read`; list has `total/limit/offset/data[]`; filters completed, customerId, invoiceAdded, primaryTechEntityId, assigned; sort | Detail shows work/customer/billing IDs, service address, completed/resolution, `workOrderInformation.completedDate`, invoiceId. No modification filter established. Status vocabulary completeness and actual lead link remain unresolved. [List](https://docs-dev.fieldedge.com/retrieve-a-paginated-list-of-work-orders-42492423e0), [detail](https://docs-dev.fieldedge.com/retrieve-a-work-order-by-id-42492424e0). |
| Invoices | `GET /v1/invoices` and `/{invoiceId}`, `invoices:read`; list offset/limit/sort and `data[]` | Detail includes invoiceDate, total, balance, payStatus, items. No currency or modification/version field established. Detail prose says invoice ID equals work-order ID; work-order example uses a distinct invoiceId. Preserve explicit links and seek clarification; never derive invoice ID from work ID automatically. [List](https://docs-dev.fieldedge.com/retrieve-a-paginated-list-of-invoices-42492403e0), [detail](https://docs-dev.fieldedge.com/retrieve-an-invoice-by-id-42492404e0). |
| Notifications | `POST /v1/webhook`, `webhooks:write`; callbackUrl, optional headers; response data.webhookId/secret | Envelope id/companyId/source/type/time/datacontenttype/data with record ID. HMAC-SHA256 exact body, `X-FieldEdge-Signature: sha256=<hex>`. Notifications require scoped GET for details. Pure verifier only; no receiver. [Registration and envelope](https://docs-dev.fieldedge.com/register-a-webhook-endpoint-to-receive-event-notifications-42492420e0). |
| Subscription management | `DELETE /v1/webhook`, `webhooks:delete`; `POST /v1/webhook/secret/rotate`, `webhooks:write` | Delete stops delivery; rotation replaces secret immediately. Existing registration inspection, duplicate registration behavior, cardinality, delivery retries, order and retention are unresolved. Never replace an unknown existing subscription. [Delete](https://docs-dev.fieldedge.com/delete-the-registered-webhook-for-the-authenticated-account-42492421e0), [rotate](https://docs-dev.fieldedge.com/rotate-the-hmac-signing-secret-for-the-registered-webhook-42492422e0). |
| Throttling | Shared per partner across keys/customers: 100/rolling minute; 1000/fixed UTC hour; 429 with seconds Retry-After; quota-specific 403 without it | Future shared throttle required. Foundation classifies only; no workers or scheduler. Distinguish authorization 403. [Rate limits](https://docs-dev.fieldedge.com/rate-limits-2483544m0). |

## Questions requiring provider/access evidence

1. Can authorized access exclude item costs, unrelated invoices and other financial
   details at provider level? Discarding fields locally does not restrict retrieval.
2. What binds authentication target_id, accountId, webhook companyId and CRM tenant?
   Foundation's tenant namespace represents a reviewed companyId, not an inferred
   equivalence between those fields. Bind destination and target explicitly later.
3. How are preexisting leads enumerated, and how are lead/job/work-order/property
   relationships retrieved without guessing from matching email or telephone?
4. What stable revision markers, modification filters, sort ties, page maxima and
   deletion/cancellation reconciliation guarantees apply to work orders/invoices?
5. Are payments/refunds/credits/adjustments readable? Invoice balance/payStatus alone
   does not prove payment amount, time or refund history. No such endpoint verified.
6. What are webhook retry schedule, duplicate-ID/body guarantees, delivery timeout,
   ordering, replay horizon and subscription coexistence rules? HMAC authenticates
   bytes, not freshness or uniqueness. Delayed original notifications are possible.
7. Confirm actual least-privilege scopes, Member quota applicability and any
   sandbox/production differences with the provider before access tests.
8. What review, pricing, contractual, Marketplace and certification requirements
   apply to this integration? Portal instructions alone establish none for SiteEngine.

No official full response schema was exported. These are inspected documented
fields/examples; strict completeness is not claimed. The `.md` links from llms.txt
failed in this research tool; normal official HTML pages were used successfully.
