# Integrated joint J0–J7 operations — local tooling, installed NOT RUN

Alma Codex is sole server executor/cleanup owner; Aubrey is the proposed browser
operator and communication route. J0 proceeds independently under its recorded
release. J1–J6 remain UNRELEASED until Alma reviews THIS integrated package.
No deployment, WordPress bootstrap, installed fixture, export or server command
was run during this local integration. Prior r2 ZIP and both runtime ZIPs retained.

## Exact dependencies and admission

Ads runtime/package source2846305d19d550997295f85e58c7312b736925bd,
ZIP34d75e77285aa1e6b8441e0a9f1f766e614150b533423549ce03a91cbd71b72f.
Consent rc.2 implementation1c0a42000927db720cfc60934ea5e2b31db28533,
docs b8ceb8c292ff3a198bd34548ec2e5a2d13afe98f,
ZIP8471e4761eef927bb10d44967ab88205ef0890a3b6bf90888b8d1ef2c182b619.
Consent operations ae2fe50dcaaa18d581ea41c06b14db3ac68f1e47, copied EXACTLY under
consent-pinned/ with original manifest SHA256
0798f8f605b97afd03bbc41f7dd81068af9c5af7de89480510e941781058ab51.
Its CONSENT-OPERATIONS.md is incorporated for context/fault/retention semantics.
The retained read-only observer bff8339e0a3faff075069f84e1f62e1e64eab4afb9b616d81bb8a851f6470f48
is unchanged; only its provenance/functions are reused. No competing Consent
capture or fault implementation remains in this driver.

J0 remains Alma's independently released package/target/nonoverlap/backups/controls,
Consent-then-Ads installation and actual primary/autocommit/all-three-InnoDB/index
checks. Capture original configuration/schema/cron/GC/queue/table totals without
message contents. Scope controls to reachable fixture paths including feeds,
direct providers, delayed/shared workers, browser/Call Bridge callbacks and cache.
No general infrastructure or mail-scan task is introduced by this package.
The CLI HTTP/mail short circuits are not coverage for ordinary browser requests.

Alma supplies actual UID/prefix/table names/master form/terms and dates, exact
existing hidden receipt input, supported selectors, AJAX URL/form-ID POST token,
and browser.response_id_path: an ARRAY of actual property names locating the ID
in THAT vendor submission response. There is NO default path or sample ID.
If J0/vendor cannot provide a correlatable ID, STOP with that exact admission gap;
no MAX/latest/marker search may substitute for ownership. Collision checks before
creation are checks only, never an admission/discovery mechanism.

J0-result.json (assignment,stage:J0,status:PASS,evidence references) is independently
hashed into immutable allocation.json0600. Logical reference map must be owner
reviewed and complete before admission; mandatory edges are supplied in template.
Fill all template placeholders from actual site evidence; it deliberately refuses
unfilled inputs. Fixture-release.json0600 must contain status:RELEASED_J1_J6_J7,
allocation_sha256 and operations_manifest_sha256; its SHA is supplied independently.
Nothing here self-approves that release. Directory0700 and files0600/current owner.

## Exact edits and bounds

One form/one browser chain; ceilings2 contacts,10 receipts/links/submissions,
one head/10 revisions,4 inquiries unchanged. THIS sequence uses exactly3 genuine
receipts/links and one head at revisions1/2/3,3 vendor submissions A/B/C,2 contacts,
2 persisted Ads inquiries A/C. All3 Consent receipts/links/head/barriers retained.
No fourth personalization capture. A's positive prepared body is personalization
Denied; C's is Granted while advertising eligible in each respective capture.

marker_fields are TWO distinct input_hidden definitions exported/reviewed from
this installed Fluent Forms builder with actual unique indices/settings, names
se_joint_marker/se_joint_case. One guarded form update adds them; one exact-byte
restore removes them. Existing receipt field unchanged. No global config/activation
edits, new forms/terms/fields or runtime changes. One milestone edit per owned
contact, <=7 custom keys, allocated fictional dates and125.00. Only configured
lifecycle/source tags on owned contacts; DNC one attach/detach pair.

One contact A's mutable submission pointer moves A→B→A. A's persisted inquiry
binding stays A; B's negative legacy builder path explicitly uses canonical B and
no inquiry A substitution. B's actual denied builder row is retained only as a
DENIED candidate for a boundary refusal test, never approved preparation. Archive/
restore only owned inquiry A's state for the reached-stage probe; no CRM head/CAS,
workflow/activation acknowledgment claim. Contact subscription status never grants
advertising and advertising permission never authorizes email; email policy unchanged.

64 finite server commands; each18s work +1s TERM/1s KILL on its own fresh process
group,64KiB aggregate output,1MiB journal, bounded details128/submission and512
references/edge,65 FK rows max. Browser10min total/15s action; real controller's
owned ACK-fault timer20s is its safety backstop. Never enlarge limits/retry after
unexpected failure. Pending/stopped/unknown process state retains controls/evidence.

## Package and supervised command interface

Alma stages the independently hash-verified package non-overwriting in the private
assigned run/harness directory outside public_html, using existing owner route.
No plugin/MU installation or new access setup. Verify the final ZIP/member manifest
from independently delivered hashes before executing any bundled script; review
WP-CLI synopsis. Standard-library member verifier after staging:

```sh
set -eu
umask 077
RUN=/home/alma.siteengine.space/.se-joint-acceptance/joint-consent-ads-20261031-01
OPS="$RUN/harness/source/tools/acceptance"
OPERATIONS_SHA='<independently reviewed OPERATIONS-MANIFEST.json SHA256>'
/usr/bin/python3 -I - "$OPS" "$OPERATIONS_SHA" <<'PY'
import hashlib,json,pathlib,sys
root=pathlib.Path(sys.argv[1]); raw=(root/'OPERATIONS-MANIFEST.json').read_bytes()
assert hashlib.sha256(raw).hexdigest()==sys.argv[2]
for name,pin in json.loads(raw)['files'].items():
    p=pathlib.PurePosixPath(name)
    assert not p.is_absolute() and '..' not in p.parts and not (root/name).is_symlink()
    assert hashlib.sha256((root/name).read_bytes()).hexdigest()==pin,name
print('PASS pinned members; no WordPress loaded')
PY
ALLOC="$RUN/allocation.json"
APIN='<independent allocation SHA256>'
JPIN=-
RPIN=-
j() {
  RESULT=$(/usr/bin/python3 "$OPS/joint-run.py" "$@" "$ALLOC" "$APIN" "$JPIN" "$RPIN") || { printf '%s\n' "$RESULT"; return 1; }
  printf '%s\n' "$RESULT"
  NEXT=$(printf '%s' "$RESULT" | /usr/bin/python3 -c 'import sys,json;print(json.load(sys.stdin)["journal_sha256"])')
  ACTUAL=$(sha256sum "$RUN/journal.json" | cut -d ' ' -f1)
  test "$NEXT" = "$ACTUAL" || return 1
  JPIN=$ACTUAL
}
```

Initial admit uses '-' journal/release pins only and no fixtures. After admit,
set RPIN to Alma's independently reviewed fixture-release SHA before first J1
command. Every later operation carries APIN/JPIN/RPIN explicitly. The launcher
checks pins BEFORE attempt creation/spawn; PHP rechecks under the owner/private
operations.lock BEFORE pending intent. Stale pins do not rewrite the journal.
After every operation j() compares the child pin to an independent file hash and
updates JPIN; refresh it after every admission too. No pin is read as self-approval.
Copy that current journal privately to the browser PC and transmit JPIN before each
browser action; never serve the journal/receipt files from the website.

Every fault calls scj_context in the existing supervised PHP child, with the shared
lock held and durable pending intent storing all three input pins. scj_with_fault
receives the ORIGINAL admitted journal and passes a projected COPY only to the
actual Ads consumer/browser/builders/synthetic-dispatch callback. scj_snapshot
and real API prove historical hashes and positive baseline before injection.
No separate unsupervised Consent command, PHP process or authority stub is used.

## Browser controller and exact-ID handoff

Existing Dell-8940 Chrome + Playwright; no installation. After fixture release,
copy immutable allocation/release and current private journal through the existing
route. Set the actual local paths/pins and run:

```powershell
$env:NODE_PATH='C:\Users\admin\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\node_modules'
& 'C:\Users\admin\.cache\codex-runtimes\codex-primary-runtime\dependencies\node\bin\node.exe' "$LocalOps\joint-browser.cjs" $PrivateAllocation $AllocationPin $PrivateRelease $ReleasePin $PrivateJournal $CurrentJournalPin
```

Driver verifies independent files/controller hash, creates ONE fresh context with
service workers blocked, aborts cross-origin requests BEFORE navigation, loads
exact consent-browser.js and calls SiteEngineJointConsent.start. All capture,
withdrawal, pending/failure/restoration/retry, submit and admit actions delegate to
that pinned owner controller. Business/email fields come from allocated synthetic
inputs; driver never fills the receipt field. No raw bearer, token or cookie dump.

Each command ends with current independent JHASH, not a literal placeholder.
Driver reads/hashes the private current journal before every command; refresh the
PC copy after each server operation/admission before the next browser action.
submit A/B/C extracts the exact returned ID only through J0's actual response path,
checks confirmation transaction correlation and prints a sanitized JSON receipt.
Transfer that line as browser-A/B/C.json0600 and its PC-independent .json.sha256 to
RUN through the existing route. Set A_ID/B_ID/C_ID from that individual response.
attest validates THAT row's ID/form/marker/case/signature and matching browser
receipt, then freezes raw response/details/audit/link/head hashes. No scan fallback.
After server admission browser admit validates the exact returned ID against the
new independent journal. Only then may the driver reload the same page/context
and resume the pinned controller at that acknowledged/admitted boundary.

## Ordered executable matrix (64 server commands)

Server j() calls below run in order. Browser lines are commands to the open driver;
replace JHASH with the current independent pin. Stop on first unexpected result.

| Row | Server command or browser action | Required evidence |
| --- | --- | --- |
| J0 | `j admit - -` | independent J0 PASS, collision checks/provenance, no fixtures; set RPIN afterward |
| J1 | `j begin J1 -`; `j form - -`; `j contact A -`; `j contact C -` | two exact fields/contacts, originals journaled |
| J1 browser | grant JHASH; submit A JHASH; transfer browser-A receipt and set A_ID | real ACK rev1, individually returned vendor ID and confirmation |
| J1 | `j attest A "$A_ID"`; `j browser-evidence A -`; `j milestone A -`; `j adopt A -` | original signed row/details, receipt/link/head1, immutable inquiry |
| J1 browser | admit A JHASH with the new journal | exact returned A admitted, same context resume |
| J1 | `j probe A allow`; `j prepare A -`; `j dispatch A allow`; `j stage J1 -` | actual gates, unchanged prepared21 body, one synthetic item1; All_Leads94 |
| J2 | `j fault A pending`; `j fault A failed`; `j fault A null`; `j fault A malformed`; `j fault A unknown`; `j fault A unsigned`; `j fault A tampered` | real owner fault callbacks deny, normal positive readback in finally |
| J3 browser | withdraw JHASH; submit B JHASH; transfer browser-B and set B_ID | controller beginWithdrawalFault/failAcknowledgement/restoreFault/retryWithdrawal, SAME event, rev2; no extra capture |
| J3 | `j begin J3 -`; `j attest B "$B_ID"`; `j browser-evidence B -`; `j rebind A B`; `j rejection B -`; `j fault B conflicting-flat` | canonical negative B consumer/browser/builders/denied candidate dispatch; no A inquiry reuse |
| J3 browser | admit B JHASH | exact returned B admitted, same chain/controller |
| J3 | `j dispatch A deny`; `j dispatch A deny`; `j probe A deny`; `j consent A withdrawn`; `j consent B denied`; `j stage J3 -` | batch item2/queued exact-body retry blocked0 calls, immutable A; actual API reasons at head2 |
| J2 closure | `j stage J2 -` | all7 projections plus real B conflict/ACK failure evidence |
| J4 browser | regrant JHASH; submit C JHASH; transfer browser-C and set C_ID | actual capture rev3, independently returned C; no fourth receipt |
| J4 | `j begin J4 -`; `j attest C "$C_ID"`; `j browser-evidence C -`; `j milestone C -`; `j adopt C -` | real C row/details/audit/head, second contact/inquiry |
| J4 browser | admit C JHASH | exact C and current journal matched |
| J4 | `j probe A deny`; `j probe C allow`; `j prepare C -`; `j dispatch C allow`; `j consent A withdrawn`; `j consent B denied`; `j consent C eligible`; `j stage J4 -` | current head3; A withdrawn, B denied, C eligible; personalization differs independently |
| J5 | `j missing C -`; `j restore C -`; `j fault C unavailable-head`; `j fault C failed-current-read`; `j stage J5 -` | request-only skip then immediate NORMAL request positive restoration; head faults preserve historical verification and block all actual consumers/dispatch |
| J6 | `j policy C -`; `j dnc-on C -`; `j probe C dnc`; `j dispatch C deny`; `j dnc-off C -`; `j archive A -`; `j probe A deny`; `j unarchive A -`; `j rebind A restore`; `j stage J6 -` | policy/DNC restored; A immutable inquiry despite pointer B, active C vs archived reached-stage A; Denied A/Granted C personalization;21/94 |
| J7 | `j begin J7 -`; browser close JHASH; `j close - -` | reference checks FIRST, exact-ID cleanup/form restoration, retain audit/head/barriers |
| J7 owner | restore only scoped J0 controls after owned work ceased; independent closure-owner receipt below | actual zero delivery/baseline comparison evidence |
| J7 | `j stage J7 -` | earlier stages PASS, exactly3 retained audit links/head3, verified closure |

J2/J5/J6 active-stage attribution is set by those operation handlers; no extra
begin command is needed. The single withdrawal supplies J2 real ACK fault + J3
withdrawal; it is not repeated to satisfy stage names. B uses contact A; B builders
use that explicit legacy pointer, while A/C use actual persisted inquiry rows.
No positive B preparation or CRM activation/hold claim is invented.

## Fault semantics, restoration and failure retention

Consent owns all ten modes: conflicting-flat B; pending/failed/null/malformed/
unknown/unsigned/tampered A; unavailable-head/failed-current-read C. Only exact
owned response or exact chain current-read SQL is projected. No details/history
rows edited. The head modes first prove historical verified=true; receipt-read
failure is NOT counted as head-read coverage. Actual current consumer, browser,
Qualified/Converted and actual append boundary must deny before synthetic HTTP.
All_Leads remains available. Synthetic append uses no real Sheet/token/export flag.

scj_with_fault finally restores original WPDB/error, rehashes exact receipts/links/
response/head and performs original real current-permission readback on success
AND throwing callback. jx_consent_fault separately retains CALLBACK_FAILED versus
restoration VERIFIED/UNKNOWN_OR_FAILED in the durable pending journal. If readback
cannot complete, STOP; no cleanup/retry/control removal. Even a restored callback
failure fails the operation. Process loss leaves the preexisting durable intent.

missing uses only --skip-plugins=siteengine-consent for that request; no activation
write. The journal requires restore C as the NEXT operation in a NORMAL request,
which calls scj_context/scj_inspect and positive consumer/browser/builders before
clearing that requirement. Failure leaves pending/stopped evidence and controls.
Policy is request-local Config only, restored in finally and positively reprobed.

J7 checks frozen responses/details, owner emails/inquiry identities, immutable
Consent receipts/links and nondecreasing barriers, complete logical references and
actual FK metadata BEFORE any restore/delete. Foreign/unknown/cross-DB references
stop cleanup. Only owned entries/details,contacts/meta/pivots,inquiries are removed;
original form bytes guarded/restored and activation/config/package hashes compared.
ALL3 receipts/links and chain/head/barriers remain Consent-owned, with exact IDs
retained privately. No purge, uninstall, whole-DB restore or old-head rollback.

Alma supplies closure-owner.json and independently verified .sha256: assignment,
external_deliveries:0,pending_owned_jobs:0,scoped_controls_restored:true,
cleanup_owner:"Alma Codex",audit_retention_owner:"Consent",actual baseline/after
configuration/schema/cron/GC/table comparisons and bounded zero-delivery references.
No fabricated measurements; J7 refuses incomplete prior stages or audit counts.
Later stages remain NOT RUN on failure. Recovery is an explicit Alma/Helper decision,
never an automatic replay or broad cleanup of retained/foreign allocations.

## Focused local checks and copyable handback

From canonical Ads checkout, existing PHP8.3.36 -n runs
 tests/joint-operations-acceptance.php and
 tests/joint-consent-integration.php ../consent-rc2-review-20261029.
The latter verifies exact retained authority/continuity/SQL-fixture hashes and
executes NEW integration callbacks only: real Consent/Ads evidence algorithms,
synthetic SQL and consumer/HTTP boundaries. It is not native/installed evidence.
Existing Python runs tests/joint-run-acceptance.py; existing Node runs
 tests/joint-browser-acceptance.cjs and --check tools/acceptance/joint-browser.cjs.
Lint the two PHP tests, joint-ops.php and pinned consent-joint.php with -n -l.
Actual local results:24 ownership/pin/stage assertions +25 integrated callback
assertions;7 Python tests;8 actual-driver admission fixtures +10 exact-ID parser
checks;4 PHP lint +2 JS syntax checks PASS. Launch/consumer/SQL boundaries in these
checks are explicitly doubled; installed WordPress/Linux/browser behavior NOT RUN.
No unchanged candidate suite, database provisioning, browser or WP bootstrap.

Through Aubrey to Alma coordinator: integrate/pin this package against Consent
operations ae2fe50 and manifest0798f8f6. Owner controller and scj_with_fault are used
unchanged. Exact remaining site inputs are J0's actual vendor response ID path,
allocated form/terms/reference map and independent allocation/release/journal pins;
if the response exposes no correlatable ID, that specific vendor admission gap is
the blocker. Review/release J1–J6 only after these inputs and controls are recorded.
J0 independent; Alma Codex remains sole executor/cleanup owner. Advertising API
neither authorizes email nor derives permission from email subscription status.
