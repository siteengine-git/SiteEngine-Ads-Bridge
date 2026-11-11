# Prospective submission contract 1 — development pilot

Trusted same-site PHP, administrator only. No REST route, worker, transport, new
ledger or automatic retry. Uses the existing protected outcome evidence table for
three immutable records: original intent, exclusive dispatch claim, observation.
Existing autocommit/durability/named-lock checks apply. External connector calls
occur only after these functions return; never inside an AB transaction/lock.

Functions:

```php
seab_prospective_prepare(string $intent_json): array;
seab_prospective_claim(string $intent_id, string $request_body_sha256): array;
seab_prospective_observe(string $intent_id, string $permit, string $observation_json): array;
seab_prospective_read(string $intent_id): array;
seab_prospective_by_lead(string $tenant_id, string $lead_id): array;
```

The exact intent JSON contains contract_version `1`, provider `fieldedge`, tenant_id,
operation_id, actual persisted inquiry_id, source, request, and synthetic `true`.
Request is exactly POST to `https://api.fieldedge.com/open-api/v1/leads`, exact JSON
body string and its SHA-256. Form source retains type fluent_form, integer form_id/
entry_id and exact ff transaction. Call source retains type call, canonical twilio
identity and existing AB evidence_id; form columns stay null. AB validates stored
canonical identity; an email/contact match cannot establish it. No fake form entry.

Intent identity is destination/provider/tenant/original inquiry, preventing duplicate
creation by rotating operation IDs. Same exact envelope replays; changed bytes conflict.
Claim issues one opaque permit plus request digest and dispatch_mode
`synthetic_transport_only`. A retained claim cannot expire or be automatically
reclaimed. Fresh readback verifies original exact envelope and permit before transport.

Observation contains contract_version, request_body_sha256, classification
success/validation_failed/uncertain, nullable http_status, response_sha256, nullable
lead_id/lead_status/received_at. Success requires201 and a valid UUID; validation
failure is400/422 only. Timeout,409,5xx,unknown/malformed responses remain uncertain.
Exact observation bytes replay; different bytes conflict. Missing result persistence
leaves the original claim blocking redispatch. No API clears uncertain state.

Read returns contract_version, status prepared/claimed/succeeded/validation_failed/
uncertain, intent_id, request digest as body_sha256, original intent_json, nullable
claim `{permit,body_sha256,dispatch_mode}`, parsed observation and nullable mapping.
Only succeeded maps provider/tenant/lead_id to the original inquiry/source. An exact
provider-lead indexed read returns that same authoritative record. Another inquiry
cannot silently reuse a bound provider lead ID. Missing/malformed/unavailable reads hold.

This is operational prospective identity, not advertising permission. It neither
authorizes email nor derives advertising Consent from subscription status. The FE
connector defaults to no live transport; production POST requires separate exact
approval. Provider documentation does not establish guaranteed lead-to-job continuity
or trackingId idempotency; later job association needs explicit provider evidence.

Call inquiries reuse schema2/contract1 adoption with source call, canonical twilio
transaction and null form/submission IDs. New calls do not inherit mutable contact
qualification/revenue timestamps. Original supported CB evidence is separately
frozen in AB; no historical acquisition is reconstructed. Reviewed synthetic call
business outcomes use existing outcome receipts/dedup. Google inspection freshly
checks current DNC and holds the unavailable authoritative call Consent/Google call
identity contract; no advertising row is released and no call delivery is enabled.
