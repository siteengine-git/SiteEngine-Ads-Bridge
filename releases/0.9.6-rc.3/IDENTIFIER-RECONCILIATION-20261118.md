# Identifier reconciliation — November 18, 2026

**Result: no confirmed lead; two phone-only customer candidates.** No runtime,
transport, approval, claim, inquiry or receipt change. Installed 0.9.6-rc.3 remains
the verified development runtime. CRM projection and advertising writes stay disabled.

## Which identifier was used

The retained exact attempted POST body has `trackingId` set to
`e637434b-a65e-4ba8-9d80-b690a9be1815`. It has **no `leadId` property**. The
immutable reservation's `guid` is exactly that trackingId. This establishes the
prepared request, not proof that the original POST reached FieldEdge.

The recovery implementation takes the immutable reservation GUID and constructs
`GET /open-api/v1/leads/{GUID}`. The documented path parameter is named `leadId`.
It did not use a customer ID, inquiry ID, returned lead ID or replacement GUID.
No returned leadId was retained from the original failed creation attempt.

The [public lead GET documentation](https://docs-dev.fieldedge.com/fetches-lead-details-based-on-the-provided-lead-identifier-42492410e0)
exposes leadId and trackingId separately; its example alone does not prove equality.
The retained November 18 FieldEdge Integrations Team written-response summary
explicitly says that a supplied GUID trackingId is stored as leadId. See the
[retained provider clarification](https://github.com/siteengine-git/SiteEngine-Ads-Bridge/blob/9384d5ea5f5110241dd2da44bc5c1b6e4105a19f/docs/integrations/fieldedge/PROVIDER-CLARIFICATION-20261118.md)
and [contemporaneous work-order record](https://github.com/siteengine-git/SiteEngine-Ads-Bridge/issues/1#issuecomment-6984013941).
That contract supports the recovery selector. Equality for this actual attempt
remains unverified because neither a creation response nor matching lead GET exists.

## Bounded authenticated reads

Fresh owner-scoped readback confirmed form40/entry896's stored phone and email
exactly match the retained request. No contact fields were invented or modified.
The successful read-only pass used the same existing Windows DPAPI OAuth credentials
and tenant, with Windows HttpClient's normal certificate validation/system TLS.
It made one token request and exactly three business GETs, without automatic retry:

| Request | Response | Interpretation |
| --- | --- | --- |
| OAuth token | HTTP200 | Authentication worked for this pass. |
| GET original GUID in documented leadId path | HTTP404, empty body | No lead returned; not proof that the historical POST never arrived. |
| Customer search, exact entry phone, offset0/limit3 | HTTP200, total2/returned2 | Both primary phones equal the submitted US number after removing punctuation and one leading country code1. Neither literally equals the submitted formatting. |
| Customer search, exact entry email, offset0/limit3 | HTTP200, total0/returned0 | No matching customer returned. |

The two phone candidates do not match the submitted email or operator-input name.
Their search records contain no lead identifier. Customer search is a supported
[customer endpoint](https://docs-dev.fieldedge.com/search-customers-with-optional-filters-42492385e0),
not a lead search by email/phone. No customer UUID was repurposed as a lead UUID;
no unsupported lookup route or page sweep was attempted. These records establish
phone-only customer candidates, not that either customer or a lead was created
by entry896. The API cannot conclusively recover that historical delivery here.

## Transport and preservation limits

An initial local invocation omitted the launcher's explicit cURL load flags and
stopped before HTTP. Correcting the invocation exposed a pre-HTTP cURL token timeout,
with zero business GETs. Seven credential-free HEAD diagnostics yielded two HTTP405
responses and five timeouts without HTTP response; TLS1.2+HTTP1.1 succeeded once
but failed on repeat. No stable cURL fix or precise TLS root cause is claimed.
The successful bounded read pass used Windows HttpClient, without changing installed
transport or introducing another credential authority. The current intermittent
failure does not establish the original incident's initiating cause.

Original approval-card and both private dispatch/recovery receipt hashes match.
Fresh native readback confirms the exact original lead/claim/observation snapshot
hash is unchanged, with crm_write_allowed=false and export_eligible=false. No old
approval/claim was reopened or consumed for this separately authorized read pass.
No lead/customer POST, replacement GUID for entry896, new form submission, CRM tag,
lifecycle or advertising write occurred. Private bodies and selectors remain out
of Git, issues and reports. Structured results contain only redacted evidence.

## Fresh-test draft boundary

A new controlled roundtrip would be needed to observe the asserted identifier
equality directly; it cannot retrospectively resolve the old attempt. A private,
locally validated **email-only draft** is prepared under the name
`TEST – SiteEngine API – please reject`, using the SiteEngine-domain email in the
previous owner-approved private input. Phone ownership was not established, so
phone is omitted. Required appointment/serviceRequest objects are present without
invented scheduling, address or issue details. The existing validator forbids any
marketingEmailOptedIn property without separate authority; the final draft omits it.
The initial invalid local draft is retained as superseded evidence.

Draft payload SHA-256:
`1f33d6f430f6e76a21b0e151df7aa6ddbc3b5e77a0e2e97a2d7665599742a540`.

Its fresh GUID exists only in the private draft; it is not attached to entry896.
No new native inquiry, AB reservation, approval or claim exists for it. It is not
dispatchable. A fresh native inquiry and durable binding plus a reviewed exact
single-use approval card and specific owner approval remain prerequisites to any
POST. No new approval has been inferred from older instructions. CRM projection
and advertising remain disabled until the actual lead association is verified.
