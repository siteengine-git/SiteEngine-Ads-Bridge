"""Pure launcher-contract tests. Does not launch WP, browser, SQL, or any server command."""
import importlib.util, pathlib, sys, unittest
sys.dont_write_bytecode = True
path = pathlib.Path(__file__).resolve().parents[1] / 'tools/acceptance/joint-run.py'
spec = importlib.util.spec_from_file_location('joint_run', path)
m = importlib.util.module_from_spec(spec); spec.loader.exec_module(m)
class Launcher(unittest.TestCase):
    def test_normal_route(self):
        c = m.command('probe','A','allow','/private/allocation.json','a'*64,path.parent)
        self.assertEqual(c[0], '/usr/local/lsws/lsphp83/bin/php')
        self.assertNotIn('--skip-plugins=siteengine-consent', c)
        self.assertEqual(c[-5:], ['probe','A','allow','/private/allocation.json','a'*64])
    def test_missing_is_request_only_skip(self):
        c=m.command('missing','C','-','/private/allocation.json','a'*64,path.parent)
        self.assertEqual([v for v in c if v.startswith('--skip-plugins')], ['--skip-plugins=siteengine-consent'])
        self.assertNotIn('deactivate', c)
    def test_reject_foreign(self):
        for op,label,arg,pin in [('delete-all','A','-','a'*64),('probe','foreign','-','a'*64),('probe','A','x'*33,'a'*64),('probe','A','-','unknown')]:
            with self.subTest(op=op,label=label), self.assertRaises(ValueError): m.command(op,label,arg,'x',pin,path.parent)
    def test_every_released_operation_has_literal_argv(self):
        for op in m.OPS:
            c=m.command(op,'A','-','x','b'*64,path.parent)
            self.assertIn('eval-file',c); self.assertNotIn('shell=True',c)
    def test_finite_runbook_sequence(self):
        import re
        doc=(path.parent/'RUNBOOK-JOINT.md').read_text(encoding='utf-8')
        commands=re.findall(r'`j ([^`]+)`', doc)
        self.assertEqual(len(commands),64)
        for command in commands:
            op,label,argument=command.split()
            self.assertEqual(m.command(op,label,argument,'allocation.json','a'*64,path.parent)[-5:-2],[op,label,argument])
    def test_actual_sources_compile_without_execution(self):
        import ast
        ast.parse(path.read_text())
        self.assertEqual(len((path.parent/'allocation.template.json').read_bytes())<32768,True)
if __name__ == '__main__': unittest.main()
