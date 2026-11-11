# Prospective FE lead submission foundation — November 11, 2026

FE-owned source extends fbad499 without changing accepted packages or production
evidence. AB Codex alone owns durable storage,canonical inquiry authority,native
allocation and integration. FE Helper retains scope. Default dispatch is disabled;
only explicit synthetic transport doubles are supported by this milestone.

## Exact AB contract1

Trusted same-site PHP calls provided by AB Codex:

```php
seab_prospective_prepare(string $intent_json): array
seab_prospective_claim(string $intent_id,string $request_body_sha256): array
seab_prospective_observe(string $intent_id,string $permit,string $observation_json): array
seab_prospective_read(string $intent_id): array
```

LeadSubmission::envelope($dry,$context,$operation) preserves exact JSON request
bytes/hash and binds an explicit canonical AB inquiry/source. Form source requires
the exact form/entry and ff:f:e transaction ID;call source requires canonical
twilio:CA... identity and supported evidence ID. No invented form entry for calls.
The local standalone synthetic inquiry ID is not substituted for an actual AB ID.
AB must validate that context against its stored immutable inquiry before preparing.

Prepare identity is destination/provider/tenant/original inquiry;rotating operations
cannot dispatch twice. The first envelope is frozen. FE verifies exact intent_json,
request digest,and exclusive permit/mode on fresh read BEFORE any transport. All
AB calls have returned before transport starts;no HTTP occurs within AB locks or
transactions. An existing claim without observation holds for reconciliation and
never expires into automatic retry. FE owns no extra durable ledger or credential store.

Observation stores only classification,HTTP status,response digest,lead ID,status,
receipt timestamp and request digest. No customer response/error bodies,credentials
or token. AB readback with exact observation and tenant/lead/original-source mapping
is success authority. A lost observation reply may recover from fresh durable read;
failed persistence leaves the original claim blocking another POST. Conflicting or
missing readback holds. Success and terminal holds replay without another transport.

Only400/422 are validation_failed.409,401/403,429,5xx,unknown transport and malformed
201 are uncertain. No automatic POST/token retry/reclaim/reconciliation worker.
Provider lead acceptance is not job qualification,advertising attribution or eligibility.

## Existing OAuth/credential path preserved

OAuthClient::syntheticLead reuses original private authenticate(),tenant binding,
Bearer format/expiry validation and exact JSON bytes. It requires explicit injected
transport before any token request. Default native cURL allowlist STILL refuses lead
POST,including when called indirectly. The existing DPAPI bundle/launcher is unchanged
and is the sole established future secret entry route;no real credentials are needed
or read for these tests. No new switch/action enables production lead submission.
An injected transport is trusted test code,not an authorization to use real HTTP.
Existing TLS/15second/64KiB protections remain intact on original real read paths.

A later authorized live pilot needs a separate reviewed transport/authorization gate
and private exact target/request,using the existing secure stdin/DPAPI path. This
milestone neither loads production credentials for POST nor claims authenticated create.

## Provider evidence and remaining linkage limit

Official create/detail/notification documentation rechecked November11:

- [Create](https://docs-dev.fieldedge.com/leads):POST/v1/leads,leads:write;example
 201 data.leadId,status,receivedAt. ExistingCustomerId resolves the provider customer;
 new customers permit phone OR email. FE retains those explicit identities.
- [Lead detail](https://docs-dev.fieldedge.com/fetches-lead-details-based-on-the-provided-lead-identifier-42492410e0):
 GET by lead ID,leads:read;example retains trackingId,customer.customerId,accountId.
 No demonstrated job/work-order ID in that example.
- [Notifications](https://docs-dev.fieldedge.com/register-a-webhook-endpoint-to-receive-event-notifications-42492420e0):
 job.created/job.linked are listed but the lead example supplies leadId,without an
 explicit job/workOrderId relationship shape. Notifications require scoped reads.

Minimum prospective local proof is canonical inquiry/source → exact durable request
and exclusive claim → returned provider leadId under the same tenant. This supports
inquiry-to-lead evidence,not an assumed lead-to-work-order relationship. Later job
attribution requires an explicit provider lead/job/work-order relation or independent
reviewed evidence of that exact pair. Contact/email/phone matches cannot supply it.

No trackingId idempotency,lookup,click-ID transport,immutability or guaranteed passage
through lead-to-job processing was established. It remains omitted from the payload.
Full rendered required-field/enumeration schema is unavailable in the published portal;
source/service values remain untested tenant semantics. No webhook registration or
permission expansion was requested;webhooks:write is not granted.

## Tests versus native persistence

Local tests use explicit in-memory AB contract doubles and OAuth/lead transport
doubles. These are not WordPress APIs or evidence of installed durable persistence.
No file-backed competing state store is built. AB executes the provided native harness
against its actual implemented callables and existing protected evidence infrastructure.
Focused results:34transport+21submission assertions PASS;43existingOAuth+44targeted+
31dry-run assertions PASS;7affectedPHP lints clean. Actual AB implementation source
was reviewed for exact field compatibility before handback;no core file was edited
by FE. Installed effects still require AB's real isolated execution/readback.

```powershell
& '..\local-test-runtime\php-8.3.36\php.exe' -n .\tests\fieldedge-lead-transport-acceptance.php
& '..\local-test-runtime\php-8.3.36\php.exe' -n .\tests\fieldedge-lead-submission-acceptance.php
```

Native isolated wrapper (AB substitutes verified allocation identity and private
canonical context file;regular FE does not operate the allocation):

```sh
wp --user=<isolated-admin> eval 'define("SITEENGINE_FE_PROSPECTIVE_ISOLATED",true); $args=["success","<private-context.json>","<verified-home>","<verified-root>"]; require "<FE-root>/tools/fieldedge/lead-submission-wordpress-demo.php";'
```

Context file:actual inquiry_id,exact source array,stable operation_id;optional synthetic
tenant_id. AB must create/retain context from its canonical prospective inquiry,not
edit an old inquiry or invent a new source binding. For conflict include the existing
intent_id as existing_intent_id;the harness requires its retained original source.
Scenarios success,validation,uncertain,malformed use distinct actual AB contexts.
Replay the identical success/context only after inspecting the retained receipt;expect
zero OAuth/lead doubles. Conflict reuses an existing intent with altered request body
and must hold without transport. No real credential file is read by this harness.

Ceiling:4fresh synthetic intent cases,≤1claim+1observation each,≤12AB-owned evidence
rows;replay/conflict adds no dispatch. Per invocation≤1OAuth token double+1lead double,
zero real HTTP/mail. Fresh-process read must verify canonical mapping,exact bytes,
claim retention and unchanged CRM/DNC/queues/exports/previous Google journal. AB's
actual installed result is reported separately. No fixture reset or uncertain replay.
