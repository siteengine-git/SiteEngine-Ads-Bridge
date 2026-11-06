# Trusted external outcome contract 1 — integration candidate

Implemented callable: `seab_external_outcome_submit(string $body): array`.
Pass UTF-8 JSON object bytes, at most 16 KiB, outside a caller SQL transaction.
Preserve the complete bytes for retries. A reordered/re-encoded body is a conflict
under the same operation identity. This is trusted same-site PHP only; no REST,
listener, scheduled worker, CRM head operation or implicit lifecycle projection.

Discover `seab_external_outcome_capability(): array`; copy its `destination` and
`contract_version` (`"1"`). Discovery is not authorization or schema preparation.
An administrator explicitly runs `SiteEngine_Ads_Bridge_Outcomes::migrate(true)`.
Only two additive InnoDB tables are introduced: reviewed evidence and receipts.

Every submission has exactly these fields (all strings):

| Field | Meaning |
| --- | --- |
| contract_version, destination | Discovered exact contract and installation identity |
| operation_id | Stable provider/tenant-scoped operation identity, 1–128 ASCII identifier bytes |
| provider, tenant_id | Provider and company identity |
| event_id, event_sha256 | Stable provider notice identity and SHA-256 of its original exact bytes |
| evidence_id | Previously stored operator review identifier |
| inquiry_id | Explicit AB inquiry, at most 40 identifier bytes |
| customer_id, job_id, invoice_id | Explicit source identities; FE work_order_id maps to job_id |
| completed_at, invoice_at | Valid zoned ISO timestamps, retaining source offset/fraction |
| amount, currency | Positive two-decimal string and USD for this bounded synthetic candidate |
| snapshot_sha256 | SHA-256 of fetched/normalized source snapshot |
| value_policy_id | `synthetic-usd-demo-1`; establishes no Climatrol revenue policy |

Identifiers use `[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}`. SHA-256 uses lowercase hex.
Use FE Foundation validators/digest for source snapshots. Unknown fields fail.

Before submission, a trusted operator with `manage_options` invokes
`seab_external_outcome_review(array $evidence): array` with:

```php
[
  'evidence_id' => 'review-demo-1',
  'outcome' => [ /* all 12 enumerated economic/association fields;
                    excludes operation/event/envelope/evidence_id fields */ ],
  'provenance' => ['reference'=>'synthetic-review-1', 'sha256'=>'<64 hex>'],
  'synthetic' => true,
  'source' => 'form',
]
```

The review is a separate explicit attestation by the trusted operator, not a
submission boolean. AB stores the reviewer, provenance and exact normalized
outcome immutably. Conflicting reuse of an evidence ID is rejected. Two reviewed
inquiries for the same provider/tenant/job cause `association_ambiguous`.
Core reads the stored review and immutable inquiry; it never guesses email/phone
matches or trusts a caller-chosen ff/Google transaction ID. No provider fetch or
provider verification is implied by this synthetic operator review.

Results contain `contract_version`, `status`, `reason_code`, `receipt_id`,
`body_sha256`, `outcome_revision` (1), and `export_eligible` (always false here).
Persisted results also contain `association_status`, `canonical_receipt_id`,
and `inquiry_id`. `recorded/outcome_persisted` means persisted only. Missing review,
ambiguous association, unmatched immutable review, unavailable inquiry, unsupported
call source, value policy and additional/revised outcomes produce durable `held`
receipts with specific codes. A valid reviewed association remains `attributed`
when Consent/current DNC later blocks advertising eligibility.

`retry` means replay identical bytes in a fresh request after resolving the named
persistence/protection condition. No automatic retry. `conflict` includes invalid
requests, operation identity reused with different bytes, and source event reused
under a new operation. Identical replay returns the exact stored response and
does not re-evaluate permission. Read using
`seab_external_outcome_read(string $receipt_id): ?array`; returned database row
contains exact `body`, `body_digest`, `response`, and canonical unique keys.
Null means not found, not permission to rotate identities. Read errors throw.

One supported Converted outcome is reserved per inquiry/action and per
provider/tenant/job. Different source notices with identical reviewed economic
data persist alias receipts referencing the first canonical receipt. Additional
jobs/invoices, changed amount/timestamps or snapshot are held for review; no
aggregation, correction, refund or historical export rewriting is implemented.
No Qualified action or 21/94-column contract is changed.

CB intake: `seab_external_call_evidence(string $call_sid,string $inquiry_id): array`
reads through CB 0.1.4 `CallLogRepository::findByCallSid($sid,true)`; never writes
CB tables. The identity is `twilio:{CallSid}`, independently related to the explicit
AB inquiry. Administrator intake persists that separate relationship, source
timestamp/status/qualification and snapshot digest in AB's evidence table.
Missing evidence/interface holds; call-origin Google delivery always
holds as `call_origin_google_contract_unsupported`. No form identity is fabricated.

Explicit CLI advertising inspection/export uses `se-ads outcome --receipt=<id>`;
`--apply` is limited to the pinned isolated synthetic target described in
[the operator runbook](CLIMATROL-DEMO.md). It reuses the existing 21-column builder,
fresh Consent/current DNC checks, HTTP transport and Converted stage ledger. No
normal export family is enabled. `current_dnc` and `consent_evidence_missing` hold
advertising while the recorded business receipt remains attributed. Newly ambiguous
review evidence blocks future dispatch without rewriting the historical receipt.
An uncertain append persists intent and requires exact worksheet `--reconcile`;
ordinary/replayed exports cannot silently submit another row.

Candidate, not stable release. Installed/native/export acceptance is recorded
separately in the integration demonstration records; this document makes no pass claim.
