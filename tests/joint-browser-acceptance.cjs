/* Actual driver admission in a VM; Playwright launch is a throwing stub. No browser/network. */
const fs=require('fs'), path=require('path'), vm=require('vm'), crypto=require('crypto'), assert=require('assert');
const source=fs.readFileSync(path.join(__dirname,'../tools/acceptance/joint-browser.cjs'),'utf8');
const hash=b=>crypto.createHash('sha256').update(b).digest('hex');
async function check(mode){
  const allocation=Buffer.from(JSON.stringify({site:'https://alma.siteengine.space/',marker:'jct_'+'a'.repeat(32),browser:{
    page_url:'https://alma.siteengine.space/fixture',submit_url:'https://alma.siteengine.space/ajax',
    form_selector:'#fixture',submit_selector:'button',submission_request_token:'fixture',email_selector:'input'}}));
  const manifest=Buffer.from('{}');
  const release=Buffer.from(JSON.stringify({status:mode==='held'?'HELD':'RELEASED_J1_J6_J7',
    allocation_sha256:mode==='foreign-allocation'?'0'.repeat(64):hash(allocation),
    operations_manifest_sha256:mode==='foreign-package'?'0'.repeat(64):hash(manifest)}));
  let launched=0;const output=[];
  const sandbox={URL,__dirname:'/fixture',process:{argv:['node','driver','allocation',hash(allocation),'release',mode==='bad-release-pin'?'0'.repeat(64):hash(release)]},
    console:{log:x=>output.push(x),error:x=>output.push(x)},
    require:name=>name==='fs'?{readFileSync:p=>p==='allocation'?allocation:p==='release'?release:manifest}:
      name==='playwright'?{chromium:{launch:async()=>{launched++;throw Error('fixture boundary; no browser launched');}}}:require(name)};
  vm.runInNewContext(source,sandbox);await new Promise(setImmediate);
  assert.equal(launched,mode==='valid'?1:0,mode);
  assert.equal(sandbox.process.exitCode,1,'all fixtures stop before browser');
  assert.equal(JSON.parse(output.at(-1)).status,'FAIL');
}
(async()=>{for(const mode of ['held','foreign-allocation','foreign-package','bad-release-pin','valid'])await check(mode);
console.log('PASS 5 actual-driver admission fixtures; launch stub only, no browser/network');})().catch(e=>{console.error(e);process.exitCode=1;});
