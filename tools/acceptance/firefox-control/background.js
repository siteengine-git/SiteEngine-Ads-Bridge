/* Temporary Firefox MV2: one existing tab, no cookies/headers/logged raw bodies. */
'use strict';
const C=AlmaFirefoxCore;let state=null;
const csp="default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' data:; connect-src 'self'; form-action 'self'; worker-src 'none'; frame-src 'none'; child-src 'none'; object-src 'none'; base-uri 'self'";
function halt(){if(state)state.halted=true;}
async function configure(raw,pins){
 const b=await C.bundle(raw,pins),tabs=await browser.tabs.query({}),same=tabs.filter(t=>{try{return new URL(t.url).origin===C.origin;}catch{return false;}});
 const selected=await browser.tabs.query({active:true,currentWindow:true});
 C.need(same.length===1&&selected.length===1&&selected[0].id===same[0].id,'one existing selected Alma tab required');
 if(state){C.need(!state.halted&&state.tab===same[0].id&&['allocation','context','release'].every(k=>state.bundle.pins[k]===pins[k])&&!state.inflight,'cannot replace failed/in-flight control');state.bundle=b;}
 else state={tab:same[0].id,bundle:b,id:crypto.randomUUID(),started:Date.now(),halted:false,blocked:0,probe_blocked:false,documents:[],vendor:[],used:[],inflight:null};
 return summary();
}
function summary(){C.need(state,'control not armed');return {status:state.halted?'FAIL':'ARMED',control_id:state.id,tab_id:state.tab,started:state.started,observed_at:Date.now(),pins:{...state.bundle.pins},operations_manifest_sha256:state.bundle.operations_manifest_sha256,blocked_count:state.blocked,probe_blocked:state.probe_blocked,documents:state.documents.slice(),vendor:state.vendor.slice(),inflight:Boolean(state.inflight),policy:'exact HTTPS origin only; worker/frame/object CSP; one existing tab'};}
function before(d){
 if(!state)return {};
 if(C.blocked(d,state.tab)){if(d.tabId===state.tab&&d.url==='https://se-joint-block.invalid/'+state.id)state.probe_blocked=true;if(state.blocked<256)state.blocked++;else halt();return {cancel:true};}
 if(d.tabId!==state.tab)return {};
 if(Date.now()-state.started>600000)halt();
 if(state.halted)return {cancel:true};
 if(d.method!=='POST'||d.url!==state.bundle.allocation.browser.submit_url)return {};
 try{
  const request=C.vendorFields(d,state.bundle.allocation);if(!request)return {};
  C.need(state.probe_blocked&&state.documents.some(d=>d.csp&&d.status===200&&d.url===state.bundle.allocation.browser.page_url),'pre-load control proof missing');
  C.need(!state.inflight&&state.used.length<3&&request.label===['A','B','C'][state.used.length],'vendor sequence/duplicate/in-flight');
  state.used.push(request.label);const b=state.bundle,filter=browser.webRequest.filterResponseData(d.requestId);
  const job={request,id:d.requestId,filter,pins:{...b.pins},chunks:[],bytes:0,status:null,timer:null};state.inflight=job;
  job.timer=setTimeout(()=>{halt();job.chunks=[];filter.disconnect();},15000);
  filter.ondata=e=>{filter.write(e.data);job.bytes+=e.data.byteLength;if(job.bytes>1048576){halt();job.chunks=[];clearTimeout(job.timer);filter.disconnect();}else job.chunks.push(new Uint8Array(e.data));};
  filter.onerror=()=>{clearTimeout(job.timer);halt();job.chunks=[];};
  filter.onstop=async()=>{
   clearTimeout(job.timer);filter.close();try{
    C.need(!state.halted&&state.inflight===job,'incomplete/failed response stream');
    const bytes=new Uint8Array(job.bytes);let offset=0;for(const part of job.chunks){bytes.set(part,offset);offset+=part.length;}
    const r=C.response(new TextDecoder('utf-8',{fatal:true}).decode(bytes),job.status,b.allocation,request);
    state.vendor.push({...r,pins:job.pins,response_sha256:await C.sha(bytes),response_bytes:bytes.length,complete:true,http_status:job.status,request_id:job.id});state.inflight=null;job.chunks=[];
   }catch{halt();job.chunks=[];}
  };
  return {};
 }catch{halt();return {cancel:true};}
}
browser.webRequest.onBeforeRequest.addListener(before,{urls:['<all_urls>']},['blocking','requestBody']);
browser.webRequest.onHeadersReceived.addListener(d=>{
 if(!state||d.tabId!==state.tab)return {};
 if(state.inflight&&state.inflight.id===d.requestId)state.inflight.status=d.statusCode;
 if(d.type!=='main_frame')return {};
 if(state.documents.length>=10){halt();return {cancel:true};}
 state.documents.push({status:d.statusCode,url:d.url,at:Date.now(),csp:true});
 return {responseHeaders:[...d.responseHeaders,{name:'Content-Security-Policy',value:csp}]};
},{urls:[C.origin+'/*']},['blocking','responseHeaders']);
browser.webRequest.onErrorOccurred.addListener(d=>{if(state&&state.inflight&&state.inflight.id===d.requestId)halt();},{urls:[C.origin+'/*']});
browser.tabs.onRemoved.addListener(id=>{if(state&&id===state.tab)halt();});
// Popup only: no content script or page message can authorize/control this policy.
browser.runtime.onMessage.addListener(async(m,sender)=>{
 C.need(sender.id===browser.runtime.id&&sender.url===browser.runtime.getURL('popup.html'),'extension popup sender required');
 if(m.op==='configure')return configure(m.raw,m.pins);
 if(m.op==='refresh'){C.need(state&&!state.halted,'existing control required');return configure({...state.bundle.raw,journal:m.journal},{...state.bundle.pins,journal:m.pin});}
 if(m.op==='summary')return summary();
 if(m.op==='probe'){
  C.need(state&&!state.halted&&!state.probe_blocked,'single harmless block probe only');
  await browser.tabs.executeScript(state.tab,{code:'void(new Image().src='+JSON.stringify('https://se-joint-block.invalid/'+state.id)+')'});
  await new Promise(r=>setTimeout(r,500));C.need(state.probe_blocked,'native tab interception probe not observed; stop');return summary();
 }
 if(m.op==='stop'){halt();return summary();}
 throw Error('unknown control operation');
});
