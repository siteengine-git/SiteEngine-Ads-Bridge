# Climatrol pilot — Gate A

Issue #6 is the sole pilot assignment. Current accepted 0.9.5 is the baseline;
0.9.6-rc.1 is an unaccepted pilot successor, not a stable release.

The existing AB prospective boundary now also accepts a separate version-2
reservation. It binds the destination, tenant UUID, original AB inquiry, canonical
source, tracking GUID and exact POST bytes before dispatch. The existing AB
outcome evidence table holds append-only reservations, exclusive claims, creation
results and ordered GET observations. No FE side ledger or CRM inquiry pointer.
Version-1 callers and records remain unchanged. Existing supported additive
outcome storage initialization is used; no existing table is reset or rewritten.

Trusted administrator PHP entry points are
`SiteEngine_Ads_Bridge_Prospective::lead_prepare/read/claim/result/observe`.
These are not public web/REST actions. Existing administrator, executor, physical
storage and destination-lock checks apply. Keep the complete exact envelope for
replay. A different GUID/body for the same tenant/inquiry conflicts. Reuse of a
tenant/GUID for another inquiry conflicts. Claims cannot be repeated or expired.

`LeadPilotDispatch` accepts only an explicitly injected transport and synthetic
reservations. It has no native HTTP implementation, secret loading or automatic
retry. It verifies the database transaction and destination-lock boundary, closes
the database connection before invoking the transport and reconnects only to
record the observation. Real reservations may be prepared, but their claims are
held for explicit real-POST authorization. No configuration toggle bypasses it.

A lost reply, crash after claim, duplicate GUID 400, rejected/malformed response
or unconfirmed persistence keeps the original GUID. Recover by an exact GET of
that GUID under the same tenant, never by another POST or a replacement GUID.
Record sanitized GET evidence using a stable event identity and expected previous
observation digest. Exact replay is idempotent; changed event bodies, stale heads,
backdated observations, wrong tenant or wrong GUID are refused. Unknown statuses
and unconfirmed/404/mismatched provider replies stay held. The initial POST result
is never rewritten by later status observations; raw GET/customer bodies are not
stored, only sanitized metadata and their digests. A bounded chain of 64
observations requires operator review before further appends.

The reviewed FE `LeadStatusProjection` component is copied selectively from
9384d5ea5f5110241dd2da44bc5c1b6e4105a19f. Its allowlist does not imply a CRM
Won/Lost policy, revenue or work-order join. Every result keeps CRM writes and
export eligibility disabled. Gate B requires one specific approved real request;
Gate A performs no provider authentication, POST/GET, enrollment, messages,
Google operation or worker-scheduling changes. Stable 0.9.5 and historical FE
components remain preserved. Native installed results are reported separately.
