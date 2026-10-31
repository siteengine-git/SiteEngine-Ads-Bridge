/* Actual integrated driver admission; launch is a throwing stub. No browser/network. */
const fs=require('fs'),path=require('path'),vm=require('vm'),crypto=require('crypto'),assert=require('assert');
const source=fs.readFileSync(path.join(__dirname,'../tools/acceptance/joint-browser.cjs'),'utf8');
const controller=fs.readFileSync(path.join(__dirname,'../tools/acceptance/consent-pinned/consent-browser.js'));
const hash=b=>crypto.createHash('sha256').update(b).digest('hex');
async function check(mode){
  const a={assignment:'ALMA-JOINT-CONSENT-ADS-20261031',form_id:3,site:'https://alma.siteengine.space/',marker:'jct_'+'a'.repeat(32),browser:{page_url:'https://alma.siteengine.space/fixture',submit_url:'https://alma.siteengine.space/ajax',form_selector:'#fixture',submission_request_token:'fixture',email_selector:'input',response_id_path:mode==='missing-id-path'?[]:['fixture','returned_id']}};
  const allocation=Buffer.from(JSON.stringify(a)),manifest=Buffer.from('{}');
  const release=Buffer.from(JSON.stringify({status:mode==='held'?'HELD':'RELEASED_J1_J6_J7',allocation_sha256:mode==='foreign-allocation'?'0'.repeat(64):hash(allocation),operations_manifest_sha256:mode==='foreign-package'?'0'.repeat(64):hash(manifest)}));
  const journal=Buffer.from(JSON.stringify({assignment:a.assignment,allocation_sha256:hash(allocation),marker:a.marker,pending:mode==='pending-journal'?{operation:'unresolved'}:null}));
  let launched=0;const output=[];
  const sandbox={URL,__dirname:'/fixture',process:{argv:['node','driver','allocation',hash(allocation),'release',mode==='bad-release-pin'?'0'.repeat(64):hash(release),'journal',mode==='bad-journal-pin'?'0'.repeat(64):hash(journal)]},console:{log:x=>output.push(x),error:x=>output.push(x)},
    require:name=>name==='fs'?{readFileSync:p=>p==='allocation'?allocation:p==='release'?release:p==='journal'?journal:String(p).includes('consent-browser.js')?controller:manifest}:name==='playwright'?{chromium:{launch:async()=>{launched++;throw Error('fixture stop before browser');}}}:require(name)};
  vm.runInNewContext(source,sandbox);await new Promise(setImmediate);
  assert.equal(launched,mode==='valid'?1:0,mode);assert.equal(sandbox.process.exitCode,1);assert.equal(JSON.parse(output.at(-1)).status,'FAIL');
  return sandbox;
}
(async()=>{
  let sandbox;for(const mode of ['held','foreign-allocation','foreign-package','bad-release-pin','bad-journal-pin','pending-journal','missing-id-path','valid'])sandbox=await check(mode);
  assert.equal(sandbox.exactVendorId({vendor:{created:{id:'1001'}}},['vendor','created','id']),1001);
  for(const value of [undefined,null,0,-1,'latest','01','1.0',{}])assert.throws(()=>sandbox.exactVendorId({id:value},['id']));
  assert.throws(()=>sandbox.exactVendorId({id:1},[]));
  for(const competing of ['.saveConsent(','.rejectNonEssential(','.retryAudit(','window.fetch='])assert.ok(!source.includes(competing));
  console.log('PASS 8 actual-driver admission fixtures +10 exact-ID parser checks; pinned Consent delegation, launch stub only');
})().catch(e=>{console.error(e);process.exitCode=1;});
