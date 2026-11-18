/* Joint fixture only. Pure pin/response policy; no network, capture or storage on load. */
(function(root){'use strict';
const origin='https://alma.siteengine.space', pin=v=>typeof v==='string'&&/^[a-f0-9]{64}$/.test(v);
function need(v,m){if(!v)throw Error('STOP: '+m);}
async function sha(bytes){return Array.from(new Uint8Array(await crypto.subtle.digest('SHA-256',typeof bytes==='string'?new TextEncoder().encode(bytes):bytes))).map(v=>v.toString(16).padStart(2,'0')).join('');}
async function bundle(raw,pins){
 const v={};for(const key of ['allocation','release','context','journal','manifest']){
  need(typeof raw[key]==='string'&&new TextEncoder().encode(raw[key]).length<=1048576,'private file absent/oversize');
  if(key!=='manifest')need(pin(pins[key])&&await sha(raw[key])===pins[key],'independent '+key+' pin mismatch');
  v[key]=JSON.parse(raw[key]);
 }
 const a=v.allocation,c=v.context,r=v.release,j=v.journal;
 need(a.assignment==='ALMA-JOINT-CONSENT-ADS-20261031'&&a.site===origin+'/'&&Number.isSafeInteger(a.form_id)&&a.form_id>0,'allocated site/form');
 need(/^jct_[a-f0-9]{32}$/.test(a.marker)&&new URL(a.browser.page_url).origin===origin&&new URL(a.browser.submit_url).origin===origin,'allocated browser URLs/marker');
 need(JSON.stringify(a.browser.response_id_path)===JSON.stringify(['data','insert_id']),'exact vendor ID path');
 need(c.assignment===a.assignment&&c.allocation_sha256===pins.allocation&&c.target.browser_origin===origin&&c.target.stored_home==='http://alma.siteengine.space'&&c.target.stored_siteurl==='http://alma.siteengine.space'&&c.target.effective_home==='http://alma.siteengine.space/','separate target identities');
 need(c.browser_access.mode==='manual-firefox'&&c.submission_meta_disposition==='RETAIN_NO_DELETE_AUTHORITY','reviewed Firefox/retention context');
 need(r.status==='RELEASED_J1_J6_J7'&&r.allocation_sha256===pins.allocation&&r.context_sha256===pins.context&&r.operations_manifest_sha256===await sha(raw.manifest),'release/context/package binding');
 need(j.assignment===a.assignment&&j.allocation_sha256===pins.allocation&&j.context_sha256===pins.context&&j.marker===a.marker&&!j.pending&&!j.stopped&&!j.closed,'current journal ownership/stop state');
 return {...v,raw,pins:{...pins},operations_manifest_sha256:await sha(raw.manifest)};
}
function blocked(d,tab){
 const belongs=d.tabId===tab||[d.originUrl,d.documentUrl].some(u=>{try{return new URL(u).origin===origin;}catch{return false;}});
 return belongs&&new URL(d.url).origin!==origin;
}
function vendorFields(d,a){
 if(d.method!=='POST'||d.url!==a.browser.submit_url)return null;
 const f=d.requestBody&&d.requestBody.formData;if(!f)return null;
 const one=k=>{need(Array.isArray(f[k])&&f[k].length===1&&typeof f[k][0]==='string','vendor request field '+k);return f[k][0];};
 if(!f.form_id||String(f.form_id[0])!==String(a.form_id))return null;
 const data=one('data');need(new TextEncoder().encode(data).length<=65536,'vendor request byte limit');
 const p=new URLSearchParams(data);need(p.getAll('se_joint_marker').length===1&&p.get('se_joint_marker')===a.marker,'vendor marker');
 const label=p.get('se_joint_case');need(['A','B','C'].includes(label)&&p.getAll('se_joint_case').length===1,'vendor case');
 const receipt=p.get('se_consent_receipt_id');need(typeof receipt==='string'&&receipt.length>0&&receipt.length<=256&&p.getAll('se_consent_receipt_id').length===1,'real receipt echo');
 return {label,receipt_id:receipt,form_id:a.form_id};
}
function response(text,status,a,request){
 need(new TextEncoder().encode(text).length<=1048576&&status===200,'complete successful bounded vendor response');
 const body=JSON.parse(text);let id=body?.data?.insert_id;
 if(typeof id==='string'&&/^[1-9][0-9]{0,9}$/.test(id))id=Number(id);
 need(body.success===true&&Number.isSafeInteger(id)&&id>0&&id<=9999999999,'individual data.insert_id absent/malformed');
 const payload=body?.data?.result?.siteengine_ads_bridge,allowed=request.label!=='B';
 need(Boolean(payload)===allowed&&(!payload||payload.transaction_id==='ff:'+a.form_id+':'+id),'transaction/payload verdict mismatch');
 return {entry_id:id,form_id:a.form_id,receipt_id:request.receipt_id,label:request.label,advertising_payload:allowed,transaction_id:payload?payload.transaction_id:null};
}
const api={origin,pin,need,sha,bundle,blocked,vendorFields,response};
if(typeof module!=='undefined')module.exports=api;else root.AlmaFirefoxCore=api;
})(globalThis);
