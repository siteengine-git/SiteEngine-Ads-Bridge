# Firefox operator sheet — future recorded Alma execution only

J1–J6 are HELD until final package/browser-control review. No actions in this sheet
were executed locally. Alma Codex is server executor/cleanup owner; Aubrey operates
his EXISTING authenticated Firefox session. No CDP, new profile, storage clearing,
credential export, manual receipt edit, latest-row query or raw HAR export.

1. **Review and arm before capture.** Verify final ZIP/external member hashes first.
   The ONE new setup change requiring Alma review is temporary loading of the bundled
   firefox-control/manifest.json: existing Firefox -> about:debugging -> This Firefox
   -> Load Temporary Add-on. No browser replacement or debugging listener. Keep only
   the allocated Alma tab open; unrelated-site tabs remain untouched. Leave this
   add-on active through closure/recovery. An unsupported API/extension policy is a
   STOP, not permission to create a new environment or fall back to fetch-only guards.

2. **Alma supplies authentic files/pins, then the control proves native interception.**
   After operations-journal admission, Alma issues fixture-release bound to final
   manifest + original APIN + reviewed manual-Firefox context CPIN. In the add-on's
   toolbar popup choose private allocation,release,context,current journal and
   OPERATIONS-MANIFEST JSON files. Enter independently supplied APIN/RPIN/CPIN/JPIN.
   Initial arm; one existing selected Alma tab is required. Click ONE harmless block
   probe, which targets a reserved .invalid image and must record actual native
   cancellation. Reload only the allocated page in that same tab with control armed.
   Read evidence: require ARMED, probe_blocked=true, no inflight request and exact
   document HTTP200+CSP entry. Save sanitized evidence privately. Never infer this
   from screenshots, a failed fetch or a CSP-only cancelled probe. Missing proof stops.

3. **Authenticate and load the actual controller.** Existing WordPress session must
   show body.logged-in AND visible #wpadminbar and the exact allocated form/data-form_id.
   If login is needed, Aubrey performs it manually in the already controlled tab;
   no tool reads credentials/cookies. Alma runs J1 form/two-contact commands only
   within its release and sends refreshed journal/pin. Thereafter choose only the journal file + JPIN and click Refresh journal only in the popup; original allocation/context/release/package stay sealed.
   Firefox Web Console: paste these exact reviewed local files (not website URLs):
   firefox-control/joint-firefox-core.js; consent-pinned/consent-browser.js;
   joint-firefox.js. Controller SHA remains
   b5969d67e38a3ddd93c3a2eba00cbf95be55a0042c02bf053c0af40c47791c26.
   Loading performs no capture. Browser helper uses standard file inputs with visible
   local selectors, never showOpenFilePicker or Chromium APIs. No private file uploads.

4. **Start and refresh at every boundary.** Independently hash saved control evidence
   in the existing local shell, not from its declared fields:

   ```powershell
   (Get-FileHash -Algorithm SHA256 -LiteralPath '<private control JSON path>').Hash.ToLowerInvariant()
   ```

   In Firefox (replace pins from Alma, never self-authorize):

   ```javascript
   await JointAdsFirefox.start({allocation:'APIN',release:'RPIN',context:'CPIN',journal:'JPIN'}, 'CONTROL_SHA');
   ```

   Choose the five private files and control evidence requested by visible pickers.
   Existing service workers cause refusal; never unregister them. Before EACH real
   capture or submit read/save/hash a fresh popup control summary and run:

   ```javascript
   await JointAdsFirefox.refresh('CURRENT_JPIN','FRESH_CONTROL_SHA');
   ```

   Select the current journal and matching control file. Fresh readback must be <=15s
   before capture/submit; session remains <=10min from initial control arm. Each file
   selection <=15s, file <=1MiB; vendor response <=1MiB/15s. No retries on failure.

5. **A: genuine grant -> vendor submission -> exact-ID admission.**

   ```javascript
   await JointAdsFirefox.step('grant');
   // fresh popup readback/hash and helper refresh with SAME current JPIN
   await JointAdsFirefox.step('submit A');
   // wait for popup's one complete A vendor record; read/save/hash it
   await JointAdsFirefox.evidence('A','POST_RESPONSE_CONTROL_SHA');
   ```

   Typed allocated business fields are verified/read back automatically; visible-only
   conditionals skip when genuinely hidden. No authority field is filled. Downloaded
   browser-A.json contains exact data.insert_id, form, receipt/revision, expected
   transaction/payload, response digest/size, control ID/digest and APIN/RPIN/CPIN/JPIN.
   No raw body, token, cookies or headers. Missing/unreadable ID/body is STOP; never
   resubmit, guess ID or replace evidence with a screenshot. Transfer that exact
   private file non-overwriting to Alma with its independent SHA. Alma verifies SHA,
   writes the existing private .sha256 sidecar and takes A_ID from this JSON, then
   executes the matrix's exact attest/browser-evidence/milestone/adopt commands.
   After receiving current journal/JPIN, refresh BOTH popup and page helper, then:

   ```javascript
   await JointAdsFirefox.step('admit A');
   ```

   Vendor confirmation may hide the form: response evidence/admission allow that,
   capture still requires the visible form. If needed AFTER acknowledged admission,
   reload this SAME guarded tab, repaste the same three files and start with current
   journal/pins. The real controller validates its existing chain/revision; session
   clock and extension used-submission list do not reset. No new chain or re-grant.

6. **B: the single withdrawal interaction.** Alma completes J1/J2 pre-withdrawal
   cases and sends refreshed pins. Refresh popup/helper, ensure form access, then:

   ```javascript
   await JointAdsFirefox.step('withdraw');
   // delegates beginWithdrawalFault -> failAcknowledgement -> finally restoreFault
   // -> SAME-event retryWithdrawal; actual ACK revision2, not a second capture
   // fresh popup/helper refresh, same current JPIN
   await JointAdsFirefox.step('submit B');
   // complete native B response, save/hash control record
   await JointAdsFirefox.evidence('B','POST_RESPONSE_CONTROL_SHA');
   ```

   Transfer/hash browser-B.json; Alma uses exact B_ID in J3 matrix, then sends new
   journal/JPIN. Refresh both and step('admit B'). A remains withdrawn, B denied.
   Complete J3 and J2 closure server cases; no extra withdrawal/event/submission.

7. **C: new eligible capture only.** With fresh pins/control and visible form:

   ```javascript
   await JointAdsFirefox.step('regrant');
   // fresh popup/helper refresh
   await JointAdsFirefox.step('submit C');
   // complete native C response, save/hash control record
   await JointAdsFirefox.evidence('C','POST_RESPONSE_CONTROL_SHA');
   ```

   Transfer/hash browser-C.json, Alma runs exact-ID C admission and sends current
   journal/pin. Refresh both; step('admit C'). Complete J4/J5/J6 server matrix under
   the corrected Consent callbacks: A withdrawn, B denied, C eligible. No email sends.

8. **Retain, report, hand back.** Alma begins J7, sends current pins; refresh both,
   then step('close'). Alma runs close only, never stage J7. Record exact retained
   graph IDs/metadata response_id links/form hashes/audit/head/control ownership.
   Report: “Functional results reported; closure incomplete—resources retained.”
   Do not delete fixtures/audit, lower barriers, restore form, remove blocker or
   restart Firefox. Alma decides tab cessation and later scoped control removal;
   Consent owns audit retention. Any failure leaves controls/pending receipts intact.
