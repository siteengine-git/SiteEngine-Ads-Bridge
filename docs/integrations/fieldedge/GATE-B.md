# Gate B — one approved unscheduled FieldEdge lead

The owner's superseding workflow: form40 records inquiry preferences, while
Climatrol schedules in FieldEdge. FE owns actual appointments, job status and
financial facts. AB preserves original preferences and records verified FE evidence
separately. No form redesign or confirmed appointment is required.

Contract reviewed November18,2026:
[official create-lead OpenAPI](https://docs-dev.fieldedge.com/leads.md).
`leadRequest.required` lists source/customer/isExistingCustomer/appointment/
serviceRequest. The referenced `leadAppointmentRequest` and
`leadServiceRequestRequest` have no required-property lists. Thus retain the required
objects, including `{}` when empty, and omit absent optional dates, times,
location and issueDescription. Examples are not required-property declarations.
The new-customer prose requires a phone or email. Runtime tenant acceptance remains
unproven until the specifically approved external POST. Documented preferred-time
UTC wording and example timezone do not justify fabricating/converting an absent
date/time. Preferred window is retained as availabilityNotes; no actual appointment
is created locally or inferred from preferences.

The existing AB prospective/evidence table and contract2 reservation remain source
authority. Gate B adds immutable exact-card review, explicit administrator approval,
an expiry/runtime hash and one-use approved POST/GET claims. A replaced body, source,
tenant, GUID, card or installed approval runtime cannot inherit approval. Existing
synthetic methods remain disabled for real traffic. No boolean settings toggle or
public endpoint enables dispatch.

Windows uses the existing DPAPI bundle and FieldEdge-Connection.ps1. The extended
owner-scoped operation stages private request bytes through OpenSSH stdin into an
owner-only file outside webroot, then calls the canonical Invoke-Site-Wp.ps1 runner.
No provider credential travels to Alma, command arguments, Git, options or chat.
The native CLI process ends after the durable claim/lock/transaction proof, before
Windows loads credentials and performs OAuth/HTTP. Exact-body POST and stored-GUID
GET use existing bounded TLS transport: verification on, no redirects/retries,
15-second timeout and64KiB body ceiling. Responses/failed receipts are private;
only sanitized result metadata is printed. Duplicate400/lost reply stays uncertain
and is reconciled by GET of the same GUID, never another POST or another GUID.

One card ceiling: two token requests total, one POST, one initial GET and at most
one explicitly invoked recovery GET. No automatic retry. Claims are retained even
if authentication/transport/receipt persistence fails. Resume exact receipt writes
from their private file; do not replay a network command to repair persistence.
Raw GET results are transient/private; AB stores sanitized status and body digest.
No CRM lifecycle write, actual appointment/job/invoice mapping, revenue, messages,
Google operation or worker schedule change is authorized by Gate B.

Operator input is a private JSON containing `entry_id`, `contact_id`, and `request`.
The request supplies actual inquiry/contact/preferences; preparation adds only the
technical GUID. Use confirmed contact341/form40 entry896. Native source identity
and email correlation must match before AB adoption/reservation; no old form39
entry889 reuse. Absent optional source fields are omitted rather than invented.
The local preparation plan is private custody for exact retry, not another FE ledger.

Run from canonical source with PowerShell7:

1. `FieldEdge-Connection.ps1 -Action PrepareLead -EvidenceFile <private-input> -EvidenceSha256 <hash>`
   records the native reservation and reviewed private card, without approval/HTTP.
2. Inspect the exact private card and approve its SHA, intended contact/preferences,
   possible new customer and staff-notification effects. Only AFTER explicit owner
   approval: `-Action ApproveLead -CardFile <card> -CardSha256 <hash> -ApprovalReference <owner-decision-reference>`.
3. After that specific approval: `-Action LeadDispatch -CardFile <card> -CardSha256 <hash>`.
4. If needed, explicitly inspect retained receipts before `-Action LeadRecover`:
   it can claim one additional GET and can never repeat POST.

Do not invoke ApproveLead/LeadDispatch/LeadRecover during preparation acceptance.
Installed native tests use a different fixture tenant and injected transport only.
Stable0.9.5 delivery remains unchanged; accepted0.9.6-rc.1 Gate A evidence is reused.
