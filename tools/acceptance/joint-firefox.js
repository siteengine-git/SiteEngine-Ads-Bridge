/* Firefox DevTools page helper. Pinned Consent controller only; no capture on load. */
(function(root){'use strict';const C=root.AlmaFirefoxCore;let session=null;
function privateFile(label){return new Promise((resolve,reject)=>{
 const panel=document.createElement('div'),title=document.createElement('p'),input=document.createElement('input');
 panel.style.cssText='position:fixed;z-index:2147483647;top:10px;left:10px;padding:12px;background:white;color:black;border:3px solid black';
 title.textContent='Choose PRIVATE '+label+' (never uploaded; 15 second limit)';input.type='file';panel.append(title,input);document.body.append(panel);
 const timer=setTimeout(()=>{panel.remove();reject(Error('private file selection timeout'));},15000);
 input.onchange=async()=>{clearTimeout(timer);try{const f=input.files[0];C.need(f&&f.size<=1048576,'private file absent/oversize');const bytes=await f.text();panel.remove();resolve(bytes);}catch(e){panel.remove();reject(e);}};
});}
function visible(e){return e&&e.getClientRects().length>0&&getComputedStyle(e).visibility!=='hidden';}
function access(a,requireForm=true){
 C.need(location.origin===C.origin&&location.href.split('#')[0]===a.browser.page_url.split('#')[0],'exact Firefox origin/page');
 C.need(document.body.classList.contains('logged-in')&&visible(document.querySelector('#wpadminbar')),'existing WordPress authenticated session not verified');
 const forms=document.querySelectorAll(a.browser.form_selector);if(requireForm)C.need(forms.length===1&&visible(forms[0])&&Number(forms[0].getAttribute('data-form_id'))===a.form_id,'visible exact allocated form');return forms[0];
}
function fill(form,f){
 C.need(f&&typeof f.selector==='string'&&typeof f.value==='string'&&['radio','select','text','tel','email'].includes(f.control_type),'typed business input required');
 C.need(!/consent|google_ad_|se_joint_|password|audit_token|continuity/i.test(f.selector),'authority/auth input refused');
 const nodes=[...form.querySelectorAll(f.selector)];C.need(nodes.length>0&&nodes.length<=32,'missing/ambiguous control');const shown=nodes.filter(visible);
 if(!shown.length&&f.visible_only===true)return 'SKIPPED_NOT_VISIBLE';C.need(shown.length,'required input hidden');
 C.need(shown.every(n=>!n.disabled&&!/consent|google_ad_|se_joint_|password|audit_token|continuity/i.test(n.name||'')),'authority alias/disabled control');
 let node;if(f.control_type==='radio'){C.need(shown.every(n=>n.type==='radio'),'unexpected radio');const chosen=shown.filter(n=>n.value===f.value);C.need(chosen.length===1,'missing/duplicate radio choice');node=chosen[0];node.click();C.need(node.checked,'radio readback');}
 else{C.need(shown.length===1,'ambiguous control');node=shown[0];
  if(f.control_type==='select'){C.need(node.tagName==='SELECT'&&[...node.options].some(o=>o.value===f.value&&!o.disabled),'select value absent');node.value=f.value;}
  else{C.need(node.tagName==='INPUT'&&node.type===f.control_type&&!node.readOnly,'unexpected text/tel/email');node.value=f.value;}
  node.dispatchEvent(new Event('input',{bubbles:true}));node.dispatchEvent(new Event('change',{bubbles:true}));C.need(node.value===f.value,'business input readback');
 }return 'VERIFIED';
}
async function control(bytes,pin,b){
 C.need(C.pin(pin)&&await C.sha(bytes)===pin,'independent control evidence pin');const e=JSON.parse(bytes);
 C.need(e.status==='ARMED'&&e.probe_blocked===true&&!e.inflight&&e.operations_manifest_sha256===b.operations_manifest_sha256,'native pre-load guard evidence');
 C.need(['allocation','release','context','journal'].every(k=>e.pins[k]===b.pins[k]),'control/current journal pins differ');
 C.need(Date.now()-e.started>=0&&Date.now()-e.started<=600000&&e.documents.some(d=>d.csp===true&&d.status===200&&d.url===b.allocation.browser.page_url),'fresh guarded document evidence');return e;
}
async function run(fn){try{C.need(session&&!session.halted&&Date.now()-session.started<=600000,'active bounded session required');access(session.b.allocation,false);return await fn(session);}catch(e){if(session)session.halted=true;throw e;}}
const api={
 async start(pins,controlPin){
  C.need(!session,'no second browser chain/session');const raw={};for(const k of ['allocation','release','context','journal','manifest'])raw[k]=await privateFile(k+'.json');
  const b=await C.bundle(raw,pins),guard=await control(await privateFile('firefox-control-evidence.json'),controlPin,b);access(b.allocation);
  C.need(navigator.serviceWorker&&typeof navigator.serviceWorker.getRegistrations==='function','service-worker inspection unavailable');
  C.need((await navigator.serviceWorker.getRegistrations()).length===0,'existing service worker: stop; never unregister');
  C.need(root.SiteEngineJointConsent&&typeof root.SiteEngineJointConsent.start==='function','load exact pinned Consent controller');
  const labels=Object.keys(b.journal.entries||{});C.need(JSON.stringify(labels)===JSON.stringify(['A','B','C'].slice(0,labels.length))&&labels.length<=3,'acknowledged A/B/C resume boundary');
  session={b,guard,started:guard.started,halted:false,position:labels.length*3,capture:null,receipts:{},pending:false};
  try{session.ops=await root.SiteEngineJointConsent.start(raw.allocation,pins.allocation,raw.release,pins.release,raw.journal,pins.journal);}catch(e){session.halted=true;throw e;}
  return {status:'ACCESS_VERIFIED',control_id:guard.control_id};
 },
 async refresh(journalPin,controlPin){return run(async s=>{const raw={...s.b.raw,journal:await privateFile('current journal.json')},pins={...s.b.pins,journal:journalPin};const b=await C.bundle(raw,pins);const guard=await control(await privateFile('refreshed control evidence'),controlPin,b);C.need(guard.control_id===s.guard.control_id,'different tab control');s.b=b;s.guard=guard;return {status:'PINS_REFRESHED',journal_sha256:journalPin};});},
 async step(command){return run(async s=>{
  const sequence=['grant','submit A','admit A','withdraw','submit B','admit B','regrant','submit C','admit C','close'];C.need(command===sequence[s.position],'fixed A/B/C sequence, no retry');
  if(['grant','withdraw','regrant'].includes(command)||command.startsWith('submit ')){access(s.b.allocation);C.need(Date.now()-s.guard.observed_at>=0&&Date.now()-s.guard.observed_at<=15000,'fresh control readback required before capture/submission');}
  const label=command.slice(-1);let result;
  if(command==='grant'||command==='regrant'){result=await s.ops[command]();s.capture=result;}
  else if(command==='withdraw'){
   let p,f,r;try{p=await s.ops.beginWithdrawalFault();f=await s.ops.failAcknowledgement();}finally{r=await s.ops.restoreFault();}
   C.need(p.status==='pending'&&f.status==='failed'&&r.restoration==='VERIFIED'&&r.fetch_override===false,'owned acknowledgement fault/restoration');
   s.pending=true;result=await s.ops.retryWithdrawal();s.capture=result;
  }else if(command.startsWith('submit ')){
   const form=access(s.b.allocation),a=s.b.allocation;fill(form,{selector:a.browser.email_selector,control_type:'email',value:a.marker+'.'+(label==='C'?'c':'a')+'@example.invalid'});
   for(const f of a.browser.required_fields||[]){C.need(f.selector!==a.browser.email_selector,'duplicate email');fill(form,f);}
   C.need(form.checkValidity(),'vendor form invalid');result=s.ops.submit(label);
  }else if(command.startsWith('admit ')){
   C.need(s.receipts[label],'complete exact vendor evidence required');result=await s.ops.admit(label,s.receipts[label].entry_id,s.b.raw.journal,s.b.pins.journal);
  }else{result=await s.ops.close();s.halted=true;}
  s.position++;return {status:'PASS',operation:command,journal_sha256:s.b.pins.journal,...result};
 });},
 async evidence(label,controlPin){return run(async s=>{
  C.need(['A','B','C'].includes(label)&&s.position===({A:2,B:5,C:8}[label])&&!s.receipts[label],'individual submission evidence boundary');
  const e=await control(await privateFile('post-response control evidence'),controlPin,s.b),matches=e.vendor.filter(v=>v.label===label);
  C.need(e.control_id===s.guard.control_id&&matches.length===1,'exact one controlled response');const r=matches[0];
  C.need(r.complete===true&&r.http_status===200&&['allocation','release','context','journal'].every(k=>r.pins[k]===s.b.pins[k]),'complete response/current pins');
  C.need(r.form_id===s.b.allocation.form_id&&r.receipt_id===s.capture.receipt_id&&s.capture.revision===({A:1,B:2,C:3}[label]),'real capture receipt/revision');
  C.need(r.advertising_payload===(label!=='B')&&r.transaction_id===(label==='B'?null:'ff:'+r.form_id+':'+r.entry_id),'response payload transaction');
  const receipt={status:'PASS',operation:'submit '+label,label,entry_id:r.entry_id,form_id:r.form_id,receipt_id:r.receipt_id,receipt_revision:s.capture.revision,advertising_payload:r.advertising_payload,transaction_id:r.transaction_id,response_sha256:r.response_sha256,response_bytes:r.response_bytes,response_complete:true,http_status:200,request_id:r.request_id,allocation_sha256:s.b.pins.allocation,release_sha256:s.b.pins.release,context_sha256:s.b.pins.context,journal_before_sha256:s.b.pins.journal,control_evidence_sha256:controlPin,control_id:e.control_id,pending_failed_ack_verified:label==='B'&&s.pending};
  C.need(C.pin(receipt.response_sha256)&&receipt.response_bytes>0,'bounded response digest');s.receipts[label]=receipt;
  const url=URL.createObjectURL(new Blob([JSON.stringify(receipt,null,2)],{type:'application/json'})),a=document.createElement('a');a.href=url;a.download='browser-'+label+'.json';a.click();setTimeout(()=>URL.revokeObjectURL(url),1000);
  return receipt;
 });}
};root.JointAdsFirefox=api;
if(typeof module!=='undefined')module.exports={privateFile,visible,access,fill,control};
})(globalThis);
