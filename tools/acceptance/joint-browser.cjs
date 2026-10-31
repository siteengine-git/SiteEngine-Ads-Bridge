/** Bounded operator driver. All Consent capture/fault operations delegate to ae2fe50. */
const fs=require('fs'), crypto=require('crypto'), path=require('path'), readline=require('readline');
const hash=b=>crypto.createHash('sha256').update(b).digest('hex');
function pinned(file,pin){const b=fs.readFileSync(file);if(!/^[a-f0-9]{64}$/.test(pin)||hash(b)!==pin)throw Error('independent file pin mismatch');return b;}
function exactVendorId(body,keys){
  if(!Array.isArray(keys)||!keys.length||keys.length>8||keys.some(k=>typeof k!=='string'||!k||['__proto__','constructor','prototype'].includes(k)))throw Error('J0 must supply actual vendor response ID path; no latest/marker fallback');
  let value=body;for(const key of keys){if(!value||!Object.hasOwn(value,key))throw Error('vendor response lacks correlatable exact ID; STOP admission');value=value[key];}
  if(typeof value==='string'&&/^[1-9][0-9]{0,9}$/.test(value))value=Number(value);
  if(!Number.isSafeInteger(value)||value<1||value>9999999999)throw Error('vendor response ID malformed');return value;
}
async function main(){
  const [file,pin,releaseFile,releasePin,journalFile,initialJournalPin]=process.argv.slice(2);
  const raw=pinned(file,pin),a=JSON.parse(raw),releaseRaw=pinned(releaseFile,releasePin),release=JSON.parse(releaseRaw);
  const manifest=fs.readFileSync(path.join(__dirname,'OPERATIONS-MANIFEST.json'));
  if(release.status!=='RELEASED_J1_J6_J7'||release.allocation_sha256!==pin||release.operations_manifest_sha256!==hash(manifest))throw Error('browser fixture/package not released');
  if(a.site!=='https://alma.siteengine.space/'||!/^jct_[a-f0-9]{32}$/.test(a.marker))throw Error('site/marker');
  const origin=new URL(a.site).origin;
  for(const key of ['page_url','submit_url','form_selector','submission_request_token','email_selector'])if(typeof a.browser[key]!=='string'||!a.browser[key])throw Error('allocated browser input missing');
  if(new URL(a.browser.page_url).origin!==origin||new URL(a.browser.submit_url).origin!==origin)throw Error('foreign URL');
  if(!Array.isArray(a.browser.response_id_path)||!a.browser.response_id_path.length)throw Error('J0 vendor response ID path missing; no discovery substitute');
  const controller=fs.readFileSync(path.join(__dirname,'consent-pinned/consent-browser.js'));
  if(hash(controller)!=='b5969d67e38a3ddd93c3a2eba00cbf95be55a0042c02bf053c0af40c47791c26')throw Error('published Consent controller hash');
  function journal(jpin){const b=pinned(journalFile,jpin),j=JSON.parse(b);if(j.assignment!==a.assignment||j.allocation_sha256!==pin||j.marker!==a.marker||j.pending||j.stopped||j.closed)throw Error('current journal ownership/stop state');return b.toString();}
  const initialJournal=journal(initialJournalPin);
  const {chromium}=require('playwright');const browser=await chromium.launch({channel:'chrome',headless:false});
  let timer,actionTimer,lines,failed=false,closed=false,blocked=0,position=0,ackFault=false;
  const returned={};const sequence=['grant','submit A','admit A','withdraw','submit B','admit B','regrant','submit C','admit C','close'];
  try{
    const context=await browser.newContext({serviceWorkers:'block'});
    await context.route('**/*',async route=>{if(new URL(route.request().url()).origin!==origin){blocked++;await route.abort();}else await route.continue();});
    const page=await context.newPage();page.setDefaultTimeout(15000);
    async function load(bytes,jpin){await page.goto(a.browser.page_url);await page.addScriptTag({content:controller.toString()});
      await page.evaluate(async x=>{window.__jointOperations=await window.SiteEngineJointConsent.start(...x);},[raw.toString(),pin,releaseRaw.toString(),releasePin,bytes,jpin]);}
    timer=setTimeout(()=>{failed=true;browser.close();},600000);await load(initialJournal,initialJournalPin);
    lines=readline.createInterface({input:process.stdin,crlfDelay:Infinity});
    console.log(JSON.stringify({status:'READY',commands:'grant JHASH; submit A JHASH; admit A JHASH; withdraw JHASH; submit B JHASH; admit B JHASH; regrant JHASH; submit C JHASH; admit C JHASH; close JHASH'}));
    for await(const line of lines){
      if(line.length>96)throw Error('command bound');const parts=line.trim().split(/ +/),jpin=parts.pop(),command=parts.join(' '),bytes=journal(jpin);
      if(command!==sequence[position++])throw Error('fixed sequence/repeat refused');
      actionTimer=setTimeout(()=>{failed=true;browser.close();},15000);
      if(command==='grant'||command==='regrant'){
        const result=await page.evaluate(async op=>await window.__jointOperations[op](),command);
        console.log(JSON.stringify({status:'PASS',operation:command,journal_sha256:jpin,...result}));
      }else if(command==='withdraw'){
        const result=await page.evaluate(async()=>{const ops=window.__jointOperations;let pending,failed,restored;
          try{pending=await ops.beginWithdrawalFault();failed=await ops.failAcknowledgement();}finally{restored=await ops.restoreFault();}
          if(pending.status!=='pending'||failed.status!=='failed'||restored.restoration!=='VERIFIED'||restored.fetch_override!==false)throw Error('Consent capture fault/restoration proof');
          const acknowledged=await ops.retryWithdrawal();return {pending:'VERIFIED',failed:'VERIFIED',restoration:'VERIFIED',...acknowledged};});
        ackFault=true;console.log(JSON.stringify({status:'PASS',operation:command,journal_sha256:jpin,...result}));
      }else if(command.startsWith('submit ')){
        const label=command.slice(-1),form=page.locator(a.browser.form_selector);
        await form.locator(a.browser.email_selector).fill(a.marker+'.'+(label==='C'?'c':'a')+'@example.invalid');
        for(const f of a.browser.required_fields||[]){if(/se_joint_|se_consent_/.test(f.selector)||f.selector===a.browser.email_selector)throw Error('field override collision');await form.locator(f.selector).fill(f.value);}
        const responsePromise=page.waitForResponse(r=>r.url()===a.browser.submit_url&&r.request().method()==='POST'&&(r.request().postData()||'').includes(a.browser.submission_request_token));
        await page.evaluate(label=>window.__jointOperations.submit(label),label);
        const response=await responsePromise,body=await response.json();if(!response.ok()||body.success!==true)throw Error('individual vendor persistence failed');
        const id=exactVendorId(body,a.browser.response_id_path),payload=body?.data?.result?.siteengine_ads_bridge;
        if(Boolean(payload)!==(label!=='B')||(payload&&payload.transaction_id!=='ff:'+a.form_id+':'+id))throw Error('actual confirmation identity/verdict');
        const stamp=await page.evaluate(()=>{const s=window.SiteEngineConsent.getConsent();return {receipt_id:s.receipt_id,event_id:s.event_id};});
        returned[label]=id;console.log(JSON.stringify({status:'PASS',operation:command,entry_id:id,advertising_payload:Boolean(payload),pending_failed_ack_verified:ackFault,external_requests_blocked:blocked,allocation_sha256:pin,release_sha256:releasePin,journal_sha256:jpin,...stamp}));
      }else if(command.startsWith('admit ')){
        const label=command.slice(-1);if(!returned[label])throw Error('no exact vendor ID from this submission');
        const result=await page.evaluate(async x=>await window.__jointOperations.admit(...x),[label,returned[label],bytes,jpin]);
        await load(bytes,jpin);console.log(JSON.stringify({status:'PASS',operation:command,journal_sha256:jpin,...result}));
      }else{const result=await page.evaluate(async()=>await window.__jointOperations.close());closed=true;console.log(JSON.stringify({status:'PASS',operation:'close',journal_sha256:jpin,...result}));break;}
      clearTimeout(actionTimer);actionTimer=null;
    }
    if(!closed)throw Error('explicit complete close required');
  }catch(e){failed=true;console.log(JSON.stringify({status:'FAIL',reason:'browser operation failed; preserve isolated context/journal evidence, no new chain or retry'}));}
  finally{clearTimeout(actionTimer);clearTimeout(timer);if(lines)lines.close();await browser.close();}
  process.exitCode=failed?1:0;
}
main().catch(()=>{console.error(JSON.stringify({status:'FAIL',reason:'admission/input pin failure; no browser operation authorized'}));process.exitCode=1;});
