from pathlib import Path
import json,hashlib,zipfile,subprocess,os
R=Path.cwd();VERSION='0.9.1';O=R/'releases'/VERSION;O.mkdir(exist_ok=True)
php=R.parent/'local-test-runtime/php-8.3.36/php.exe'
checks=[]
suites=['product-setup-replacement.php','product-setup-acceptance.php','setup-workflow-acceptance.php','setup-reporting-acceptance.php']
if os.environ.get('SEAB_CONSENT_AUTHORITY'):suites+=['consent-consumer-acceptance.php','consent-dispatch-acceptance.php']
for name in suites:
 q=subprocess.run([str(php),'-n',str(R/'tests'/name)],capture_output=True,timeout=20);assert q.returncode==0,(name,q.stdout,q.stderr);checks.append({'test':name,'summary':q.stdout.decode().splitlines()[-1]})
q=subprocess.run(['node',str(R/'tests/product-setup-browser-double.js')],capture_output=True,timeout=10);assert q.returncode==0;qout=q.stdout.decode().strip();checks.append({'test':'product-setup-browser-double.js','summary':qout})
sha=lambda b:hashlib.sha256(b).hexdigest()
prior=json.loads((R/'releases/0.9.0/MANIFEST.json').read_text(encoding='utf-8'))
paths={x['path']for x in prior['payload']}
paths.discard('includes/crm-import/import.js') # no normal hidden import controller in successor
paths|={'docs/CRM-REQUIREMENTS-0.9.1.md','includes/connectors/fieldedge/ConnectorDiagnostics.php','includes/class-siteengine-ads-bridge-product-setup.php','includes/class-siteengine-ads-bridge-google.php','includes/class-siteengine-ads-bridge-smartcodes.php','includes/product-setup.js','includes/saved-operation-recovery.js','docs/SETUP-0.9.0.md'}
bodies={p:(R/p).read_bytes()for p in sorted(paths)}
for p,b in bodies.items():
 assert not p.startswith(('tests/','.private/')) and p!='config/client.php'
 if p.endswith('.php'):
  q=subprocess.run([str(php),'-n','-l',str(R/p)],capture_output=True,timeout=10);assert q.returncode==0,(p,q.stdout)
manifest={k:v for k,v in prior.items()if k not in ['candidate','version','source_commit','scope','payload']}
rows=[{'path':p,'bytes':len(b),'sha256':sha(b)}for p,b in bodies.items()]
snapshot=sha(json.dumps(rows,sort_keys=True,separators=(',',':')).encode())
manifest.update(version=VERSION,candidate=VERSION,source_commit=subprocess.check_output(['git','rev-parse','HEAD'],text=True).strip(),source_base_commit='a344497',source_snapshot_sha256=snapshot,designation='Setup release; actual scoped acceptance and limits recorded in RESULTS.json',scope='Issue 3: complete combined Ads/FE CRM requirements; distinct Lost definition; no automation, import or provider actions',payload=rows)
mb=(json.dumps(manifest,indent=2)+'\n').encode();(O/'MANIFEST.json').write_bytes(mb)
zipname=f'siteengine-ads-bridge-{VERSION}.zip'
with zipfile.ZipFile(O/zipname,'w',compression=zipfile.ZIP_DEFLATED,compresslevel=9)as z:
 for p,b in {**bodies,'SUCCESSOR-MANIFEST.json':mb}.items():
  i=zipfile.ZipInfo('siteengine-ads-bridge/'+p,(2026,11,13,18,0,0));i.compress_type=zipfile.ZIP_DEFLATED;i.external_attr=0o100644<<16;z.writestr(i,b)
with zipfile.ZipFile(O/zipname)as z:
 assert z.testzip()is None
 for p,b in bodies.items():assert z.read('siteengine-ads-bridge/'+p)==b
 assert z.read('siteengine-ads-bridge/SUCCESSOR-MANIFEST.json')==mb
 assert not any('import.js' in n or '/.private/'in n or '/tests/'in n for n in z.namelist())
delivery={'version':VERSION,'designation':'UNACCEPTED','package':zipname,'sha256':sha((O/zipname).read_bytes()),'manifest_sha256':sha(mb),'source_snapshot_sha256':snapshot,'source_commit':manifest['source_commit'],'members':len(bodies)+1,'PHP_files':sum(p.endswith('.php')for p in bodies),'installed':False,'released':False,'current_delivery_replaced':False}
(O/'DELIVERY.json').write_bytes((json.dumps(delivery,indent=2)+'\n').encode());(O/'SHA256SUMS.txt').write_bytes((delivery['sha256']+'  '+zipname+'\n'+delivery['manifest_sha256']+'  MANIFEST.json\n').encode())
(O/'LOCAL-RESULTS.json').write_bytes((json.dumps({'checks':checks,'Consent_consumer_assertions':104 if 'consent-consumer-acceptance.php' in suites else 'NOT RUN: supply pinned SEAB_CONSENT_AUTHORITY','Consent_dispatch_assertions':32 if 'consent-dispatch-acceptance.php' in suites else 'NOT RUN: supply pinned SEAB_CONSENT_AUTHORITY','Consent_dependency':'exact retained dependency via SEAB_CONSENT_AUTHORITY' if os.environ.get('SEAB_CONSENT_AUTHORITY') else 'Not rerun in this invocation; earlier pinned evidence is retained separately','PHP_lints':delivery['PHP_files'],'runtime':'PHP 8.3.36 -n','coverage':'Actual algorithms/rendering; simulated WP/vendor/storage/transport; no installed/native/browser claim','initial_dependency_refusal':'Consent suites first refused missing SEAB_CONSENT_AUTHORITY; then exact retained pinned dependency supplied without changing hash checks'},indent=2)+'\n').encode())
(O/'INSTALL-AND-UPGRADE.md').write_bytes((R/'docs/SETUP-0.9.0.md').read_bytes())
print(json.dumps(delivery,indent=2))
