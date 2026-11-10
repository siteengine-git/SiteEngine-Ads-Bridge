# Selected-entry targeted discovery

FE-owned extension authorized by AB Helper through AB Codex, the execution lead.
This follows two privately selected original form entries. No arbitrary probe,
source backfill, outcome submission, deployment, record write or Google export.
Original evidence, selector values and actual provider IDs stay outside Git/chat.

## Narrow operations

TargetedRead constructs canonical GET URLs on api.fieldedge.com only:

* customers/search: exactly one email or phone selector;
* work-orders: exactly one observed customerId;
* work-orders/search/by-address: addressLine1, city, stateOrRegion, postalCode;
* work-orders/{id}, customers/{id}, leads/{id}: explicit observed UUID references.

List operations fix offset0 and limit3. No page traversal, arbitrary URL, service/
date query filter, invoice/financial endpoint, unknown parameter, fragment, alternate
origin or business-write method is supported. Customer modified-time filters are
not repurposed as work-order dates. Exact lead reads occur only when a selected
work order actually supplies its leadId. The original one-record smoke probe is
still separate; Discover does not invoke it.

Official endpoints:
[customer search](https://docs-dev.fieldedge.com/search-customers-with-optional-filters-42492385e0),
[customer work orders](https://docs-dev.fieldedge.com/retrieve-a-paginated-list-of-work-orders-42492423e0),
[address work orders](https://docs-dev.fieldedge.com/retrieve-work-orders-at-a-given-address-most-recent-first-42492425e0),
[work-order detail](https://docs-dev.fieldedge.com/retrieve-a-work-order-by-id-42492424e0).
Lead-by-ID is linked from the official API index; no lead search/list is invented.

## Bounded execution and evidence

FieldEdge-Connection.ps1 adds Discover with EvidenceFile/EvidenceSha256. AB Codex
supplies the exact private original-evidence reference and hash. The evidence must
contain exactly two distinct original entries from the same form; no production
identifiers or contact selectors are embedded in the implementation or test data.
The existing Windows DPAPI credential route sends credentials to PHP through stdin.
TLS, origin, redirect, expiry, body/header bounds and timeouts remain enforced.

One token attempt is recorded before authentication. SelectedCaseJournal holds an
exclusive lock on one private per-pass file under the existing protected credential
directory. Each business GET reserves its numbered slot, fsyncs and reads back the
state BEFORE network dispatch. Six is the cumulative ceiling across both entries.
An uncertain GET still consumes its slot. No automatic retries or token refresh.
Closed, previously started, corrupt or mismatched pass state cannot be reset by
reinvocation or a second token. Existing findings cannot be overwritten. The launcher's
130second overall ceiling bounds the finite token-plus-six-reads workflow.

This is one private request journal, not an outcome ledger or general inbox. It
stores operation names, selector hashes, attempt status and safe schema types,
never credentials/tokens/query values or raw provider bodies. Minimal actual IDs,
relationship references, source times and comparison flags go to the separate
private findings file, inheriting the same restricted Windows directory ACL.
Safe stdout contains counts/statuses/disposition and private evidence paths only.

Discovery first narrows customers using the selected original email (or phone).
Only an exact returned selector match is a customer candidate. One matched customer
permits its work-order list; otherwise a complete original address may narrow a
documented address search. Only a unique work-order candidate, or unique exact
address match among that page, is inspected by ID. Ambiguous candidates remain
Needs Review rather than selecting an arbitrary latest job. No pagination sweep.

Candidate customer/address/service/timing overlap cannot prove origin. A defensible
pair additionally requires an exact unique original transaction reference observed
in the provider's trackingId, or in an explicitly linked lead's documented trackingId
with matching provider customer identity. Actual field presence is observed, not
presumed. Absence/ambiguity/incomplete bounded results remain Needs Review; they do
not prove no relationship exists. Stop after one defensible pair. Consent/DNC and
advertising eligibility remain AB-owned independent checks; no permission is granted.

Request/structure failure retains partial findings and conservatively counted
attempts, closes the pass as selected_request_failure_stop, and exits nonzero.
No source change, new journal or operation rotation may be used to bypass that
record. AB Codex receives the factual execution result and owns consolidation.

## Focused local verification

44 synthetic targeted assertions,43 original OAuth assertions and7 Windows DPAPI/
parser/status/Unicode assertions PASS. Five affected PHP files lint clean. Tests use fake
transports and native temporary files only, with distinct synthetic form entries.
They cover route/method/filter refusal, canonical queries, pre-dispatch reservation,
seventh-request refusal, fresh journal read, no rerun reset, uncertainty/no retry,
contact-only non-attribution, explicit-reference stop and partial-failure reporting.
The Windows launcher sends explicit UTF-8 bytes through redirected stdin so the
Windows PowerShell console code page cannot alter Unicode paths or credentials.
Fixed stage codes identify local failures without exposing inputs or exceptions.

## Bounded production execution

Execution pin2551d2470705b1efd9b7555703b67bbe71458e06:one token HTTP200 and five
business GET attempts,all HTTP200/JSON received;no retries,pagination or writes.
Both cases remain Needs Review;candidate provider/contact/location relationships
did not establish the exact original inquiry-to-job association. The private pass
is closed. Actual identifiers,source timestamps,relationship references and evidence
hashes were returned privately to AB Codex for the consolidated handback. No customer
values are included here. This does not establish Google eligibility,installed
WordPress acceptance,production conversion submission or synchronization readiness.
