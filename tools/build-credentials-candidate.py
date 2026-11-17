"""Focused 0.9.4 package; use canonical checkout and pinned Base 2.5.0 test dependency."""
from pathlib import Path
import hashlib,json,os,subprocess,zipfile
R=Path(__file__).resolve().parents[1];O=R/'releases/0.9.4';O.mkdir(exist_ok=True)
php=R.parent/'local-test-runtime/php-8.3.36/php.exe'
env=os.environ.copy();env['OPENSSL_CONF']=str(php.parent/'extras/ssl/openssl.cnf')
args=[str(php),'-n','-d','extension_dir='+str(php.parent/'ext'),'-d','extension=openssl','-d','extension=sodium']
checks=[]
for name,extra in [('base-credentials-acceptance.php',[]),('base-credentials-acceptance.php',['--without-base']),('product-setup-replacement.php',[]),('product-setup-acceptance.php',[]),('setup-reporting-acceptance.php',[]),('setup-workflow-acceptance.php',[])]:
 q=subprocess.run(args+[str(R/'tests'/name)]+extra,env=env,capture_output=True,timeout=25)
 assert q.returncode==0,(name,q.stdout,q.stderr)
 checks.append({'test':name,'args':extra,'result':q.stdout.decode().splitlines()[-1]})
q=subprocess.run(['node',str(R/'tests/product-setup-browser-double.js')],capture_output=True,timeout=10);assert q.returncode==0,q.stderr
checks.append({'test':'product-setup-browser-double.js','result':q.stdout.decode().strip()})
prior=json.loads((R/'releases/0.9.3/MANIFEST.json').read_text(encoding='utf-8'))
paths={p['path']for p in prior['payload']}|{'includes/class-siteengine-ads-bridge-credentials.php','docs/GOOGLE-CREDENTIALS.md'}
commit=subprocess.check_output(['git','rev-parse','HEAD'],cwd=R,text=True).strip()
bodies={p:subprocess.check_output(['git','show',commit+':'+p],cwd=R)for p in sorted(paths)}
sha=lambda b:hashlib.sha256(b).hexdigest()
for p,b in bodies.items():
 assert (R/p).read_bytes()==b,'Uncommitted payload '+p
 assert p!='config/client.php' and not p.startswith(('.private/','tests/'))
 if p.endswith('.php'):
  q=subprocess.run([str(php),'-n','-l',str(R/p)],capture_output=True,timeout=10);assert q.returncode==0,(p,q.stdout)
rows=[{'path':p,'bytes':len(b),'sha256':sha(b)}for p,b in bodies.items()]
m={'version':'0.9.4','source_commit':commit,'designation':'Development installable; installed/provider limits in RESULTS.json','dependency':{'Base':'2.5.0','source':'277c8c629dd3b3ffff32011030d055b942ea5076','contract':'credentials-1'},'payload':rows}
mb=(json.dumps(m,indent=2)+'\n').encode();(O/'MANIFEST.json').write_bytes(mb)
name='siteengine-ads-bridge-0.9.4.zip';out=O/name
if out.exists():assert False,'Preserve existing candidate; do not overwrite'
with zipfile.ZipFile(out,'x',compression=zipfile.ZIP_DEFLATED,compresslevel=9)as z:
 for p,b in {**bodies,'SUCCESSOR-MANIFEST.json':mb}.items():
  i=zipfile.ZipInfo('siteengine-ads-bridge/'+p,(2026,11,17,12,0,0));i.compress_type=zipfile.ZIP_DEFLATED;i.external_attr=0o100644<<16;z.writestr(i,b)
with zipfile.ZipFile(out)as z:
 assert z.testzip()is None and len(z.namelist())==len(rows)+1
 for p,b in bodies.items():assert z.read('siteengine-ads-bridge/'+p)==b
d={'version':'0.9.4','source_commit':commit,'package':name,'sha256':sha(out.read_bytes()),'manifest_sha256':sha(mb),'members':len(rows)+1,'PHP_files':sum(p.endswith('.php')for p in bodies),'installed':False,'released':False}
(O/'DELIVERY.json').write_bytes((json.dumps(d,indent=2)+'\n').encode())
(O/'SHA256SUMS.txt').write_bytes((d['sha256']+'  '+name+'\n'+d['manifest_sha256']+'  MANIFEST.json\n').encode())
(O/'INSTALL-AND-UPGRADE.md').write_bytes((R/'docs/GOOGLE-CREDENTIALS.md').read_bytes())
(O/'LOCAL-RESULTS.json').write_bytes((json.dumps({'runtime':'PHP8.3.36 + OpenSSL/Sodium; Node','checks':checks,'lints':d['PHP_files'],'limits':'Real pinned Base crypto/service, AB code; WP/storage/HTTP/DOM doubles. No installed Google acceptance claimed.'},indent=2)+'\n').encode())
print(json.dumps(d,indent=2))
