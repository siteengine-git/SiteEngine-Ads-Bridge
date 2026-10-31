# Joint J0â€“J7 operations â€” review package, installed cases NOT RUN

Assignment ALMA-JOINT-CONSENT-ADS-20261031. Alma Codex is sole server executor
and cleanup owner; Aubrey is proposed browser operator. J0 remains independently
released by the October31 coordinator release. J1â€“J6 require that coordinator's
review of this package, actual inputs and Consent-owned capture/fault review.
This document supplies executable operations, not permission to run them now.

Ads runtime source2846305d19d550997295f85e58c7312b736925bd,
handoff409df8334a2715fe8b0d0603d174872f84a84304,0.6.3.1-rc.1 ZIP
34d75e77285aa1e6b8441e0a9f1f766e614150b533423549ce03a91cbd71b72f.
Consent0.6.1-rc.2 implementation1c0a42000927db720cfc60934ea5e2b31db28533,
docs b8ceb8c292ff3a198bd34548ec2e5a2d13afe98f, ZIP
8471e4761eef927bb10d44967ab88205ef0890a3b6bf90888b8d1ef2c182b619.
Frozen observer SHA256
bff8339e0a3faff075069f84e1f62e1e64eab4afb9b616d81bb8a851f6470f48.

## Admission and actual inputs

Alma's J0 covers current nonoverlap, target/owner, package verification, private
targeted backups, immediate scoped outbound/worker/cache controls, primary routing,
autocommit and all-three Consent InnoDB/index checks. Consent normal upgrade first,
Ads second; no force upgrade/reactivation. Record original configuration/schema,
table totals, cron/GC/queue state without message contents. Bootstrap itself must
be covered by those controls. Hooks do not fence direct-provider/shared workers.
No unbounded mail scan or broad infrastructure investigation is required here.

Alma records private J0-result.json: assignment,stage:"J0",status:"PASS",actual
evidence references. Its independent SHA256 enters allocation.json. Fill ALL
allocation.template.json placeholders from J0: UID, prefix/tables, master-form ID,
selectors, actual AJAX URL and observed form-ID POST token, configured lifecycle
and DNC IDs, fixture dates/site_instance_id. No ID is predicted. Logical reference
map includes the five mandatory edges plus ALL site-specific form/CRM/job links;
reference_map_reviewed must be true. Unknown/cross-database/foreign edges stop cleanup.

Existing se_consent_receipt_id hidden input and its actual sanitization path must
be confirmed by Consent's owner. marker_fields must contain exactly TWO distinct
input_hidden objects exported/reviewed from this installed Fluent Forms builder,
with names se_joint_marker and se_joint_case and actual unique indices/settings.
No guessed builder schema, new form, terms or fields. The form operation makes
one guarded update adding these two; close makes one guarded exact-byte restore.
Receipt field unchanged. Persistent global config/activation edits ZERO.

Two owned model-created contacts use marker.a@example.invalid and marker.c@example.invalid.
Each receives one milestone update (at most7 custom keys, fictional125.00,
allocated dates), configured lifecycle tags and source tag; only these contacts.
DNC on/off is one attach/detach pair. A's CRM submission pointer changes Aâ†’Bâ†’A
with readback; one owned inquiry A is archived/restored for a fixture probe.
These are not CRM CAS/hold/activation-ack acceptance. No CRM workflow is invoked.
Adoption uses the real trusted adapter with exact IDs/payload durably saved first.

Ceilings unchanged: one form, one browser chain,2 contacts,10 receipts/links,
one head/10 revisions,10 submissions with owned details,4 inquiries. Normal flow
uses2 contacts,3 submissions,2 inquiries,4 real captures. The exact sequence below
uses64 server commands; no spare probes/retries. Each request18s +1s TERM/1s KILL,
aggregate64KiB output, journal1MiB, reference count512/edge, FK metadata65 max.
Browser actions15s, session10min, captures/submissions each10 max. Unknown process
cessation, missing output/JSON or any failure stops; retain journal and controls.

## Package, release and owner shell

Stage non-overwriting outside public_html in the assigned run/harness directory.
Verify independent ZIP/manifest pins and EVERY member before executing a script.
Use existing owner route only, owner/private directory0700 and files0600; never
install this package as a plugin or MU plugin. Review WP-CLI synopsis first.

Alma Helper supplies fixture-release.json with status:"RELEASED_J1_J6_J7",
allocation_sha256 and operations_manifest_sha256, plus independent hash in
fixture-release.sha256. It is not self-approved by these tools. allocation.json
stays immutable; its SHA is supplied to every invocation. J0 admit does not require
fixture release and creates no fixtures. In the existing Alma owner shell:

```sh
set -eu
umask 077
RUN=/home/alma.siteengine.space/.se-joint-acceptance/joint-consent-ads-20261031-01
OPS="$RUN/harness/source/tools/acceptance"
ALLOC="$RUN/allocation.json"
PIN='<actual independently verified allocation SHA256>'
j() { /usr/bin/python3 "$OPS/joint-run.py" "$@" "$ALLOC" "$PIN"; }
```

Before the first j(), use the independently delivered operations-manifest SHA256
(not a value read from the same unverified bundle) and standard-library verifier:

```sh
OPERATIONS_SHA='<reviewed OPERATIONS-MANIFEST.json SHA256>'
/usr/bin/python3 -I - "$OPS" "$OPERATIONS_SHA" <<'PY'
import hashlib,json,pathlib,sys
root=pathlib.Path(sys.argv[1]); raw=(root/'OPERATIONS-MANIFEST.json').read_bytes()
assert hashlib.sha256(raw).hexdigest()==sys.argv[2], 'manifest provenance'
m=json.loads(raw)
for name,digest in m['files'].items():
    assert pathlib.PurePosixPath(name).name==name
    assert hashlib.sha256((root/name).read_bytes()).hexdigest()==digest, name
print('PASS pinned operations members; no WordPress loaded')
PY
```

Runner invokes the established PHP8.3 /usr/bin/wp --path route. Only missing adds
--skip-plugins=siteengine-consent for that request; stored activation unchanged.
No Base-only filter, transport/export command or real token/Sheet ID is used.

## One real browser chain

Copy reviewed allocation bytes privately to Dell-8940 through the existing route.
Set actual $LocalOps, $PrivateAllocation and $Pin; run existing Node/Playwright:

```powershell
$env:NODE_PATH='C:\Users\admin\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\node_modules'
& 'C:\Users\admin\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe' "$LocalOps\joint-browser.cjs" $PrivateAllocation $Pin
```

Driver creates one fresh in-memory Chrome context, aborts cross-origin requests
before navigation, then accepts fixed ordered commands. No bearer/cookie/token
dump. Grant/withdraw/regrant/personal-off use installed SiteEngineConsent methods
and real acknowledged capture. It never supplies the receipt field. Real form
submission uses the allocated selectors and ordinary vendor AJAX persistence.
Required field inputs must be synthetic. Same-origin callbacks/server feeds still
need J0 controls, including relevant Call Bridge; no calls/provider tests.
Do not reopen/retry the chain after failure. The withdraw command wraps only that owned negative capture fetch: holds its
response pending (2s wait), rejects it, verifies failed/no receipt or ACK token,
restores the exact fetch reference, then deliberately retries the SAME event once
through retryAudit. No server capture occurred before restoration; the retry is
the specified fault case, never an automatic retry after an unexpected failure.

For each submit PASS line, save only that sanitized JSON as browser-A/B/C.json;
independently hash on the PC and transfer it with adjacent .json.sha256 through
the existing route, mode0600. browser-evidence binds receipt ID to persisted stamp.
Do not transfer full responses/storage. Retain the bounded zero-delivery ledger.

## Ordered executable matrix

Run server lines in this order; execute browser lines at the stated boundaries.
Stop at first unexpected result. Expected-denial passes require actual gates AND
zero synthetic append calls; worker exit0 alone is insufficient.

| Row | Exact operation (server j(); browser in open driver) | Evidence |
| --- | --- | --- |
| J0 | `j admit - -` | independent J0 receipt, actual packages/primary/engines/form/terms, collision-free marker |
| J1 | `j begin J1 -`; `j form - -`; `j contact A -`; `j contact C -` | guarded fields, actual returned contacts |
| J1 browser | `grant`, `submit A`; transfer browser-A receipt | real rev1 ACK, real form response, advertising payload |
| J1 | `j attest A -`; `j browser-evidence A -`; `j milestone A -`; `j adopt A -` | real signed response/link/head, immutable inquiry binding |
| J1 | `j probe A allow`; `j prepare A -`; `j dispatch A allow`; `j stage J1 -` | actual browser/builders, frozen body, synthetic item1 |
| J2 | `j begin J2 -`; `j fault A pending`; `j fault A failed`; `j fault A null`; `j fault A malformed`; `j fault A unknown`; `j fault A unsigned` | request-only canonical read faults deny; history unchanged |
| J3 browser | `withdraw`, `submit B`; transfer browser-B receipt | same-chain real rev2, B payload absent |
| J3 | `j begin J3 -`; `j attest B -`; `j browser-evidence B -`; `j rejection B -` | real negative canonical B, not rebound to A |
| J3 | `j dispatch A deny`; `j dispatch A deny`; `j probe A deny`; `j stage J3 -` | batch item2 and queued exact-body retry deny; frozen A unchanged |
| J4 browser | `regrant`, `submit C`; transfer browser-C receipt | real rev3 advertising/personalization grant |
| J4 | `j begin J4 -`; `j attest C -`; `j browser-evidence C -`; `j milestone C -`; `j adopt C -` | second actual owned contact/inquiry |
| J4 | `j probe A deny`; `j probe C allow`; `j prepare C -`; `j dispatch C allow`; `j stage J4 -` | old A stays withdrawn, new C permits synthetic append |
| J2 completion | `j begin J2 -`; `j fault C negative`; `j stage J2 -` | real B negative plus conflicting grant in request view; no signed positive fabrication |
| J5 | `j begin J5 -`; `j missing C -`; `j fault C unavailable`; `j stage J5 -` | actual absence/null-error reads; browser/builders/dispatch deny |
| J6 | `j begin J6 -`; `j policy C -`; `j dnc-on C -`; `j probe C dnc`; `j dispatch C deny`; `j dnc-off C -` | config restored; DNC builders/dispatch deny, existing consent-only browser behavior unchanged |
| J6 | `j rebind A B`; `j archive A -`; `j probe A deny`; `j unarchive A -`; `j rebind A restore` | immutable A despite changed contact pointer; active C remains separately bound |
| J6 browser | `personal-off` | real rev4, advertising retained, personalization barrier advances |
| J6 | `j probe C allow`; `j dispatch C deny`; `j prepare-effective C -`; `j dispatch-effective C allow`; `j stage J6 -` | old Granted body rejected; separate Denied body permits;21/94 columns |
| J7 | `j begin J7 -`; browser `close`; `j close - -` | reference checks first, exact-ID cleanup and form restoration |
| J7 owner | restore only scoped controls after owned jobs ceased; supply closure receipt below | actual zero-delivery/state comparisons |
| J7 | `j stage J7 -` | all earlier evidence PASS and owner closure verified |

J2 is intentionally split: genuine B negative first requires J3 real capture.
The withdraw command runs the real pending/failed ACK fault before successful B,
without adding a chain/event. browser-evidence B requires its sanitized verified
flag. PHP pending/failed faults separately test Ads gates against request-local
evidence; neither fault manufactures positive evidence.

## Fault scope, recovery and closure

Fault changes only a returned owned response: pending/failed state, null, scalar,
unknown contract999, absent key or actual B negative; a conflicting flat grant
remains. J5 unavailable returns null+last_error on receipt SELECTs. Policy changes
only in-process Config required policy. Finally restores WPDB/config/last_error;
stored response SHA rechecked. No SQL evidence/source/options edited. Missing API
uses request-only skip and fails if another loader still provides it.
Every normal fault starts with genuine positive builders/browser, then requires
denial and operational All_Leads usability. Actual google_sheets_append_row is
intercepted by pre_http_request with synthetic response only, no network append.
CLI WP HTTP/mail are short-circuited; direct SDK routes require existing J0 controls.

Before mutations journal stores pending intent, contact email, original custom/
tags, form bytes/hash, exact adoption IDs/body, state/pointer before/after and cleanup
IDs. Crash/timeout leaves pending; failure leaves stopped/runner-stop. Never replay,
rotate IDs or automatically clean. Alma compares only exact owned readbacks and
returns the precise recovery requirement while retaining controls. No table purge.

close checks original responses, contact emails, immutable audit/link digests and
nondecreasing barriers, actual FK metadata and reviewed logical refs BEFORE deletes.
Foreign/unknown references retain resources. It restores exact guarded form bytes,
checks activation unchanged, removes only journaled inquiry IDs, entries+their
details, contacts+meta/pivots, then verifies all absences. Consent receipts/links/
head/barriers NEVER deleted; IDs and Consent retention owner remain recorded.
Private evidence/backups retained; no whole-DB restore or historical repair.

Alma supplies closure-owner.json and independently verified .sha256 with assignment,
external_deliveries:0,pending_owned_jobs:0,scoped_controls_restored:true,
cleanup_owner:"Alma Codex",audit_retention_owner:"Consent",actual baseline/after
configuration/schema/cron/GC/table comparisons and zero-delivery evidence references.
This is an owner attestation, not fabricated measurements. J7 refuses missing
earlier stages/closure. Stages are PASS/FAIL/NOT RUN; later stages remain NOT RUN
on failure. Retain controls if any owned worker/operation outcome is uncertain.

## Copyable handback through Aubrey

Consent owner: review this package pin and the actual installed receipt hidden-field
template/sanitizer/confirmation shape. Confirm same-chain saveConsent/rejectNonEssential,
ACK matching and permanent B/A barriers, request-local negative/unavailable/missing
faults, and audit retention at J7. Review joint-browser.cjs withdraw's exact
pending→failed→restored-fetch→explicit same-event retryAudit implementation against
your existing consent-browser.js beginWithdrawalFault/failAcknowledgement/
restoreFault/retryWithdrawal. Confirm no extra event/chain, ACK match and no positive
evidence fabrication. Return reviewed package hash and exact input corrections
through Aubrey. No Consent source edits requested. Alma supplies actual J0 inputs,
reference map and fixture/control release; J1–J6 remain NOT RUN until that review.

## Local validation only (completed)

From the canonical Ads source checkout, existing PHP8.3.36 with -n executes
`tests/joint-operations-acceptance.php` (offline doubles only); lint that file and
`tools/acceptance/joint-ops.php` with -n -l. Existing Python executes
`tests/joint-run-acceptance.py` (pure argv/runbook/AST tests, no subprocess);
existing Node --check tools/acceptance/joint-browser.cjs checks syntax only.
39 PHP assertions +6 Python tests,2 PHP lint and1 JS syntax check PASS. These do
not prove Linux process groups, actual SQL/vendor persistence, browser capture,
installed fault behavior, primary current-read or J0–J7 acceptance. Candidate
regressions were not repeated. No server command or browser was executed locally.
