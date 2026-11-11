# Windows/Alma one-shot orchestration — November 11, 2026

FE owns the Windows launcher/worker. AB owns Invoke-ProspectiveBridge.ps1,remote PHP
bridge,canonical route admission and isolated execution. Existing owner/CLI profile
and canonical Invoke-Site-Wp.ps1 remain the only network route. No shared-runner edit,
REST endpoint,secret transfer,new vault/auth/ledger or native provider POST.

## Executable path

FieldEdge-Connection.ps1 -Action PilotDouble branches BEFORE Read-Bundle. It requires
existing PowerShell7 and the existing PHP8.3.36 runtime;no runtime is installed.
Invoke-FieldEdgePilotDouble.ps1 pins raw UTF-8 route/card,local adapter/profile/shared
runner hashes,current hostname/SID and exact synthetic source/tenant/request/operation.
Card ID is route-<raw-route-sha256>,avoiding circular digests. Route mode double_only
and synthetic target are mandatory;existing protected local receipt root is retained.

The PHP worker uses accepted PilotReview/LeadSubmission seams. A private bounded
JSON-line broker maps four fixed operations to AB's strict request envelope. Each
request is exclusively created/fsynced,then passed to the pinned AB adapter as PATH
and SHA only. AB calls the canonical runner with its fixed nonsecret base64/eval
template and validates exactly one remote sentinel. No arbitrary remote code or
operation comes from the card. Raw diagnostics are discarded,never used as JSON.

Every reply pins request/route/card/operation,plus native connection ID,owner UID,
verified site,production-disabled mode and transaction/coordination-lock absence.
The local lead double requires the last fresh native read proof. The network call
is synthetic on Windows;actual durable review/claim/observation remains on Alma.
Production credentials,token and provider network are never accessed in this action.

Ceilings:12remote callbacks,30s canonical remote operation,45s local adapter wait,
240s whole run,128KiB request/response,one lead double,zero token/provider calls,
zero automatic retries. Ambiguous remote execution preserves request/result/held
files and original durable claim;inspect/reconcile rather than rerun/reset. Per-action
connection IDs and exact adapter/source pins are retained in the private receipt.
Protocol payloads must also fit native process command-line limits;oversize/process
failure holds rather than implying a completed remote operation.

## Exact launcher command template

AB supplies/adopts one FRESH bounded route/card against an existing original form
inquiry whose selected synthetic target has no prior intent. Do not generate or reset
an old consumed context. Fixed local adapter lives in AB canonical source;do not copy
it elsewhere because its own project/profile containment checks depend on location.

```powershell
& '<existing-pwsh7.exe>' -NoProfile -File '<FE-root>\tools\fieldedge\FieldEdge-Connection.ps1' -Action PilotDouble -RouteFile '<private-route.json>' -RouteSha256 '<raw-route-sha256>' -CardFile '<private-card.json>' -CardSha256 '<raw-card-sha256>' -BridgeAdapterPath '<AB-source>\tools\acceptance\Invoke-ProspectiveBridge.ps1' -BridgeAdapterSha256 '<exact-AB-adapter-sha256>' -PilotMode simulate
```

Mode review performs actual remote review/read with no local lead double. Simulate
may claim once and observe the local response;exact repeated card reads its stored
result without another double. Disabled-execute performs no remote operation and
returns native_production_post_disabled before credentials. No mode enables native
production POST. Existing DPAPI provisioning remains unchanged for a separately
authorized future native transport,keeping secrets on the PC.

## Verification and clarification

New fieldedge-windows-pilot-acceptance.ps1 performs real Windows/PHP process IPC
against an explicitly labeled adapter DOUBLE;9focused assertions PASS. It proves
bounded framing,pins,per-action receipts and hold behavior for bad reply/lock/exit/
size/permit/host cases. It makes no SSH/AB/provider call and is not installed evidence.
Actual Windows→canonical runner→Alma proof is recorded separately below.
No old acceptance campaign was repeated.

Actual closeout now verified from retained AB receipts:runtime3f00361 firstsend has
9callbacks/1Windowslead-double;exactreplay3callbacks/0double,sameverifiedresult.
All12actualnativeconnections independently show open-connection lock/transaction/
site-owner proof. SQL has4newowned review/intent/claim/result rows,total20prospective
rows with prior16 and29old evidence rows preserved. Originaltables/settings/Google/
cron and accepted112memberdev.3runtime unchanged,no newcontact/form/call,0provider
requests/credentialloads. No ZIP was rebuilt. This is actual cross-host durable
execution with synthetic transport;native production POST is still disabled.

Focused post-double uncertainty supplement:run the same test script with
-Group uncertainty to execute ONLY two new process-double cases. Adapter failure
at callback7(pre-observation read after local double) and callback8(observe) yields
13PASS. Exclusive requests/held receipts,original permit/digest and callback sequence
are preserved;there is no later callback/write retry or false success receipt.
Missing final local-double count remains unknown,not zero. The callback8 observation
retains its locally classified result without claiming durable remote acceptance.
No worker/broker/runtime correction or old-suite rerun was needed.

One provider clarification draft was prepared privately for AB Helper/Aubrey using
the already-retained Integrations Team route. It consolidates the schema/casing/status
contradictions,customer/address/notification/dispatch effects,and exact lead/workorder
pair/tracking retry semantics. No email or provider request was sent. Existing real
subject/service/address/appointment owner decisions remain unresolved;this synthetic
execution route does not turn the completed pending card into production authorization.
