# Consent J1–J6 operations — 2026-10-31, local tooling complete

No installed case was run. The coordinator release allows J0 preparation/installation only; its J1–J6 fixture gate remains closed. Alma Codex alone executes server operations/cleanup. Aubrey is the proposed browser operator. This tooling adds no runtime feature and changes no candidate/package byte. Deliver this document through Aubrey; no external messages were sent.

Dependencies: Consent implementation `1c0a42000927db720cfc60934ea5e2b31db28533`, docs `b8ceb8c292ff3a198bd34548ec2e5a2d13afe98f`, ZIP SHA256 `8471e4761eef927bb10d44967ab88205ef0890a3b6bf90888b8d1ef2c182b619`. Ads implementation `2846305d19d550997295f85e58c7312b736925bd`, final handoff `409df8334a2715fe8b0d0603d174872f84a84304`. Do not rebuild/install Consent for this task.

## Files and admission contract

Local files are in `D:\SiteEngine\Plugins\.PLUGIN — SiteEngine Consent\source\tools\acceptance\`. `CONSENT-TOOLING-MANIFEST.json` records their exact SHA256 and focused-test hashes. Alma must review/pin the completed joint package before privately copying these tools to its already assigned run directory. Do not place them in public_html or the plugin runtime directory.

The actual Ads `joint-ops.php` allocation/journal interface was reviewed read-only during its author's work. Consent consumes the same structures:

- Allocation: assignment/site/prefix/form_id/marker/run_dir/owner_uid, exact `tables.submissions/receipts/events/chains`, independently pinned allocation SHA. No guessed target/ID defaults. Site must be the released Alma site, prefix must match WPDB and table map, run directory must equal `/home/alma.siteengine.space/.se-joint-acceptance/joint-consent-ads-20261031-01`.
- Journal: assignment/marker/allocation_sha256, entries `A/B/C` each with exact integer `id` and original raw `response_sha256`, admitted `chain_id`, `audit[receipt_row_id]` containing receipt_id/original sha256/link_sha256, and `head.revision`. Unique entries, at most 10 entries/audit rows, 2 contacts, one chain, revision <=10. Stopped/closed/foreign journals refuse execution. No discovery through MAX/latest/unfiltered scans occurs in Consent tooling.
- Existing private `fixture-release.json` must have `status: RELEASED_J1_J6_J7` and allocation_sha256; supply its SHA independently, outside allocation JSON. J0 release alone is insufficient. Supply a separately read/pinned current journal SHA after each actual admission. Files must be 0600, run dir 0700 and all owned by the recorded UID/current executor. Existing Ads-owned `operations.lock` must be present, owner-matched/0600, and uncontended. Consent CLI writes no ledger or lock file.

Actual IDs do not exist before vendor creation. Obtain each exact ID from that one real submission's recorded response, then have the Ads owner’s reviewed admission operation validate the exact ID, marker, label and form and add its original response/audit hashes. Never guess the next ID, select the latest entry or discover a fixture by a scan. If the vendor response does not expose an independently correlatable exact ID, stop and name that admission gap. Consent's tools accept admitted IDs, never create or repair the journal. A response with missing/changed marker/hash, foreign chain, or unsupported fields stops before injection.

## Real browser sequence, one site/browser chain

J0 must allocate the exact `browser.page_url` and `browser.form_selector` and verify the selected Fluent Forms node has the matching `data-form_id`, normal AJAX submission, a real `se_consent_receipt_id` echo that survives sanitization, and the two Ads-owned hidden fields `se_joint_marker` / `se_joint_case`. No fields are added by Consent. J0 records their exact master-form field IDs and notification/feed/provider/cache controls. Business fields/contact mapping come from the same allocation (A/B contact A, C contact C); the two synthetic contacts remain Ads-owned. Browser analytics/advertising delivery must already be blocked before page load. Never use this helper to certify egress isolation.

Use a fresh operator-only browser context for first capture, preserve it throughout, and do not clear storage between cases. Load `consent-browser.js` as a reviewed DevTools Snippet (paste the file verbatim; loading it performs no capture). The helper uses actual `SiteEngineConsent.saveConsent(input, method)`, `rejectNonEssential()`, `getConsent()` and `retryAudit()`; no forged browser ACK or separate continuity token is issued. It never prints the raw bearer key/token. Obtain reviewed private JSON files through Alma’s approved local handling; do not serve them from the site or upload them.

Paste these helpers once, then choose the corresponding private files. The SHA values are independent pins from Alma, not self-approval of selected bytes:

```javascript
async function privateText() {
  const [handle] = await window.showOpenFilePicker();
  return (await handle.getFile()).text();
}
const allocationText = await privateText(); // allocation.json
const fixtureReleaseText = await privateText(); // fixture-release.json
const allocationSHA = '<Alma-reviewed allocation SHA256>';
const releaseSHA = '<Alma-reviewed fixture-release SHA256>';
let ops = await SiteEngineJointConsent.start(
  allocationText, allocationSHA, fixtureReleaseText, releaseSHA
);
```

Execute each step once, in coordination with Alma; any unexpected failure stops the sequence. Complete normal business-field validation before `submit`. No script auto-fills unknown field names, changes form definitions or retries a submission.

```javascript
await ops.grant(); // actual advertising grant, personalization false; ACK revision 1
ops.submit('A');   // exactly one normal vendor requestSubmit, markers set in existing fields
```

Wait for successful vendor persistence. Record its exact response ID. Alma validates/adopts that ID into the journal and runs Consent inspect A plus Ads prepare/probe A. A must be historically verified/currently eligible; Ads controls determine the other gates. Then privately receive the new pinned journal and enter the exact returned ID (no sample ID):

```javascript
let journalText = await privateText();
let journalSHA = '<independently provided current journal SHA256>';
await ops.admit('A', Number('<exact A ID from its actual response>'), journalText, journalSHA);
```

Before withdrawal, Alma runs the J2 null/malformed/unknown/unsigned/tampered/pending/failed projections on currently eligible A, using its prepared body; these create no rows. Each fault requires an eligible baseline, so an already blocked row cannot pass trivially. J2 acknowledgement faults then use the **same actual withdrawal interaction** which J3 later acknowledges. No extra receipt, event, chain or submission is created for these faults. Execute the following three lines consecutively; a 20-second owned timeout removes the capture override and halts further operations if the operator does not finish. Do not submit another form while capture is held.

```javascript
try {
  await ops.beginWithdrawalFault(); // actual rejectNonEssential; exact receipt request held before network
  await ops.failAcknowledgement(); // real rc.2 failure path, local rejection/no receipt; removes override
} finally {
  console.log(await ops.restoreFault()); // must report restoration VERIFIED, fetch_override false
}
await ops.retryWithdrawal(); // only this deliberate retry; actual retryAudit, SAME event, ACK revision 2
ops.submit('B');             // real rejected business submission, once
```

Local optional flags are denied immediately; pending/failed state carries no receipt/token. Holding/failing this one capture sends **zero** receipt AJAX requests to the server. Restoration verifies `window.fetch` equals the original reference, clears its timer and rejects any held promise. It runs on success, error and timeout. A foreign replacement of fetch causes STOP rather than overwriting another owner’s wrapper. On timeout/restoration uncertainty close the isolated fixture browser, retain server controls/evidence and report; no automatic retry/restart/new event.

The same acknowledged withdrawal/rejected B supplies J2 rejection and J3 subsequent-withdrawal evidence; do not create a second withdrawal to satisfy stage names. After B's exact-ID admission, Consent inspect must return `A: withdrawn/allowed false`, `B: denied/allowed false`. B contains a genuine acknowledged rejected choice, not a fake denial receipt. Run Ads rejection/probe/dispatch on owned IDs. Then receive the new pinned journal and:

```javascript
journalText = await privateText();
journalSHA = '<new independently provided journal SHA256>';
await ops.admit('B', Number('<exact B response ID>'), journalText, journalSHA);
await ops.regrant(); // actual new advertising grant, personalization true; ACK revision 3
ops.submit('C');     // new normal vendor submission, once
```

Admit C from its exact response ID, refresh the pinned journal, and call `ops.admit('C', exactID, journalText, journalSHA)`. Inspect A/B/C: A remains withdrawn, B remains denied, C eligible under the unchanged policy. A/B original response/stamp/receipt/link hashes must match their admitted originals. C's personalization=true is independent of A's false; neither substitutes for advertising permission. No historical stamp is upgraded after a late ACK or regrant.

If actual vendor confirmation hides the form or requires a page reload, first finish exact-ID admission/inspection at the acknowledged A/B boundary. Reload only in this same browser context, reload the snippet, and resume with the same allocation/release plus the independently pinned journal:

```javascript
ops = await SiteEngineJointConsent.start(allocationText, allocationSHA,
  fixtureReleaseText, releaseSHA, journalText, journalSHA);
```

Resume refuses unknown/pending/failed state, a different receipt, journal/current revision mismatch, or a new/unadmitted chain. Do not restart at grant or clear cookies to work around refusal. AJAX success alone is not database evidence; server inspection remains required. End the browser helper with `await ops.close()`; close the fixture browser only after evidence is retained. It removes fault state and retains continuity/evidence, never resets barriers.

## Exact server command interface (Alma only, after fixture release)

PHP file: private `$Run/consent-joint.php`, source hash pinned by the tooling manifest. Actual CLI arguments are **seven** after the eval-file path:

```text
inspect|fault LABEL MODE allocation.json ALLOCATION_SHA JOURNAL_SHA RELEASE_SHA
```

Use the already recorded profile/shared owner route, not a new SSH script. These are paste-ready argument arrays for the canonical runner; the assigned profile and reviewed pins must be supplied by Alma:

```powershell
$Profile = '<J0-recorded existing absolute site profile path>'
$Run = '/home/alma.siteengine.space/.se-joint-acceptance/joint-consent-ads-20261031-01'
$AllocationSha = '<Alma-reviewed allocation SHA256>'
$JournalSha = '<current exact-ID journal SHA256>'
$ReleaseSha = '<Alma-reviewed fixture-release SHA256>'
$InvokeSiteWp = 'D:\SiteEngine\Agency\AI-Workspace\Invoke-Site-Wp.ps1'
$Arguments = @('eval-file', "$Run/consent-joint.php", 'inspect', 'A', 'eligible',
  "$Run/allocation.json", $AllocationSha, $JournalSha, $ReleaseSha)
& $InvokeSiteWp -SiteProfile $Profile -TimeoutSeconds 20 -WpArguments $Arguments
```

Inspect requires an explicit expected reason (eligible, withdrawn or denied) and asserts available/allowed/reason, not just exit status. At J1 use A/eligible; after withdrawal use A/withdrawn and B/denied; after regrant use A/withdrawn, B/denied and C/eligible. Refresh the journal pin when it changes. This calls the actual `SiteEngine_Consent_Authority::current_eligibility(array $stamp, int $canonical_form_id, string $required_policy): array`. Only contract 2 available/allowed true permits advertising. Normal inspect uses original stored evidence; no projection is active.

Standalone J2/J5 fault probe example (set mode from the table, exact owned label only):

```powershell
$Arguments = @('eval-file', "$Run/consent-joint.php", 'fault', 'C', 'failed-current-read',
  "$Run/allocation.json", $AllocationSha, $JournalSha, $ReleaseSha)
& $InvokeSiteWp -SiteProfile $Profile -TimeoutSeconds 20 -WpArguments $Arguments
# Positive original-state readback after success OR a failed probe:
$Arguments = @('eval-file', "$Run/consent-joint.php", 'inspect', 'C', 'eligible',
  "$Run/allocation.json", $AllocationSha, $JournalSha, $ReleaseSha)
& $InvokeSiteWp -SiteProfile $Profile -TimeoutSeconds 20 -WpArguments $Arguments
```

Do not execute direct commands until the joint runner’s owner has integrated the released outer 20-second + 2-second TERM/KILL supervision. The existing shared PowerShell runner limits the SSH client to 20 seconds but does **not** prove remote PHP child-group termination. The reviewed Ads-owned `joint-run.py` supplies process-group supervision for its own allowlisted operations; it must call Consent's library operations inside that supervised child or admit this CLI argv. Calling the Consent library inside a supervised Ads child is sufficient; no second harness is needed. This is a named Ads/Alma integration dependency, not permission to invent a connection/timeout bypass. No commands above have run on Alma.

## Fault table and callable Ads integration

`scj_with_fault(array $allocation, array $journal, string $label, string $mode, callable $callback): array` takes only original admitted journal evidence. A successfully reviewed caller invokes `scj_context($allocation)` first, holds the shared operations lock, validates the independently pinned allocation/release/current journal and records its pending operation before calling. Load with `define('SCJ_LIBRARY_ONLY', true); require $Run . '/consent-joint.php';`. Set allocation `_sha256` to the independently verified allocation pin. The callback receives a **copy** of the journal with only projected response_sha256 adjusted; global `$wpdb` is the temporary read view. It never replaces a real original hash in the private journal.

| Mode / label | Injected view, expected result | Persistent mutations |
| --- | --- | --- |
| conflicting-flat / B | Original signed rejected B + genuine A flat receipt/forged granted flat flags; no fallback permission | 0 |
| null, malformed, unknown, unsigned, tampered / A (J2) | Only A's projected response stamp changed/removed; real Ads current/browser/Qualified/Converted/dispatch must deny | 0 |
| pending, failed / A (J2) | Real Consent-generated signed negative stamp in projected response + stale A flat grant; denies, no retroactive upgrade | 0 |
| unavailable-head / C | Only exact owned chain's current-read SELECT returns missing head; actual API unavailable/denied, historical proof unchanged | 0 |
| failed-current-read / C | Only that exact current-read SELECT throws; actual API returns storage_unavailable/denied | 0 |

Response projections are **synthetic malformed-evidence fixtures**, not proof that vendor persistence wrote corrupt rows. Pending/failed projections complement the real browser's deliberately failed withdrawal capture; they do not claim a pending/failed real submission was created. No entry details are edited or fabricated. Actual normal A/B/C exercise vendor persistence; the retained Ads observer/owned harness must inspect the real persisted details through exact owned IDs.

Ads callback example using its existing library functions, not a source edit by Consent:

```php
$result = scj_with_fault($a, $j, 'C', 'failed-current-read',
    function (array $projectedJournal) use ($a, $j) {
        $probe = jx_probe($a, $projectedJournal, 'C');
        jx_expect($probe, false);
        $dispatch = jx_append($j['prepared']['C']); // Ads-owned synthetic transport sink only
        scj_require(!$dispatch['allowed'] && $dispatch['synthetic_calls'] === 0, 'fault reached dispatch');
        return ['builders_browser_blocked' => true, 'dispatch_blocked' => true];
    });
```

Never call the normal owned-row verifier against a projected journal outside the callback. The standalone CLI proves Consent's direct result only; Ads callback execution is required to claim builder/browser/dispatch coverage. Nothing here enables exports or reaches a real transport. The tool refuses unsupported/write WPDB methods, and faults only the admitted response ID or exact current-read SQL/chain. It snapshots admitted response, every exact audit receipt/link ID and the one head, then restores the original `$wpdb` reference/error and positively rereads original hashes and real API result in **finally**, including callback failure. A callback exception reports `CALLBACK_FAILED; RESTORATION_VERIFIED`; any failed readback reports STOP and retains controls. No separate persistent restoration write exists. Process termination discards this process-local view; retain a failed operation until Alma runs the positive inspect command and reconciles the runner/journal.

J5 missing callable cannot be safely created by removing a method from a loaded final PHP class. Consent's context intentionally refuses missing/wrong rc.2. Ads owns its reviewed request-only `--skip-plugins=siteengine-consent` probe and must demonstrate the actual consumer denies while Consent is absent, followed by a fresh **normal** request verifying original hashes/API and stored evidence. That tests missing plugin authority, not a method-only disappearance with all other helpers intact. No stored active_plugins change, autoloader trick or always-successful authority stub is authorized here.

## Mutation ceiling, retention and remaining owner integration

Consent contribution: 3 delivered capture requests total (grant, same-event withdrawal retry, regrant), 3 receipt inserts, 3 immutable links, 1 head created and advanced only through revisions 1/2/3; 3 normal submissions A/B/C with their actual owned details. One intentionally held/failed receipt request never reaches PHP. Ordinary capture performs one existing rate-limit transient increment per delivered request; physical DB/cache writes and already authorized bootstrap effects are recorded by Alma, not assumed to be one SQL statement. No additional head/revision for a fault. Three browser marker/case assignments are transient existing-field values, not persisted form-definition edits. All server inspect/fault operations have zero persistent writes and zero fixture creation. Fault operations create no extra contacts, submissions, receipts, links, heads or inquiry IDs.

Ads owns the recorded two contact limit, four inquiry limit, exact form-field definitions/additions/restoration, their journal/attempt files, notification/feed controls and J6 DNC/policy cases. Combining this Consent sequence leaves at least seven receipts/links and seven submissions of the joint ceiling unused; unused capacity is not permission to add a case or retry a failure. Actual details count comes from the single assigned vendor form and must be recorded before its first submit; no guessed field/detail count is claimed.

Retain all 3 synthetic receipts, all 3 links, the one head and permanent barriers, identified by the journal's exact `audit` IDs and `chain_id`. Retain original response/stamp evidence privately before any approved Ads cleanup. Consent supplies **no delete command**. Alma Codex is cleanup/execution owner, Consent owns audit retention; removal of manifest-owned submissions/details/contacts/inquiries is only Ads' reviewed foreign-reference-checked J7 contract. Never delete audit rows, restore old DB state, uninstall, lower barriers or delete outside that manifest. If unknown references remain, retain the affected fixtures and report them. Positive readbacks and failed-operation evidence remain privately owned by Alma.

Concrete Ads-owner review points before fixture release: consume these Consent callbacks under its bounded launcher; supply exact-response-ID admission (the initial draft attestation discovered marked rows rather than taking an exact returned ID); retain independent allocation/release pins (the later draft added fixture-release.sha256, resolving the initial circular-hash concern); cover J5 current-read faults with this exact-head seam rather than failing earlier historical receipt reads; verify restoration readback on failure as well as success; retain Consent audit data at J7. Ads files were read only while their owner was editing them, so observations are review points for its final checkpoint, not a claim that its unfinished file is the released package.

## Focused local evidence

`tests/joint-operations.php`: 32 checks through real pinned Consent rc.2 / Ads 2846305 helper, synthetic SQL boundary. Covers all ten fault modes, foreign ID/chain/table/hash refusal, write refusal, restoration after callback failure and refusal when original state drifts. `tests/joint-browser.cjs`: 13 focused controller checks using actual embedded rc.2 JS and retained VM boundary fixtures, not the unchanged runtime suite. Covers exact capture hold/failure/same-event retry, one key/three native submits, foreign allocation, exception/timeout restoration. No WordPress, SQL or browser action ran on Alma. Actual installed acceptance and Ads integrated dispatch callback remain NOT RUN.

Run focused local tooling checks only:

```powershell
$PHP = 'D:\SiteEngine\Plugins\.PLUGIN — SiteEngine Ads-Bridge\local-test-runtime\php-8.3.36\php.exe'
$Consent = 'D:\SiteEngine\Plugins\.PLUGIN — SiteEngine Consent\source'
$Ads = 'D:\SiteEngine\Plugins\.PLUGIN — SiteEngine Ads-Bridge\source'
& $PHP -n "$Consent/tests/joint-operations.php" "$Ads/includes/class-siteengine-ads-bridge-consent.php"
node "$Consent/tests/joint-browser.cjs"
```

These execute no installed WordPress/browser/SQL. The 32 PHP + 13 browser-tooling checks passed; two new PHP files lint and both JavaScript files syntax-check clean. Existing runtime suites were not repeated. The actual pinned Ads helper matches implementation2846305 (line endings normalized); only the SQL/DOM/storage/transport boundaries are doubled.
