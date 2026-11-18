/* Typed fixture business inputs and access checks. No Consent capture, cookies or credentials. */
function demand(ok, message) { if (!ok) throw Error(message); }
async function applyField(form, field) {
  demand(field && typeof field.selector==='string' && typeof field.value==='string' &&
    ['radio','select','text','tel','email'].includes(field.control_type), 'unsupported allocated business control');
  demand(!/se_consent|consent_|google_ad_|se_joint_|password|audit_token|continuity/i.test(field.selector), 'authority/authentication field is not a business input');
  const group=form.locator(field.selector), count=await group.count();
  demand(count>0 && count<=32, 'allocated business control missing/ambiguous');
  const visible=[];
  for(let i=0;i<count;i++){const node=group.nth(i); if(await node.isVisible())visible.push(node);}
  if(!visible.length && field.visible_only===true)return {status:'SKIPPED_NOT_VISIBLE',control_type:field.control_type};
  demand(visible.length>0, 'required control not visible');
  let node;
  if(field.control_type==='radio'){
    const choices=[];for(const n of visible){demand((await n.getAttribute('type'))==='radio','unexpected radio control');if(await n.getAttribute('value')===field.value)choices.push(n);}
    demand(choices.length===1,'allocated radio value missing/duplicate');node=choices[0];
  }else{demand(visible.length===1,'ambiguous allocated control');node=visible[0];}
  const actualName=await node.getAttribute('name');
  demand(!/consent|google_ad_|se_joint_|password|audit_token|continuity/i.test(actualName||''),'authority field alias refused');
  demand(await node.isEnabled(),'allocated control disabled');
  if(field.control_type==='radio'){await node.check();demand(await node.isChecked(),'radio readback mismatch');}
  else if(field.control_type==='select'){
    demand(await node.evaluate(n=>n.tagName.toLowerCase())==='select','expected native select');
    await node.selectOption({value:field.value});demand(await node.inputValue()===field.value,'select readback mismatch');
  }else{
    const type=(await node.getAttribute('type')||'text').toLowerCase();
    demand(type===field.control_type && await node.isEditable(),'unexpected text/tel/email control');
    await node.fill(field.value);demand(await node.inputValue()===field.value,'text readback mismatch');
  }
  return {status:'VERIFIED',control_type:field.control_type};
}
async function verifyAccess(page,a,access,status=200){
  demand(status===200 && page.url()===a.browser.page_url,'unauthenticated/foreign page response');
  demand(typeof access.authenticated_selector==='string' && access.authenticated_selector.length>0 && access.authenticated_selector.length<=256,'reviewed authenticated DOM marker missing');
  const auth=page.locator(access.authenticated_selector),form=page.locator(a.browser.form_selector);
  demand(await auth.count()===1 && await auth.isVisible(),'unauthenticated: supervised login/access proof required');
  demand(await form.count()===1 && await form.isVisible() && Number(await form.getAttribute('data-form_id'))===a.form_id,'allocated form access not verified');
  return true;
}
function attachment(access){
  demand(access.mode==='existing-cdp' && access.exclusive_tab===true,'existing controlled browser/tab capability required; never create an environment');
  const url=new URL(access.endpoint);
  demand(['http:','ws:'].includes(url.protocol)&&['127.0.0.1','localhost'].includes(url.hostname)&&url.port && !url.username&&!url.password,'reviewed existing local browser capability required');
  demand(Number.isInteger(access.context_index)&&access.context_index>=0&&Number.isInteger(access.page_index)&&access.page_index>=0,'exact existing context/tab indexes required');
  return url.href;
}
module.exports={applyField,verifyAccess,attachment};
