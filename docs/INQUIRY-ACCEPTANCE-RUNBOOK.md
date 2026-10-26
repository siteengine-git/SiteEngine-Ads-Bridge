# Alma setup and inquiry acceptance runbook

Prepared 2026-10-26, America/Chicago. Execution status: PLANNED / BLOCKED.
This document authorizes nothing by itself. Current assignment permits inspection,
existing read-only isolated checks and documentation commit/push only. Setup,
site writes, deployment, transitions, exports and sends require later scope.

Accepted runtime: Ads Bridge 0.6.3; inquiry schema 2; PHP adapter contract 1;
configuration tree schema 2. Canonical source is recorded in ../STATUS.md.
Runtime/source baseline: bd54e89; documentation predecessor: dad9eaf.
Original ZIP SHA-256:
`c77c9f2f50aea5bd37ccdd51221e4384c80dbb6fa18da2cd84c265d4f0b8c404`.
No diagnosed Ads-side defect or new Ads release is proposed.

Site-specific form IDs, route/owner paths, feed selectors and inspection evidence
are in the local parent-project file
`ALMA-SETUP-EVIDENCE-2026-10-26.md`; it is intentionally outside public Git.
F denotes the existing attribution-capable form identified there. A/B, C, RA/RB,
IA/IB and v are symbols for future real fixture records, not executable IDs.
Never substitute Golden contacts, form IDs, prefixes or consent receipts.

## 1. Fresh state and exact setup delta

Read 2026-10-26: shared START-HERE.md, ACCESS-RUNBOOK.md, parent/project/repository
AGENTS.md and STATUS.md; current contract, settings/provisioner/schema source,
and existing CRM Ops source/status. Required PC/Alma preflight exited 0 using
the project owner/root and PHP 8.3 override. No AWS access was needed.

- Active: WordPress 7.1.3; Ads 0.6.3; Base 2.2.2; Consent 0.6.0; FluentCRM and
  Pro 3.2.6; Forms and Forms Pro 6.2.16; Call Bridge 0.1.0.
- CLI PHP 8.3.36 and alternate PHP 8.4.27 exist. Shared preflight default is
  PHP 8.4; project inspection/tests explicitly used 8.3. Web handler/version
  remains unverified: the owner cannot read LiteSpeed vhost configuration.
  Do not infer web PHP from either CLI binary or change the handler here.
- MariaDB 10.11.19; consent table InnoDB. IS_FREE_LOCK returned 1 for an unused
  probe name; this establishes function availability, not cross-session lock
  acquisition, contention, reentrancy or recovery correctness.
- Ads installed package: 38/38 original payload hashes match. Zero CRM contacts,
  zero form submissions, four form definitions, one consent receipt, zero
  funnels and zero campaign-email rows. That receipt is not a fixture approval.
- No saved seab_settings_v2, external Ads override found, or local client.php.
  Resolver source implies setup defaults; this was not a full WordPress runtime
  provenance check. No mu-plugin directory was found in prior reconciliation.

### Settings to establish after isolation and authorization

| Setting | Current state | Concrete intended setup / gate |
|---|---|---|
| schema_version | default 2; no saved tree | Save canonical v2 settings through supported Ads settings path |
| client.slug / client.short_name | blank defaults | Proposed isolated profile alma-ads-acceptance / Ads Test; approve when saving |
| client.name/timezone/currency/google_ads_customer_id | Base-backed fact storage nonempty | Retain Base authority; verify resolved nonplaceholder values, valid IANA timezone/currency/CID and provenance. WordPress timezone_string is blank; do not silently replace Base timezone or change it here |
| fluent_forms.form_id | 0 | Bind F only after confirming its canonical entry source and feed hold ordering |
| fluent_forms.fields | default map only | Verify exact map below; save explicitly, no aliases to unrelated form fields |
| fluentcrm.lists | Customers / Future Customers / DNC defaults | Create only missing terms, retain existing DNC and unrelated Contact list |
| lifecycle tag roles | only Won exists | Create New, Qualified, Unqualified; preserve Won; prove role IDs unique |
| source tag roles | absent | Add Form Lead for form fixture; Call Lead, Referral, Walk In Lead are default-map completeness items, not permission for call tests |
| lifecycle_automation | false | Leave false during setup; enable only within later controlled lifecycle test scope after writer-hold gate passes |
| consent.required_policy_version | unsaved; policy authority exists | Match current Consent authority; require_ad_user_data=true; reject stale/missing receipts |
| exports.all_leads / qualified_leads / won_jobs / lost_leads | false defaults | Keep false initially. Lost remains retired. Later selector/dry-run checks need explicit temporary settings/export authorization |
| google_sheets.enabled | false | Keep false; no live destinations/credentials copied. Dedicated test sink only if separately authorized |
| Google API upload | disabled architecture | Remains disabled throughout |
| diagnostics | defaults | Keep test-profile guards false unless specifically testing guards; no cron installation |

F already contains these direct field names: first_name, last_name, email,
phone, address_1, address_2, city, state, zip_code, country-list, service_type,
estimated_value, gclid, gbraid, wbraid, fbclid, msclkid, utm_source, utm_medium,
utm_campaign, utm_content, utm_term, referrer_url, page_title, ip_address,
user_agent, cookie_value and transaction_id. Map country -> country-list;
other names map directly. submitted entry ID is obtained from the actual
persisted Fluent Forms entry, never a caller's hidden transaction_id.

Not present as F inputs: submission_id, invoiced_amount, closed_date,
qualified_at, lost_date and the eight se_cid/se_agid/se_adid/se_kw/se_mt/se_net/
se_dev/se_loc context inputs. submission_id must be projected from the stored
entry by the trusted held feed, not added as a trusted visitor input. Lifecycle,
revenue and milestone values belong to CRM/inquiry state; their absence as form
inputs is not a request to collect them from visitors. The eight context fields
are optional for core inquiry acceptance; add hidden capture only if its
ValueTrack coverage is separately included. Do not map anonymous input_text
fields to canonical fields by guessing.

F contains the stamped receipt/policy/banner/method/timestamp, category consent,
ad_storage, analytics_storage, ad_user_data and ad_personalization names.
Installed Consent registers fluentform/before_insert_submission. Actual browser
capture -> authoritative receipt -> persisted entry stamping is untested here.

### Terms and custom fields

Missing lists: Customers, Future Customers. DNC and unrelated Contact already
exist. Missing lifecycle tags: New, Qualified, Unqualified. Won already exists.
Missing default source tags: Form Lead, Call Lead, Referral, Walk In Lead.
Only Form Lead is essential for the form branch; do not create duplicate terms.

All nine Ads provisioner fields are missing from CRM custom-field definitions:

| Slug | Required type |
|---|---|
| invoiced_amount | number |
| closed_date | date |
| qualified_at | date_time |
| unqualified_at | date_time |
| submission_id | text |
| ads_export_status | text |
| ads_exported_at | date_time |
| ads_conversion_value_frozen | number |
| ads_export_error | textarea |

Also missing: customer_rating, a single-choice business field (installed type
single-select / select-one). Options: Wrong Area; Wrong Service; Wrong Product;
Wrong Price; Spam / Junk; Other. Ads provision-fields does not create it.
Use installed field registry; never silently retype existing fields.

CRM Ops owns its separate identity/head and eleven planned operational fields:
first_contact_attempt_at, last_contact_attempt_at, last_contacted_at,
next_follow_up_at, appointment_at, appointment_status, estimate_status,
estimate_sent_at, estimate_expires_on, service_status, service_completed_at.
They are absent here and are not part of Ads provision-fields. Their required
WordPress projection types and se_crm_current_inquiry_id mapping must come from
the approved CRM Ops adapter/schema design, not a second Ads implementation.

### Missing schemas and physical verification

- {actual_prefix}se_ads_inquiries and seab_inquiry_schema_version are absent.
  Explicit migration must reach version 2, preserve data and verify complete
  schema from includes/class-siteengine-ads-bridge-inquiries.php::schema_sql().
  Verify InnoDB, unique inquiry/active-contact/transaction/reconciliation/event
  keys and required nullable recovery columns: projection_plan LONGTEXT,
  projection_plan_hash CHAR(64), projection_verified_at_utc DATETIME.
  A version option alone is insufficient; migration does not run on activation.
- {actual_prefix}se_ads_export_ledger is absent. The supported ledger-admin
  migrate path is separate; verify exact columns/indexes from the current CLI
  schema. Do not cause creation by running an export, clear history, or claim
  sink_txn is unique: it is an index and source ledger semantics apply.
- CRM Ops operational schema is not installed. Current local primitives define
  inquiries, heads, requests, events, outbox, adoptions and recovery under an
  approved prefix. A supported production migration/version/drift design and
  controller/connection layer remain prerequisites owned by CRM Ops.
- Consent receipt table exists as InnoDB. Do not recreate it or alter a receipt.

Future authorized setup commands must first read each live command synopsis.
The private appendix provides complete owner/PHP/WP/root wrappers. Relevant
supported operations: provision-fields (preview / --apply), inquiry-admin
migrate (preview / --apply), inquiry-admin inspect, ledger-admin migrate
(preview / --apply), ledger-admin inspect. inquiry-admin migrate has no
--dry-run flag. No transition CLI payload is invented in this runbook.

## 2. Isolation must precede setup fixtures

Inventory conclusions are metadata only, not proof of safe delivery isolation.
Do not submit a form, open a call/DNI test, run a queue or request live exports
until the following controlled isolation work is separately approved and proven.

| Existing route | Observed state | Required later isolation / pass evidence |
|---|---|---|
| F and another imported form CRM feeds | enabled; F mentions submission_id and receipt binding | CRM Ops must durably intercept/buffer before any contact overwrite. Preserve payloads and map; prove no projection during hold. Do not just disable the feed and call holds tested |
| Form email notifications | five enabled across those two forms; one other disabled | Snapshot selectors locally, suppress or redirect only test routes to a proven non-delivering capture sink; verify transport capture and no external recipients. Include admin/autoresponse, confirmations and reports |
| Imported Google Sheets form feed | enabled on the other form | Disable scoped test route or replace with isolated sink after authorization; no Golden/live Sheet reuse |
| Form custom JS / analytics / callbacks | custom JS exists; destinations not exhaustively audited | Review test page code and HTTP handlers before browser submission; prevent analytics/Ads/other external events; do not call a staging label isolation |
| Ads exporter | settings/export families disabled; no Ads export WP cron observed | Preserve disabled state. No owner crontab found and no Ads job in readable system cron; root-user/panel/other-user schedules remain unverified. Obtain privileged read-only inventory before acceptance |
| FluentCRM scheduled work | minute/five-minute/hourly/daily/weekly hooks active | Coordinate test-scoped holds with all workers and handoff boundaries. Current zero campaigns/funnels/contacts does not prove future sends cannot occur |
| Action Scheduler | three pending actions: recurring scheduler, CRM daily, CRM minute | Snapshot counts/hooks and claimed/in-progress work; quarantine/cancel only authorized test jobs through owning API. Do not drain/replay queues or delete SQL rows to start clean |
| Call Bridge | active, external config reference present, routes and se_cb_gc scheduled | Call Bridge owner must verify external-call/DNI isolation; do not read credentials, invoke Twilio or assume passthrough/provider safety. Form-only Ads acceptance must not exercise call routes |
| Consent / Shield / platform cron | cleanup/update hooks active | Keep unrelated maintenance intact unless a test dependency is diagnosed. Do not blanket disable WordPress cron or other CRM automation |

Success evidence: scoped before/after configuration fingerprints, destination
classification without secrets, transport capture counts, zero external sends/
Sheet appends/Ads events, no claimed worker crossing the hold. Unknown system
cron, web PHP, callback destinations or Call Bridge external behavior is a stop
gate; resolve by focused owner review, not a broad shutdown.

## 3. CRM Ops implementation gate and ownership

Current CRM Ops source/status was refreshed read-only: local AdoptionCoordinator,
RecoveryCoordinator, InquiryStore, MysqlSchema, acknowledgment validator and
synthetic tests exist. Bootstrap remains inert, with no real Ads adapter calls
or WordPress add_action/add_filter writer-hold integration. Reported 144 checks
are historical; do not label them installed WordPress acceptance.

Before execution CRM Ops must provide source revision/hash list, tests and a
writer inventory proving these behaviors:

1. Acquire a durable per-contact hold before incoming repeat submission/feed
   replacement can mutate submission_id, lifecycle, dates, revenue or identity.
   Capture the old canonical binding and adopt it first. Persist the new form
   event while held; neither lose it nor apply it early.
2. Enforce the hold at CRM edit APIs, direct supported field/tag paths, form
   feeds, queued callbacks, worker claims and final delivery/projection boundary.
   Account for a worker already in flight when the hold begins. A pending_id
   in a local service does not by itself stop external FluentCRM writers.
3. Resolve/generate legitimate operational inquiry IDs and site identity using
   CRM Ops' supported integration. Missing legacy identity requires review;
   do not INSERT a head by hand or synthesize an Ads-owned operational ID.
4. Persist adoption/prepare/activation complete bodies and event IDs before each
   adapter call. Store request/body hashes, durable phases/outbox and evidence
   sufficient to replay after a fresh process. Stable request key/body replays
   must converge; changed bodies must conflict.
5. Freeze adopted head v in prepare expected_head_version. After matching ready,
   archive/new-head CAS and saved activation body commit atomically; activation
   head_version=v+1. Reservation bookkeeping cannot rewrite frozen v.
6. Invoke all three real same-site PHP adapters outside CRM Ops SQL transactions.
   No cross-plugin transaction is promised. Neither plugin writes the other's
   tables. Ads named locks cover cooperating Ads calls, not arbitrary CRM writes.
7. Accept only the contract-defined matching activation acknowledgment for the
   persisted site/contact/previous/proposed/reconciliation/event/current-head
   identity and consecutive version. Do not demand undocumented response fields:
   correlate supported echoed fields to the saved request/site state. Re-read
   current head/hold atomically at release/delivery eligibility boundary.
8. Ready, an Ads checkpoint, adoption success and historical prepare/activation
   receipts cannot enroll a superseded inquiry. Reject stale head/site/body,
   mismatched event or reconciliation, and retain holds after errors/crashes.
9. Only after matching current-head committed and explicit integration eligibility
   checks may the fixture's logical gate advance. Delivery remains disabled in
   this acceptance scope. Current prototype intentionally keeps pending_id/edit
   hold after acknowledgment; an approved release protocol is still missing.

SiteEngine Base owns shared facts; Consent owns authoritative receipt capture
and stamping; Fluent Forms owns entries/feeds; Call Bridge owns calls; CRM Ops
owns orchestration/holds/identity/delivery; Ads owns attribution/reset/export
history. Do not patch vendor FluentCRM or implement CRM Ops inside Ads Bridge.

## 4. Ordered acceptance with two inquiries for one contact

Preconditions: isolated controlled site, above setup accepted, real installed
FluentCRM behavior verified, CRM Ops gate passed, authorized fixtures and test
harness supplied. No existing wp se-crm command, REST adapter, retry worker,
Start New Inquiry toggle, cancellation or compensation is assumed to exist.
The trusted harness must use the three documented PHP adapters; payload examples
in CRM-OPS-INQUIRY-CONTRACT.md are shape references only.

Allocate actual contact C; entry A and entry B in F; distinct receipt RA/RB;
CRM Ops identities IA/IB and stable site/event/request IDs. Maintain a protected
local evidence map. Save complete phase bodies securely; publish only redacted
metadata/hashes. No customer identities or raw plans belong in Git.

| Stage | Ordered action after later authorization | Required pass evidence | Failure / recovery |
|---|---|---|---|
| T0 Baseline | Snapshot versions, payload hashes, actual prefixes, config provenance, schema/types/indexes, counters and isolation | Preflight 0; correct site/owner/web+CLI runtime; no unknown outbound routes; test harness revision and zero-send guard recorded | Stop before fixtures; resolve missing prerequisites without weakening gates |
| T1 First entry | Submit A through actual isolated receipt/stamping path; CRM Ops establishes C/IA/current head v with legitimate canonical binding | Stored entry A links authoritative RA/current policy; ff:F:A computed by Ads; actual identity/click capture recorded; no copied CRM consent used | Missing/unstamped/wrong-policy receipt blocks advertising; do not backfill historical consent |
| T2 Existing Won | Through approved fixture path, reach Qualified then Won for IA with controlled actual fixture invoice amount/currency/date | Unique lifecycle; durable qualified_at, closed_date/value; unrelated field/tag preserved; expected head v and old source durably captured | Retain hold on malformed dates/value/source. Synthetic invoice facts are confined to declared test records, never fabricated for client records |
| T3 Adopt before replacement | Hold C before repeat feed; call adopt with persisted IA/v/source A; replay identical adoption | status=adopted; one active Ads IA with ff:F:A and old milestones; exact replay unchanged; conflicting identity/body fails | If original head/source unresolved, stop for CRM Ops review. No invented identity, pending clear or mutable-source reconstruction |
| T4 Receive B held | Persist entry B with distinct RB and same contact identity; queue its intended projection behind hold | Entry B durable, old current CRM submission still A, IA immutable; delayed/queued edit attempt suppressed with evidence | Any early overwrite or in-flight bypass FAILS hold integration; keep hold and repair CRM Ops before proceeding |
| T5 Prepare | Persist/call prepare(previous IA, proposed IB, expected v, source B) outside transaction; replay exact body | ready plus stable reconciliation; IB prepared with ff:F:B; IA still active; one pending reservation; no CRM reset on prepare | Missing adoption/schema, contention or competing pending event => hold/retry; second body/event must not take reservation |
| T6 CRM head CAS | CRM Ops commits archive/new-current with expected v/reservation checks and stores exact activation body for v+1 | One current IB, durable phase/body/site/event correlation, hold retained; failed CAS leaves no half-switched CRM head | Stale version/current => no call to commit; rollback local transaction and investigate. Never rotate IDs to bypass |
| T7 Ads commit | Call matching activation outside CRM Ops transaction; inspect/read back projection and inquiry pointers | committed for matching current head; IA archived preserving A; IB active/New with B; new submission set; old dates/revenue/reason/export fields cleared; unrelated fields/tags/CRM Ops identity preserved | Any reset/readback/pointer failure => no acknowledgment and hold retained; follow recovery matrix |
| T8 Ack boundary | Drop response, restart harness, replay exact body; inject wrong event/site/version/reconciliation and superseded-head receipt cases | Exact replay converges with no repeated CRM reset; wrong/superseded acknowledgment never releases eligibility; current-head recheck at boundary; zero sends | Lost response uses same IDs/body/new request. Historical committed receipt is evidence only, never enrollment permission |
| T9 Immutable attribution | Inspect both Ads rows and build/select reached stages without changing bindings | Exactly IA archived/A and IB active/B for C; different ff transactions and receipt resolution; IA unexported reached stages remain candidates after B replaces contact submission | Any fallback/rebinding to current B FAILS; stop for diagnosed source/contract review, no ledger clearing |
| T10 Consent matrix | Main pair RA granted, RB denied; separate fresh two-inquiry pair with both granted and differing personalization; variants missing/stale receipt via new declared fixtures | A advertising eligibility follows RA; B follows RB independently; denied/missing/stale receipt excluded; ad_personalization emitted independently; internal All_Leads history retained | Never mutate RA/RB to manufacture a pass, reuse Golden receipts or infer advertising consent from subscription status |
| T11 Current DNC | Under explicit fixture-write scope add C to current DNC, inspect both inquiries' eligibility; remove only that test membership if approved | Current DNC suppresses both archived/current advertising candidates despite earlier grants; internal history remains; removal restores only otherwise eligible stages | Any DNC escape FAILS; preserve evidence and stop exports. Do not alter real DNC memberships |
| T12 Stage/ledger replay | In both-granted paired fixture reach B Qualified; build Qualified/Converted for A and Qualified for B. Later test-sink delivery is optional separately scoped work | One ff transaction can appear once per conversion action; independent Qualified/Converted sinks, no stage starvation; 21/94 header/row widths; no Inquiry ID in Google header | No live append here. Actual append/ledger failure/dedupe tests require a disposable sink and explicit apply authority; no exactly-once cross-system claim |
| T13 Close | Save redacted report and protected fixture map; leave delivery off and pending failures held | Version/hash/step-by-step counters and responses, evidence of no external writes, final one-current-head state, all fixture changes accounted for | Do not delete history/ledgers/receipts to tidy a failed run. Cleanup needs approved fixture-specific plan |

The main sequence uses exactly two inquiries per contact. Consent/negative and
crash cases use fresh independent paired fixtures or approved disposable
snapshots. Do not create a third inquiry or edit immutable history to reuse a
case. Cases needing a future head to prove supersession may simulate the stale
response at the boundary without activating another inquiry.

## 5. Failure injection, recovery and evidence

Fault injection requires later authorization and an isolated harness/environment;
never corrupt a shared site's plan/table or patch vendor code to create a case.
At each injection record phase/body hash, expected/current head, row states,
checkpoint presence/hash, CRM write counts, acknowledgment, held writer events,
transaction/lock outcome and transport capture counts. Do not print raw plan.

| Failure window / condition | Expected pass condition | Permitted recovery after explicit test authorization |
|---|---|---|
| Before adoption/prepare acknowledgment; lost response | Old source/head remains held; exact stored body/event survives restart | New request replays same phase/body; response correlation before progression |
| Schema option missing or physical recovery column/type wrong | retry / inquiry_schema_migration_required before contact writes | Retain hold; separately authorize explicit migration and physical readback, then identical retry |
| Contact lock contention / reentrant call / lost session | busy/unavailable/reentrant retry, no successful projection acknowledgment | Wait for legitimate owner completion; retry identical body in fresh request; no manual lock-kill workaround |
| Process loss after activating plan persistence, before/mid-reset | Plan/hash durable; prior Ads head remains active; only original-to-intended remaining work permitted | Retry same activation after writer hold recheck; assert completed field/tag changes are not resubmitted |
| Reset completes but checkpoint persistence fails/lost process | No committed acknowledgment; actual intended projection may already exist | Same-body retry verifies real fields/tags and saves checkpoint without repeating writes |
| Checkpoint exists, pointer transaction fails/rolls back | Original Ads head retained; checkpoint durable; no acknowledgment | Verify unchanged projection, retry inquiry pointer commit only; CRM write count remains zero |
| Third field value/unexpected lifecycle/config change/corrupt plan | projection_recovery_conflict / projection_plan_configuration_changed / projection_plan_corrupt; hold, no further reset | Operator-specific investigation; restore expected state/config only with reviewed permission; identical retry, no ID rotation |
| Drift after saved checkpoint | projection_checkpoint_drift; no CRM writes and no pointer switch acknowledgment | Keep hold; review the external writer before any corrective action |
| Legacy activating row has no plan | legacy_activation_projection_unknown; cannot infer former state | Escalate operator recovery design; no automated clear/cancel/rollback available |
| COMMIT/ROLLBACK/release unconfirmed or DB session unusable | No success; failed session discarded; no further transitions in same request | Resolve DB fault and use fresh request/connection with identical phase/body; never acknowledge from an in-memory result |
| CRM head/hold changes while response in flight | Old receipt cannot authorize current/superseded enrollment | Re-read/CAS current head at ack and final eligibility boundary; retain hold and reconcile durable phase |
| Queued/in-flight feed/edit/send crosses hold | Test fails even if Ads locks and isolated suites pass | Keep controlled scope isolated; CRM Ops owner fixes writer interception; rerun fresh paired fixture |

Final acceptance requires all cases designated for this execution scope to have
fresh pass evidence, zero unauthorized sends/appends and reviewed remaining
gaps. Pointer transactions, CRM projection and remote Sheet delivery are not
one atomic transaction. Recovery finishes the accepted activation; it does not
restore the old CRM projection or abandon a reservation.

## 6. Permitted PHP validation route and fresh results

Do not repeat the rejected full-checkout-to-Alma transfer, disguise it, split it
into chunks, or clone a second editable master as a workaround.

Permitted route used on 2026-10-26: verify the already-installed original 0.6.3
payload against its original manifest, then run its existing files in place as
the site owner using the established CLI PHP binary. Transfer command text only,
not source/archive/documentation. Do not bootstrap WordPress or the native SQL
suite. Inspect included tests first for filesystem/network/database side effects.
Disable CLI opcache/file cache; do not copy files or set up a new runtime.

Fresh results on PHP 8.3.36: 23 PHP files lint clean; ten isolated suites below
352 PASS / 0 FAIL. No WordPress bootstrap, DB test, deployed change or live
contact transition ran. One shell invocation was interrupted without results;
a subsequent invocation completed lint but had a shell syntax error before
suites. The corrected suite command exited 0 and produced the results below.
Do not count those incomplete invocations as suite passes.

| Existing installed suite | PASS | FAIL |
|---|---:|---:|
| config-settings-acceptance.php | 10 | 0 |
| inquiry-acceptance.php | 43 | 0 |
| inquiry-recovery-acceptance.php | 46 | 0 |
| inquiry-transition-acceptance.php | 50 | 0 |
| lifecycle-acceptance.php | 18 | 0 |
| static-acceptance-040.php | 17 | 0 |
| static-acceptance-050.php | 24 | 0 |
| static-acceptance-051.php | 23 | 0 |
| static-acceptance-060.php | 36 | 0 |
| static-acceptance.php | 85 | 0 |

These validate the original installed copy, not the seven neutralized public
checkout files, native SQL, WordPress dbDelta, real Subscriber persistence,
web PHP, writer holds or live two-inquiry acceptance. The installed FluentCRM
3.2.6 source now shows selective blank deletion, serialized comparison and both
custom-data hooks; functional persistence still needs the controlled live test.

Native SQL suite remains NOT RUN: it requires the dedicated database named
seab_inquiry_acceptance, PDO mysql, SEAB_TEST_DSN/DB_USER/optional DB_PASSWORD and
SEAB_TEST_ALLOW_DB_WRITES=1. It creates temporary tables and writes. Never use a
WordPress database or its credentials. Provisioning/running it requires separate
specific authorization; a SKIP is not a pass. Future validation of sanitized
Windows tests can use a separately approved local PHP installation/environment;
none was installed in this assignment.

## 7. Proposed execution scopes — none granted by this document

1. **Isolation/setup only:** approve the exact local selector list, scoped
   notification/feed/call/analytics and worker isolation, a proven mail capture
   boundary, supported settings save, missing terms/fields, explicit Ads inquiry
   and ledger migrations, site runtime confirmation and backup/readback plan.
   Preserve unrelated automation. No submissions, transitions or exports yet.
2. **CRM Ops implementation/review:** assign its own writer the WordPress
   hold/adopt-before-replace/real-adapter/ack-release integration, source tests,
   supported schema and worker/crash evidence. This is not Ads feature work.
3. **Fixture acceptance:** after scopes 1/2 pass, authorize a finite synthetic
   contact/entry/receipt/inquiry budget, exact adapter calls and fixture edits,
   fault-injection boundaries and private evidence capture for T0-T13. Keep
   customer delivery disabled. Approve any dry-run export separately; do not
   assume read-only export flags authorize the settings needed to enable it.
4. **Optional disposable-sink/native SQL cases:** separately authorize any
   test-only Sheet provisioning/appends/ledger writes or dedicated test database.
   No Golden destination, production credential or AWS promotion is implied.

Immediate next task is scoped Alma isolation/setup approval plus the CRM Ops
integration assignment. Live transitions remain blocked until their prerequisites
are implemented and accepted. This runbook/status commit changes documentation
only; accepted Ads runtime/version, original ZIP and sites are unchanged.
