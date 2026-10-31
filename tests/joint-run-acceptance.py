"""Pure launcher-contract tests. Does not launch WP, browser, SQL, or any server command."""
import importlib.util, pathlib, sys, unittest
sys.dont_write_bytecode = True
path = pathlib.Path(__file__).resolve().parents[1] / 'tools/acceptance/joint-run.py'
spec = importlib.util.spec_from_file_location('joint_run', path)
m = importlib.util.module_from_spec(spec); spec.loader.exec_module(m)
class Launcher(unittest.TestCase):
    def test_normal_route(self):
        c = m.command('probe','A','allow','/private/allocation.json','a'*64,'b'*64,'c'*64,'context.json','d'*64,path.parent)
        self.assertEqual(c[0], '/usr/local/lsws/lsphp83/bin/php')
        self.assertNotIn('--skip-plugins=siteengine-consent', c)
        self.assertEqual(c[-9:], ['probe','A','allow','/private/allocation.json','a'*64,'b'*64,'c'*64,'context.json','d'*64])
    def test_missing_is_request_only_skip(self):
        c=m.command('missing','C','-','/private/allocation.json','a'*64,'b'*64,'c'*64,'context.json','d'*64,path.parent)
        self.assertEqual([v for v in c if v.startswith('--skip-plugins')], ['--skip-plugins=siteengine-consent'])
        self.assertNotIn('deactivate', c)
        normal=m.command('restore','C','-','/private/allocation.json','a'*64,'b'*64,'c'*64,'context.json','d'*64,path.parent)
        self.assertFalse(any(v.startswith('--skip-plugins') for v in normal))
    def test_reject_foreign(self):
        for op,label,arg,pin in [('delete-all','A','-','a'*64),('probe','foreign','-','a'*64),('probe','A','x'*33,'a'*64),('probe','A','-','unknown')]:
            with self.subTest(op=op,label=label), self.assertRaises(ValueError): m.command(op,label,arg,'x',pin,'b'*64,'c'*64,'context.json','d'*64,path.parent)
    def test_every_released_operation_has_literal_argv(self):
        for op in m.OPS:
            c=m.command(op,'A','-','x','b'*64,'-' if op=='admit' else 'b'*64,'-' if op=='admit' else 'c'*64,'context.json','d'*64,path.parent)
            self.assertIn('--require='+str(path.parent/'joint-startup.php'),c); self.assertIn('eval-file',c); self.assertNotIn('shell=True',c)
    def test_finite_runbook_sequence(self):
        import re
        doc=(path.parent/'RUNBOOK-JOINT.md').read_text(encoding='utf-8')
        commands=re.findall(r'`j ([^`]+)`', doc)
        self.assertEqual(len(commands),64)
        for command in commands:
            op,label,argument=command.split()
            self.assertEqual(m.command(op,label,argument,'allocation.json','a'*64,'-' if op=='admit' else 'b'*64,'-' if op=='admit' else 'c'*64,'context.json','d'*64,path.parent)[-9:-6],[op,label,argument])
    def test_independent_pins_before_launch(self):
        import tempfile, json, hashlib
        with tempfile.TemporaryDirectory(prefix='joint-pin-fixture-') as d:
            run=pathlib.Path(d)
            for name in ['journal.json','fixture-release.json']: (run/name).write_text('{}')
            pin=hashlib.sha256(b'{}').hexdigest()
            m.verify_current_pins('probe',run,'a'*64,pin,pin)
            for jp,rp in [('0'*64,pin),(pin,'0'*64),('-',pin)]:
                with self.assertRaises(ValueError):m.verify_current_pins('probe',run,'a'*64,jp,rp)
            self.assertEqual(list(run.glob('attempt-*')),[])
    def test_guard_fail_closed(self):
        import tempfile, hashlib
        from unittest.mock import patch
        with tempfile.TemporaryDirectory() as d:
            p=pathlib.Path(d)/'guard.php';p.write_bytes(b'harmless fixture')
            digest=hashlib.sha256(p.read_bytes()).hexdigest()
            self.assertTrue(m.verify_guard(str(p),str(p),digest))
            for value,expected in [(str(p),'0'*64),(str(p)+'missing',digest)]:
                with self.assertRaises(ValueError):m.verify_guard(value,value,expected)
            with patch.object(pathlib.Path,'read_bytes',side_effect=PermissionError()):
                with self.assertRaises(ValueError):m.verify_guard(str(p),str(p),digest)
    def test_private_context_and_completed_map(self):
        import tempfile,json,hashlib
        from unittest.mock import patch
        sha=lambda b:hashlib.sha256(b).hexdigest()
        with tempfile.TemporaryDirectory() as d:
            run=pathlib.Path(d);refs=[{'table':'fixture_table','column':str(i),'kind':'entry'} for i in range(29)]
            raw=json.dumps({'logical_references':refs}).encode();(run/'reference-map-reviewed.json').write_bytes(raw)
            a={'assignment':'fixture','reference_map_sha256':sha(raw),'logical_references':refs};ar=json.dumps(a).encode();(run/'allocation.json').write_bytes(ar)
            c={'assignment':'fixture','allocation_sha256':sha(ar),'guard_path':m.RUN+'/cli-control.php','guard_sha256':m.GUARD_SHA,'reference_map_sha256':sha(raw)}
            cr=json.dumps(c).encode();f=run/'context.json';f.write_bytes(cr);f.chmod(0o600)
            with patch.object(m,'verify_guard',return_value=True) as guard:
                self.assertEqual(m.private_context(str(f),sha(cr),a,run),c);guard.assert_called_once()
                with self.assertRaises(ValueError):m.private_context(str(f),'0'*64,a,run)
                (run/'reference-map-reviewed.json').write_bytes(b'{}')
                with self.assertRaises(ValueError):m.private_context(str(f),sha(cr),a,run)
    def test_actual_sources_compile_without_execution(self):
        import ast
        ast.parse(path.read_text())
        self.assertEqual(len((path.parent/'allocation.template.json').read_bytes())<32768,True)
if __name__ == '__main__': unittest.main()
