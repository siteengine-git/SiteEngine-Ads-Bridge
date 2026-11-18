"""Actual aggregate supervisor in bounded local PHP children; no real SQL/transport."""
import pathlib,subprocess,json,tempfile,sys
root=pathlib.Path(__file__).resolve().parent
php=pathlib.Path(sys.argv[1]).resolve()
checks=0
for mode in ['success','throw','row-failure']:
 with tempfile.TemporaryDirectory(prefix='seab-telemetry-')as directory:
  store=pathlib.Path(directory)/'owned.json';store.write_text('{}')
  p=subprocess.run([str(php),'-n',str(root/'export-runtime-fixture.php'),mode,str(store)],capture_output=True,timeout=10)
  assert len(p.stdout)+len(p.stderr)<65536
  data=json.loads(store.read_text())
  assert p.returncode==(0 if mode=='success'else 2)
  assert data['seab_last_export_attempt']['status']==('complete'if mode=='success'else'failed')
  assert ('seab_last_export_runtime'in data)==(mode=='success')
  checks+=3;print('PASS '+mode+' actual child exit, durable attempt and success-record separation')
print('SUMMARY '+str(checks)+' PASS; real local PHP subprocesses, synthetic option/transport')
