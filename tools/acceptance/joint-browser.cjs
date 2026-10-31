/** Operator-side existing Chrome + bundled Playwright. No server execution here. */
const fs = require('fs');
const crypto = require('crypto');
const readline = require('readline');
const {chromium} = require('playwright');
async function main() {
  const [file, pin] = process.argv.slice(2);
  const raw = fs.readFileSync(file);
  if (crypto.createHash('sha256').update(raw).digest('hex') !== pin) throw Error('browser allocation pin');
  const a = JSON.parse(raw);
  if (a.site !== 'https://alma.siteengine.space/' || !/^jct_[a-f0-9]{32}$/.test(a.marker)) throw Error('site/marker');
  for (const k of ['page_url','submit_url','form_selector','submit_selector','submission_request_token','email_selector'])
    if (typeof a.browser[k] !== 'string' || !a.browser[k]) throw Error('allocated browser input missing: '+k);
  const origin = new URL(a.site).origin;
  if (new URL(a.browser.page_url).origin !== origin || new URL(a.browser.submit_url).origin !== origin) throw Error('foreign URL');
  const browser = await chromium.launch({channel:'chrome', headless:false});
  try {
  const context = await browser.newContext({serviceWorkers:'block'}); // One fresh in-memory chain; never reuse an operator's normal profile.
  let captures = 0, submits = 0, blocked = 0, failed = false;
  let ackFaultVerified=false;
  const expectedActions=['grant','submit A','withdraw','submit B','regrant','submit C','personal-off']; let position=0;
  await context.route('**/*', async route => {
    if (new URL(route.request().url()).origin !== origin) { blocked++; await route.abort(); }
    else await route.continue();
  });
  const page = await context.newPage(); page.setDefaultTimeout(15000);
  await page.goto(a.browser.page_url);
  const timer = setTimeout(() => { failed = true; browser.close(); }, 600000);
  let actionTimer=null, explicitlyClosed=false;
  const lines = readline.createInterface({input:process.stdin, crlfDelay:Infinity});
  console.log(JSON.stringify({status:'READY',commands:'grant, submit A, withdraw, submit B, regrant, submit C, personal-off, close'}));
  try {
    for await (const text of lines) {
      actionTimer=setTimeout(()=>{failed=true;browser.close();},15000);
      if (text.length > 32) throw Error('command bound');
      if (text === 'close') { if(position!==expectedActions.length) throw Error('incomplete chain'); explicitlyClosed=true; break; }
      if(text!==expectedActions[position++]) throw Error('fixed one-chain sequence/repeat refused');
      if (['grant','withdraw','regrant','personal-on','personal-off'].includes(text)) {
        if (++captures > 10) throw Error('capture ceiling');
        const before = await page.evaluate(() => window.SiteEngineConsent.getConsent()?.event_id || '');
        await page.evaluate(async action => {
          if (!navigator.locks || !window.isSecureContext) throw Error('real continuity prerequisites absent');
          const s = window.SiteEngineConsent;
          if (action === 'withdraw') {
            const original=window.fetch; let event='', hit=false, rejectCapture=null;
            const wait=async predicate=>{const end=Date.now()+2000;while(!predicate()){if(Date.now()>end)throw Error('ACK fault wait bound');await new Promise(r=>setTimeout(r,20));}};
            const wrapper=function(url,options){
              const body=options&&typeof options.body==='string'?new URLSearchParams(options.body):null;
              const payload=body&&body.get('action')==='se_consent_save_receipt'?JSON.parse(body.get('payload')||'null'):null;
              if(!payload||payload.event_id!==event) return original.apply(this,arguments);
              if(hit||payload.consent.advertising!==false)throw Error('owned capture fault scope'); hit=true;
              return new Promise((resolve,reject)=>{rejectCapture=reject;});
            };
            window.fetch=wrapper;
            try {
              event=s.rejectNonEssential().event_id;
              await wait(()=>hit);
              const pending=s.getConsent();
              if(pending.audit_status!=='pending'||pending.receipt_id||pending.audit_token)throw Error('pending capture gained authority');
              rejectCapture(Error('owned test capture failure')); rejectCapture=null;
              await wait(()=>s.getConsent().audit_status==='failed');
              const failed=s.getConsent();
              if(failed.receipt_id||failed.audit_token||failed.consent.advertising!==false)throw Error('failed capture gained authority');
            } finally {
              if(rejectCapture)rejectCapture(Error('restore owned capture fault'));
              if(window.fetch!==wrapper)throw Error('foreign fetch ownership; stop');
              window.fetch=original;
              if(window.fetch!==original)throw Error('capture restoration failed');
            }
            if(await s.retryAudit()!==true||s.getConsent().event_id!==event)throw Error('explicit same-event capture retry failed');
          }
          else await s.saveConsent({analytics:false, advertising:true, personalization:action==='personal-on'||action==='regrant'}, 'save_preferences');
        }, text);
        await page.waitForFunction(old => {
          const s=window.SiteEngineConsent.getConsent(); return s?.audit_status==='saved' && s.event_acknowledged===true && s.event_id!==old;
        }, before);
        if(text==='withdraw') ackFaultVerified=true;
        // Never print raw cookies, bearer key, context token, ACK token, or the complete state.
        const evidence = await page.evaluate(() => {
          const s=window.SiteEngineConsent.getConsent(); return {event_id:s.event_id,receipt_id:s.receipt_id,audit_status:s.audit_status};
        });
        console.log(JSON.stringify({status:'PASS',operation:text,capture:captures,...evidence}));
      } else if (/^submit [ABC]$/.test(text)) {
        if (++submits > 10) throw Error('submission ceiling');
        const label = text.slice(-1); const form = page.locator(a.browser.form_selector);
        await form.evaluate((element, values) => {
          for (const [name, value] of Object.entries(values)) {
            const field=element.querySelector('[name="'+name+'"]'); if (!field) throw Error('allocated marker hidden field missing');
            field.value=value; field.dispatchEvent(new Event('input',{bubbles:true})); field.dispatchEvent(new Event('change',{bubbles:true}));
          }
        }, {se_joint_marker:a.marker,se_joint_case:label});
        await form.locator(a.browser.email_selector).fill(a.marker+'.'+(label==='C'?'c':'a')+'@example.invalid');
        for (const field of a.browser.required_fields || []) {
          if (field.selector===a.browser.email_selector || /se_joint_|se_consent_/.test(field.selector)) throw Error('required-field override collision');
          await form.locator(field.selector).fill(field.value);
        }
        const received = page.waitForResponse(r => r.url()===a.browser.submit_url &&
          r.request().method()==='POST' && (r.request().postData()||'').includes(a.browser.submission_request_token));
        await form.locator(a.browser.submit_selector).click();
        const response = await received; const body = await response.json();
        if (!response.ok() || body.success !== true) throw Error('real form persistence response failed');
        const payload=body?.data?.result?.siteengine_ads_bridge;
        if (Boolean(payload) !== (label!=='B')) throw Error('actual confirmation payload verdict');
        const capture = await page.evaluate(() => { const s=window.SiteEngineConsent.getConsent(); return {receipt_id:s.receipt_id,event_id:s.event_id}; });
        console.log(JSON.stringify({status:'PASS',operation:text,advertising_payload:Boolean(payload),transaction_id:payload?.transaction_id||null,external_requests_blocked:blocked,pending_failed_ack_verified:ackFaultVerified,...capture}));
        // Persisted IDs/stamp/link/head read back by attest, never guessed from an HTML counter.
        await page.goto(a.browser.page_url);
      } else throw Error('unrecognized command');
      clearTimeout(actionTimer); actionTimer=null;
    }
    if(!explicitlyClosed)throw Error('explicit complete close required');
  } catch (e) { failed=true; console.log(JSON.stringify({status:'FAIL',reason:e.message,action:'STOP; no retry/new chain; retain server journal'})); }
  finally { clearTimeout(actionTimer); clearTimeout(timer); lines.close(); await context.close(); await browser.close(); }
  process.exitCode = failed ? 1 : 0;
  } finally { await browser.close(); }
}
main().catch(e => { console.error(JSON.stringify({status:'FAIL',reason:e.message})); process.exitCode=1; });
