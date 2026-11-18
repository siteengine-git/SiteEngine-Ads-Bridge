# FE one-shot review preparation — November 11, 2026

Preserves accepted1c2fe68/dev.2 foundation. This addition supports a review-only
card and explicit synthetic transport. Native production dispatch remains disabled.
No credentials loaded,production record selected,provider request,shared core edit,
new ledger,credential transfer or existing intent reset.

## Official specification findings

The public [leads.md](https://docs-dev.fieldedge.com/leads.md) OpenAPI is available
even though the rendered page omitted schemas. Its required top-level objects/fields
are source,customer,isExistingCustomer,appointment,serviceRequest. Source is nonempty
text without an enum. Nested schemas list no required arrays;new-customer prose
requires phone OR email. ExistingCustomerId overrides the other customer values.
Preferred method descriptions name Email/Phone/SMS,while examples use lowercase.
Response status is integer0..19,contradicting example labels. Optional trackingId
mentions idempotency/traceability but supplies no collision/retry/retention contract.
Date/time descriptions say UTC alongside a timezone field. These inconsistencies
remain explicit owner/provider questions,not silent coercions of retained requests.

The separate [lead detail schema](https://docs-dev.fieldedge.com/fetches-lead-details-based-on-the-provided-lead-identifier-42492410e0.md)
documents lead/customer/account/tracking references. The [work-order schema](https://docs-dev.fieldedge.com/retrieve-a-work-order-by-id-42492424e0.md)
does not establish a lead reference. [Notification documentation](https://docs-dev.fieldedge.com/register-a-webhook-endpoint-to-receive-event-notifications-42492420e0.md)
lists job linking events but not a complete lead/job pair payload. No guarantee of
customer/address creation timing,customer communications or workflow side effects
was found. No production verification or webhook registration was attempted.

LeadReference extracts only documented identity fields from a bounded detail body
and verifies the expected lead UUID. Numeric status becomes code:N;example string
labels remain literal. Neither maps codes to guessed business labels. Unknown fields,
including injected workOrderId,do not establish a relationship. LeadResponse reuses
this normalization;canonical tenant/inquiry mapping remains AB-owned.

## Review-only gate

Exact AB callables supplied and actual core implementation reviewed:

```php
seab_prospective_pilot_review(string $card_json):array
seab_prospective_pilot_read(string $card_sha256):array
seab_prospective_pilot_claim(string $card_sha256,string $intent_id,string $request_sha256):array
```

PilotReview binds these to the accepted LeadSubmission prepare/claim/read seams;
existing observe/read authority is retained. Raw card bytes determine SHA256,never
reserialized JSON. Target contains provider/environment/tenant and SHA256 of an
opaque credential REFERENCE,not secret contents. Card pins exact envelope/source/
new operation/request bytes and budget1token/1lead/0retry. Every boundary checks
card hash,exact bytes and production_post_enabled:false before any injected transport.

Only complete synthetic review_only cards with unresolved=[] are supported. The
default sender has no transport. Production-enabled/mismatched reads,wrong claims,
unresolved owner designation,changed body/budget/tenant and consumed-intent reuse
hold. A new card cannot reset an existing claim. The accepted adapter performs the
durable observation/readback and keeps uncertainty held without blind retry.

PilotPayload inspects exact bytes without rewriting them. It validates documented
required top-level fields/types plus a narrower pilot policy:known fields only,
explicit existing customer UUID,documented preferred-method spelling,unambiguous
string/address shapes,no implicit marketing opt-in or unproven tracking transport.
These policy restrictions are distinct from provider requiredness. Empty synthetic
appointment/service objects prove gate structure only;they do not establish an
acceptable real booking or invent owner values. Tenant acceptance remains unverified.

## Bounded native command

AB alone selects ONE existing original form inquiry with no intent under a fresh
synthetic target. No new form/call or old intent reset. PilotCardFixture::card($context)
requires exact inquiry_id/source/form/entry/ff:f:e,new operation_id/card_id,synthetic
tenant_id and credential_reference_sha256. It does not persist/create an inquiry.
AB saves returned exact bytes privately and hashes that file. No target is hardcoded.

```sh
wp --user=<isolated-admin> eval 'define("SITEENGINE_FE_PILOT_REVIEW_ISOLATED",true); $args=["review","<private-card.json>","<exact-raw-card-sha256>","<verified-home>","<verified-root>"]; require "<FE-root>/tools/fieldedge/pilot-review-wordpress.php";'
```

Review writes at most one AB review+intent. Explicit simulate uses at most one lead
double;token doubles0,realHTTP/mail0. Repeat the same card with simulate only after
readback:expect stored success and0additional transport. Total new AB rows≤4 across
review/intent/claim/result. Wrong card/source/budget claims are focused local checks;
AB records actual installed verification separately. disabled-execute always holds
native_production_post_disabled;it is a refusal proof,not a future live command.

## Genuine execution boundary and pending private card

Windows DPAPI is current-user/machine-local. The trusted AB callables execute in Alma
PHP. The existing Windows credential launcher cannot invoke those same-process PHP
functions. There is no approved REST endpoint or credential transfer between them.
No new route was added. An exact native live execution command therefore remains
unavailable until separate completed-card authorization AND a supported execution
host/credential route are resolved. Do not represent a made-up Pilot action as runnable.

The private real card retains unknown target/original-inquiry/body values as unresolved
owner decisions;no customer,appointment or business data was selected. Its pinned
synthetic predecessor is evidence only,not a launchable real request. AB Helper owns
the consolidated decision request. A later minimal provider continuity question is:
which documented GET/dispatch/event response supplies BOTH leadId and workOrderId,
and is trackingId retained immutably through that process? Before proof,normalization
must remain unavailable rather than infer from matching contacts. Any later selected
read/write needs its own exact bounded authorization;none was made now.
