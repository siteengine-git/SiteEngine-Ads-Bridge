# Final integrated joint operations r5 — local preparation; J1–J6 HELD

Completed J0, original allocation/reference map and both runtime packages carry
forward unchanged. r3/r4 artifacts remain preserved. This package replaces the
r4 Consent blocker and adds one MANUAL Firefox operator path. No CDP endpoint,
Chromium, new profile/context or attachment discovery is required. Alma Codex alone
executes server commands and owns graph/control cleanup; Aubrey operates the
existing authenticated Firefox tab only within Alma's recorded future assignment.
No server, WordPress bootstrap, browser, fixture or transport execution occurred here.

## Exact dependencies / private context

Ads runtime source2846305d19d550997295f85e58c7312b736925bd, ZIP SHA256
34d75e77285aa1e6b8441e0a9f1f766e614150b533423549ce03a91cbd71b72f.
Consent rc.2 runtime1c0a42000927db720cfc60934ea5e2b31db28533, docs
b8ceb8c292ff3a198bd34548ec2e5a2d13afe98f, ZIP SHA256
8471e4761eef927bb10d44967ab88205ef0890a3b6bf90888b8d1ef2c182b619.
Published Consent tooling7a54433cf5eba4583071c3b25dc6b99b8e548454,
manifest964557de4920cbcb682b5ec0c86f0cbce8ab7cd94601824419d85558877c8883.
All eight listed published members are exact copies, never independently patched.
consent-browser.js remains b5969d67e38a3ddd93c3a2eba00cbf95be55a0042c02bf053c0af40c47791c26.

Read consent-pinned/CONSENT-OPERATIONS.md and CONSENT-TARGET-CORRECTION-R4.md.
Their old showOpenFilePicker example is superseded ONLY by the Firefox file-input
operator wrapper; the controller itself is unchanged. Exact Ads joint-target.php
remains2b7a5bf897dd22c1ca7a629b88566b4c9f9589e0f9f3eb8a4e2d62cfe191f117.
Its exact copy beside standalone consent-joint.php satisfies the published validator
dependency; loaded callback code verifies the reflected same helper hash.

Never rewrite original allocation/map. Alma reviews a separate context.json0600:
exact stored HTTP home/siteurl and effective HTTP home remain separate from HTTPS
browser origin; exact host/owner/UID/root/database hash/server/prefix remain private
and unchanged. Only the unissued browser_access companion changes to mode
manual-firefox as in context.template.json. Independently issue its new CPIN and bind
fixture-release.context_sha256 and operations_manifest_sha256. Original APIN/map stay.
All callback initialization carries a._sha256/APIN, a._release_sha256/RPIN,
a._context, a._context_sha256/CPIN and journal.context_sha256; the published library
rechecks private allocation/release/context files, ownership/modes and actual target.
A target array, old seven-argument CLI or stale pin cannot replace that authentication.

## ONE missing browser capability / smallest setup change

Retained Alma inspection did not establish a cross-origin interceptor for the
existing Firefox session. A fetch/XHR wrapper is insufficient. The single proposed
setup change is loading THIS reviewed temporary Firefox add-on from
firefox-control/manifest.json in the EXISTING profile/tab using about:debugging.
Do NOT load it under this local assignment. Alma must review/release this browser
control before fixtures; no new browser/profile/debugging listener is proposed.

The add-on registers Firefox native blocking webRequest BEFORE navigation/capture,
allows only exact HTTPS Alma origin, rejects cross-origin requests of every type
(including images/scripts/beacons/form navigations/redirects/WebSockets), and adds
restrictive CSP before document processing: no workers/frames/objects, no cross-origin
connect/script/image/form delivery. Requests from the selected tab and separately
identified Alma-origin background sources are covered; exactly ONE Alma tab must be
open. Unrelated sites remain unaffected. No cookie/credential/header/raw body is
exported. Existing service-worker registrations cause the page helper to stop;
never unregister or clear storage. No claim covers privileged Firefox traffic,
non-HTTP protocols, unrelated workers, direct server SDKs or already-open sockets.
J0's separate reachable server delivery/worker/cache controls remain necessary.

Native verification BEFORE capture: arm, run the ONE .invalid image interception
probe in the existing tab (onBeforeRequest must actually record cancellation), then
reload the allocated page with the native blocker active. The returned HTTP200
main-document CSP event and fresh control receipt are required by the helper.
The .invalid name has no delivery endpoint; if cancellation is not observed, STOP,
not retry or treat ordinary failure/CSP suppression as proof of interception.
Firefox version/extension availability, API behavior and actual site compatibility
are NOT RUN here. This ONE control setup/native verification is the remaining
browser prerequisite, not a renewed attachment search or general planning pass.
See FIREFOX-OPERATOR.md for exact manual steps and hashes from the package manifest.

## Exact server command interface — future Alma execution ONLY

Stage the verified final ZIP non-overwriting in the existing private run/harness-r5-final.
Before ANY bundled execution, verify the independently delivered ZIP and external
member manifest; compare every member length/hash and reject path traversal.
Directory0700/files0600 owned by the established executor. No permanent MU plugin.
The existing retained cli-control.php must remain at the exact private run path,
SHA256 f3702f4570a67ccdfb0973e3d3b0e4bc9411bf5b5fb33550e569382c562dde8f.
Every supervised child, including missing-plugin mode, uses WP-CLI
--require=<OPS>/joint-startup.php to verify/load it BEFORE application bootstrap.
The parent verifies it before attempt creation/spawn; omitted/unreadable/mismatched
bytes fail. CLI HTTP/mail/cron control does not certify web/SDK/worker isolation.

Run only inside the existing owner route; no new access setup or SSH command here:

```sh
RUN=/home/alma.siteengine.space/.se-joint-acceptance/joint-consent-ads-20261031-01
OPS="$RUN/harness-r5-final"
ALLOC="$RUN/allocation.json"
CONTEXT="$RUN/context.json"
APIN='<independent original completed allocation SHA256>'
CPIN='<independent reviewed manual-Firefox context SHA256>'
JPIN=-
RPIN=-
j() {
  RESULT=$(/usr/bin/python3 "$OPS/joint-run.py" "$@" "$ALLOC" "$APIN" "$JPIN" "$RPIN" "$CONTEXT" "$CPIN") || { printf '%s\n' "$RESULT"; return 1; }
  printf '%s\n' "$RESULT"
  NEXT=$(printf '%s' "$RESULT" | /usr/bin/python3 -c 'import sys,json;print(json.load(sys.stdin)["journal_sha256"])')
  ACTUAL=$(sha256sum "$RUN/journal.json" | cut -d ' ' -f1)
  test "$NEXT" = "$ACTUAL" || return 1
  JPIN=$ACTUAL
}
```

`admit` here creates only the OPERATIONS journal; completed J0 is not repeated and
no fixtures are created. Set RPIN from Alma's NEW independent fixture release after
that admission and before the first J1 command. It binds final operations manifest,
CPIN and original APIN. Every later command authenticates current JPIN under the
shared lock and persists pending intent before any mutation. Parent bounds stay
18s work +1s TERM/1s KILL,64KiB output,1MiB journal,64 commands. Incomplete process,
output, pin or restoration evidence stops; no automatic retry/cleanup/release.

Actual child shape (generated by existing joint-run.py):
/usr/local/lsws/lsphp83/bin/php /usr/bin/wp
--path=/home/alma.siteengine.space/public_html --require=<OPS>/joint-startup.php
[--skip-plugins=siteengine-consent ONLY for missing]
eval-file <OPS>/joint-ops.php OP LABEL ARG allocation.json APIN JPIN RPIN context.json CPIN

Standalone published Consent shape is also NINE arguments:
inspect|fault LABEL MODE allocation.json APIN JPIN RPIN context.json CPIN.
Its library/argv parser is integrated/tested, but no standalone fault command is
released outside the supervised Ads child. Use `j consent C eligible`, `j fault C
unavailable-head`, and `j fault C failed-current-read` below: they call the ACTUAL
published callbacks with durable intent, the shared lock, authentic pins, actual
consumer/browser/builders and synthetic append boundary in that same bounded child.
Success AND throwing callbacks require original evidence hashes and positive readback
in finally. Missing-plugin mode must be followed immediately by normal `j restore C -`.

## Real flow, evidence and mutation ceiling

One form/context/browser chain; two contacts; ceilings10 receipts/links/submissions,
one head/10 revisions and4 inquiries unchanged. The fixed flow uses A/B/C: exactly
3 real delivered captures/receipts/links and revisions1/2/3,3 vendor submissions and
2 inquiries. No unused ceiling is permission to repeat a case. Two reviewed marker
field definitions are the only form additions. Notification/feed controls remain
Alma-owned. No runtime/shared-service/configuration/activation edits occur here.
All exports, Sheets and lifecycle automation stay disabled. Advertising never
authorizes email or derives permission from subscription status.

Actual extension StreamFilter reads ONLY each owned vendor request/response, forwards
all response bytes unchanged, caps captured body1MiB/15s and accepts3 requests max.
Root form_id plus serialized data must contain exact marker/case and real echoed
receipt_id; it never writes authority fields. A complete HTTP200 response must have
success=true, data.insert_id and expected payload/transaction verdict. Missing or
unsupported request encoding, ID/path, truncation, duplicate, timeout, foreign receipt,
stale pins or malformed JSON stops with no admission/search/retry. Only sanitized
field/digest/byte-count evidence is saved, never raw response, cookie, headers or bearer.
Page helper ties that record to the actual Consent-controller ACK/revision and
current APIN/RPIN/CPIN/JPIN, then saves browser-A/B/C.json. Alma independently hashes
that exact file into browser-A/B/C.json.sha256 and uses THAT returned ID for attest.
Server attest verifies the exact persisted row/signature/form/marker/case/receipt
and all pins; browser-evidence rechecks against its admitted pre-operation journal pin.
No marker/MAX/latest discovery is used. Actual response path remains untested installed.

Before each browser boundary, transmit current journal.json and JPIN privately;
refresh BOTH extension and page helper and independently hash their control evidence.
Before capture/submit, control readback must be <=15s old; no prepare-time permission.
A/B submit on contact A; C uses contact C. Fields follow allocated native types and
visible conditional applicability. Controller owns ALL grant/withdrawal/retry/regrant
behavior; no competing implementation or forged evidence. If vendor success hides
form, evidence/admission can proceed without visible form; captures still require it.
Reload only at an acknowledged/admitted boundary in the SAME Firefox tab, paste same
files and start with current journal; real controller proves the existing chain/revision.
Session remains bounded by original add-on start10min; reload never resets that bound.

## Ordered executable matrix (63 server commands; existing ceiling64 unchanged)

Server j() calls below run in order. Browser lines refer to the numbered Firefox operator sheet;
refresh JPIN and the extension/page pins at each browser boundary. Stop on first unexpected result.

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
| J7 | `j begin J7 -`; Firefox step close; `j close - -` | record exact metadata/related graph IDs, Consent audit/head and form hashes; retain controls; no deletion/restoration |
| J7 disposition | No stage-J7 execution or clean PASS | Functional results reported; closure incomplete—resources retained. Alma Codex owns graph/control cleanup; Consent owns audit/barriers. |

## Retained closure / recovery

J7 always follows the accepted retained-graph disposition: Functional results
reported; closure incomplete—resources retained. Do NOT run stage J7, delete data,
restore the form/controls automatically or claim full acceptance. The close operation
records exact metadata IDs/response_id links, admitted submissions, related details,
contacts/meta/pivots, inquiry IDs, original/patched/current form hashes and Consent
audit/head/barriers in the private journal. Bounded unreadable/foreign/duplicate rows
leave pending/stopped evidence and unknown graph completeness; never assume zero.
Existing foreign-reference/FK deletion guards remain intact; deletion clearance is
NOT RUN on this retention path. No ownership/deletion grant is inferred from a match.
Alma Codex owns retained graph/form/controls and any later exact cleanup release;
Consent owns audit retention/barriers. Background unowned rows stay excluded.

On failure STOP at that case. Keep browser blocker and all server controls; retain
private receipts, attempt logs, current journal and prior pins. No new chain,
resubmission, automatic restoration, barrier rollback or whole-DB recovery. Existing
application services remain unchanged. Only Alma decides recovery/cessation. Remove
the temporary add-on only after Alma verifies fixture-tab/background work ceased and
separately releases that scoped removal; never restart Firefox to bypass the control.

## Local verification and handoff

REVIEW-R5.md/OPERATIONS-RESULTS.json record actual fresh focused checks versus carried
historical evidence. All native Firefox/network/file/SQL/OS boundaries are doubled;
installed behavior NOT RUN. No setup/receipt investigation/unchanged runtime suite
or Windows database investigation repeated. Return this pinned package through
Aubrey to Alma coordinator; J1–J6 remain held pending final package/browser-control
review. Use exact hashes in DELIVERY-R5.json and external per-member manifest.
