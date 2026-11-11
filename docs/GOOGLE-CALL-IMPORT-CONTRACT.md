# Call-origin Google contract — preview only, November 11, 2026

Selected reference: Google's calls-upload CSV, distinct from the existing21-column
Data Manager sheet. No sink, schema, scheduler or API upload is added. Existing
Google_Ads_Import21 and All_Leads94 contracts remain unchanged.

Primary evidence:

- [Calls import setup](https://support.google.com/google-ads/answer/6275629?hl=en).
- [Actual official CSV](https://www.gstatic.com/conversiontracking/call-conversion-import-template.csv), SHA256
  `b9054427d5e012b8a154a4349fdd583121b2a14c44ee5f17d4bcfd065461056e`.
- [CallConversion fields](https://developers.google.com/google-ads/api/reference/rpc/v26/CallConversion)
  and [API requirements](https://developers.google.com/google-ads/api/docs/conversions/upload-calls).

The downloaded CSV has eight columns: caller phone, call start, conversion name,
conversion time/value/currency, Ad User Data and Ad Personalization. Pure preview
maps E.164 caller, true offset-qualified start, exact action name and later reviewed
business instant/value/currency. CSV output offsets use four digits. Consent columns
stay blank. Structural PASS always remains held/export_eligible false/delivery false;
arbitrary authority flags are rejected. No upload-ready row is produced.

Google account tracking/forwarding and enabled calls-upload action need independent
verification. API action-resource/Consent requirements are separate; API remains
disabled. No account or real call was inspected. Duration/UTM/contact matching cannot
establish qualification, original identity or permission.

## Concrete source-owner gaps

CB API at480b4ca2def17687a4db80e36891d03bf05ed003 lacks caller E.164 and true
provider-start/offset. Capture UTC is server receipt observation; naive legacy time
cannot substitute. Capture0.1.7 acceptance remains closed. A later call-mode decision
must define minimum provider-verified caller/start facts and privacy/access boundary.

Consent owner confirmed at34ccc2c101163ab46f4a2189ddaa3d34f1b4f206: public
current_eligibility accepts an original canonical Fluent Forms stamp and real formID;
no call-bound issuer/association/current-authority API exists. Raw receipt reference,
copied form stamp, internal form0/sealing and contact matching cannot authorize calls.
No legitimate positive call-Consent fixture exists under that contract. Positive
tests cover structure only; missing/negative/unavailable/DNC evidence stays held.

Smallest future resolution: CB supplies trusted prospective browser-intent linkage
to provider/account/site-scoped canonical call and association point. Consent owns
the subject-typed immutable envelope/signature and current withdrawal/barrier checks.
Negative/pending originals cannot be upgraded by delayed ACK or later re-grant. AB
retains independent DNC/policy/business/Google gates. This requires an owner contract
decision, not fabricated evidence, historical backfill or speculative capture edits.

Focused verification uses synthetic structural fixtures/current-DNC doubles and fresh
native held-call readback only. No new call, grant, Google request, schema or export.
