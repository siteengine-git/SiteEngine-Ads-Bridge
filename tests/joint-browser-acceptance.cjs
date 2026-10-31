const assert=require('assert'),fs=require('fs');
const {applyField,verifyAccess,attachment}=require('../tools/acceptance/joint-browser-controls.cjs');
const {exactVendorId}=require('../tools/acceptance/joint-browser.cjs');let checks=0;
function node(type,value='',visible=true,name='business'){return {type,value,visible,name,checked:false,async isVisible(){return this.visible},async getAttribute(k){return ({type:this.type,value:this.value,name:this.name,'data-form_id':'4'})[k]},async isEnabled(){return true},async isEditable(){return true},async check(){this.checked=true},async isChecked(){return this.checked},async evaluate(){return type==='select'?'select':'input'},async selectOption(o){this.value=o.value},async inputValue(){return this.value},async fill(v){this.value=v},async count(){return 1}}}
function form(nodes){return {locator(){return {count:async()=>nodes.length,nth:i=>nodes[i]}}}}
async function refuse(f){await assert.rejects(f);checks++}
async function admission(mode){
 const vm=require('vm'),crypto=require('crypto'),path=require('path'),hash=b=>crypto.createHash('sha256').update(b).digest('hex');
 const a={assignment:'fixture',site:'https://alma.siteengine.space/',marker:'jct_'+'a'.repeat(32),browser:{page_url:'https://alma.siteengine.space/fixture',submit_url:'https://alma.siteengine.space/ajax',form_selector:'#fixture',submission_request_token:'fixture',email_selector:'input',response_id_path:mode==='missing-id-path'?[]:['data','insert_id']}};
 const ar=Buffer.from(JSON.stringify(a)),manifest=Buffer.from('{}');
 const c=Buffer.from(JSON.stringify({allocation_sha256:hash(ar),target:{browser_origin:'https://alma.siteengine.space',stored_home:'http://alma.siteengine.space',stored_siteurl:'http://alma.siteengine.space'},browser_access:{mode:'existing-cdp',exclusive_tab:true,endpoint:'http://127.0.0.1:9222',context_index:0,page_index:0}}));
 const release=Buffer.from(JSON.stringify({status:mode==='held'?'HELD':'RELEASED_J1_J6_J7',allocation_sha256:mode==='foreign-allocation'?'0'.repeat(64):hash(ar),context_sha256:hash(c),operations_manifest_sha256:mode==='foreign-package'?'0'.repeat(64):hash(manifest)}));
 const journal=Buffer.from(JSON.stringify({assignment:a.assignment,allocation_sha256:hash(ar),marker:a.marker,pending:mode==='pending-journal'?{}:null}));
 const controller=fs.readFileSync(path.join(__dirname,'../tools/acceptance/consent-pinned/consent-browser.js'));let attached=0;
 const module={exports:{}},sandbox={module,URL,__dirname:'/fixture',process:{argv:['node','driver','allocation',hash(ar),'release',mode==='bad-release-pin'?'0'.repeat(64):hash(release),'journal',mode==='bad-journal-pin'?'0'.repeat(64):hash(journal),'context',mode==='bad-context-pin'?'0'.repeat(64):hash(c)],exit:n=>{sandbox.exit=n}},console:{log:()=>{},error:()=>{}}};
 sandbox.require=name=>name==='./joint-browser-controls.cjs'?require('../tools/acceptance/joint-browser-controls.cjs'):name==='fs'?{readFileSync:p=>p==='allocation'?ar:p==='release'?release:p==='journal'?journal:p==='context'?c:String(p).includes('consent-browser.js')?controller:manifest}:name==='playwright'?{chromium:{connectOverCDP:async()=>{attached++;throw Error('fixture stop before browser');}}}:require(name);
 sandbox.require.main=module;vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../tools/acceptance/joint-browser.cjs'),'utf8'),sandbox);await new Promise(setImmediate);
 assert.equal(attached,mode==='valid'?1:0,mode);assert.equal(sandbox.exit,1);checks++;
}
(async()=>{
 for(const mode of ['held','foreign-allocation','foreign-package','bad-release-pin','bad-journal-pin','bad-context-pin','pending-journal','missing-id-path','valid'])await admission(mode);
 for(const type of ['radio','select','text','tel','email']){const n=node(type,type==='radio'?'chosen':'');assert.equal((await applyField(form([n]),{selector:'[name=business]',control_type:type,value:'chosen'})).status,'VERIFIED');checks++}
 assert.equal((await applyField(form([node('text','',false)]),{selector:'input',control_type:'text',value:'x',visible_only:true})).status,'SKIPPED_NOT_VISIBLE');checks++;
 await refuse(()=>applyField(form([node('text','',false)]),{selector:'input',control_type:'text',value:'x'}));
 await refuse(()=>applyField(form([node('checkbox')]),{selector:'input',control_type:'radio',value:'x'}));
 await refuse(()=>applyField(form([node('text','','true','se_consent_receipt')]),{selector:'input',control_type:'text',value:'x'}));
 await refuse(()=>applyField(form([node('text')]),{selector:'[name=consent_receipt]',control_type:'text',value:'x'}));
 await refuse(()=>applyField(form([node('radio','wrong')]),{selector:'input',control_type:'radio',value:'x'}));
 const a={form_id:4,browser:{page_url:'https://alma.siteengine.space/fixture',form_selector:'#form'}};
 const access={authenticated_selector:'#reviewed-auth',mode:'existing-cdp',exclusive_tab:true,endpoint:'http://127.0.0.1:9222',context_index:0,page_index:0};
 const page={url:()=>a.browser.page_url,locator:()=>node('text')};assert(await verifyAccess(page,a,access));checks++;
 await refuse(()=>verifyAccess(page,a,access,401));await refuse(()=>verifyAccess({...page,url:()=>'/login'},a,access));
 await refuse(()=>verifyAccess({...page,locator:()=>({...node('text'),count:async()=>0})},a,access));
 assert.equal(attachment(access),'http://127.0.0.1:9222/');checks++;
 for(const change of [{exclusive_tab:false},{mode:'launch'},{endpoint:'http://remote:9222'},{context_index:null}]){assert.throws(()=>attachment({...access,...change}));checks++}
 assert.equal(exactVendorId({data:{insert_id:'123'}},['data','insert_id']),123);checks++;
 for(const v of [null,0,-1,'latest','01',{}]){assert.throws(()=>exactVendorId({id:v},['id']));checks++}
 const s=fs.readFileSync(require.resolve('../tools/acceptance/joint-browser.cjs'),'utf8');assert(!/\.launch\(|\.newContext\(|\.newPage\(/.test(s));assert(s.indexOf("await page.route(")<s.indexOf('await page.goto('));assert(s.includes('if(!accessReady)throw'));checks+=3;
 console.log(`PASS ${checks} typed/access/exact-ID/attach-only checks; locator/browser doubles only, no browser execution`);
})().catch(e=>{console.error(e);process.exitCode=1});
