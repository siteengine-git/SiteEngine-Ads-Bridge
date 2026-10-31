"""Linux owner-side bounded launcher; local preparation only, never SSH or sudo setup."""
import hashlib, json, os, pathlib, selectors, signal, subprocess, sys, time

OPS = {'admit', 'form', 'contact', 'attest', 'milestone', 'adopt', 'prepare', 'probe',
       'dispatch', 'consent', 'restore', 'rejection', 'fault', 'missing', 'policy', 'dnc-on', 'dnc-off', 'archive', 'unarchive', 'rebind', 'browser-evidence', 'begin', 'stage', 'close'}
GUARD_SHA = 'f3702f4570a67ccdfb0973e3d3b0e4bc9411bf5b5fb33550e569382c562dde8f'
RUN = '/home/alma.siteengine.space/.se-joint-acceptance/joint-consent-ads-20261031-01'

def command(op, label, argument, allocation, digest, journal_pin, release_pin, context_file, context_pin, here):
    if op not in OPS or label not in {'-', 'A', 'B', 'C', 'J0', 'J1', 'J2', 'J3', 'J4', 'J5', 'J6', 'J7'} or len(argument) > 32:
        raise ValueError('unallowlisted operation/label')
    validate_pins(op, digest, journal_pin, release_pin)
    if not valid_pin(context_pin): raise ValueError('independent private context pin required')
    argv = ['/usr/local/lsws/lsphp83/bin/php', '/usr/bin/wp',
            '--path=/home/alma.siteengine.space/public_html', '--require='+str(here/'joint-startup.php')]
    if op == 'missing': argv += ['--skip-plugins=siteengine-consent']
    return argv + ['eval-file', str(here / 'joint-ops.php'), op, label, argument, str(allocation), digest, journal_pin, release_pin, str(context_file), context_pin]

def valid_pin(value):
    return isinstance(value,str) and len(value)==64 and all(c in '0123456789abcdef' for c in value)

def verify_guard(path, expected_path=RUN+'/cli-control.php', expected_hash=GUARD_SHA):
    p=pathlib.Path(path)
    if str(p)!=expected_path or p.is_symlink() or not p.is_file(): raise ValueError('retained guard omitted/unreadable')
    try: raw=p.read_bytes()
    except OSError: raise ValueError('retained guard unreadable') from None
    if hashlib.sha256(raw).hexdigest()!=expected_hash: raise ValueError('retained guard hash mismatch')
    return True

def private_context(file, pin, allocation, run):
    p=pathlib.Path(file)
    if p.is_symlink() or (os.name == 'posix' and p.stat().st_mode & 0o777 != 0o600) or str(p)!=str(run/'context.json') or not valid_pin(pin) or hashlib.sha256(p.read_bytes()).hexdigest()!=pin:
        raise ValueError('independent private context mismatch')
    c=json.loads(p.read_text())
    if c['allocation_sha256']!=hashlib.sha256((run/'allocation.json').read_bytes()).hexdigest() or c['assignment']!=allocation['assignment']:
        raise ValueError('context is not bound to completed allocation')
    if c.get('guard_path')!=RUN+'/cli-control.php' or c.get('guard_sha256')!=GUARD_SHA: raise ValueError('retained guard binding missing')
    verify_guard(c['guard_path'])
    ref=(run/'reference-map-reviewed.json').read_bytes()
    if hashlib.sha256(ref).hexdigest()!=allocation['reference_map_sha256'] or c['reference_map_sha256']!=allocation['reference_map_sha256']:
        raise ValueError('reviewed reference-map pin mismatch')
    reviewed=json.loads(ref)
    if reviewed['logical_references']!=allocation['logical_references'] or len(allocation['logical_references'])!=29:
        raise ValueError('completed 29-check reference map mismatch')
    return c

def validate_pins(op, allocation, journal, release):
    def pin(x): return isinstance(x,str) and len(x)==64 and all(c in '0123456789abcdef' for c in x)
    if not pin(allocation) or (op=='admit' and (journal!='-' or release!='-')) or (op!='admit' and (not pin(journal) or not pin(release))):
        raise ValueError('independent allocation/current journal/release pins required')

def verify_current_pins(op, run, allocation, journal, release):
    validate_pins(op, allocation, journal, release)
    if op=='admit':
        if (run/'journal.json').exists(): raise ValueError('admission journal already exists')
    else:
        for name,pin in [('journal.json',journal),('fixture-release.json',release)]:
            f=run/name
            if f.is_symlink() or hashlib.sha256(f.read_bytes()).hexdigest()!=pin: raise ValueError('current journal/release pin mismatch')

def verify_members(here):
    manifest=json.loads((here/'OPERATIONS-MANIFEST.json').read_text())
    for name,expected in manifest['files'].items():
        path=pathlib.PurePosixPath(name)
        if path.is_absolute() or '..' in path.parts or '\\' in name or (here/name).is_symlink() or hashlib.sha256((here/name).read_bytes()).hexdigest()!=expected:
            raise ValueError('operations package member provenance')
    cm=here/'consent-pinned/CONSENT-TOOLING-MANIFEST.json'
    if hashlib.sha256(cm.read_bytes()).hexdigest()!='964557de4920cbcb682b5ec0c86f0cbce8ab7cd94601824419d85558877c8883':
        raise ValueError('published Consent tooling pin mismatch')

def private_json(path, value):
    with open(path, 'x', encoding='utf-8') as f:
        os.chmod(path, 0o600); json.dump(value, f); f.flush(); os.fsync(f.fileno())

def main():
    if len(sys.argv) != 10: raise ValueError('usage: joint-run.py OP LABEL ARG allocation.json ALLOCATION_SHA JOURNAL_SHA RELEASE_SHA context.json CONTEXT_SHA')
    op, label, argument, filename, digest, journal_pin, release_pin, context_file, context_pin = sys.argv[1:]
    here = pathlib.Path(__file__).resolve().parent
    allocation = pathlib.Path(filename).resolve()
    if str(allocation.parent) != RUN or allocation.stat().st_mode & 0o777 != 0o600:
        raise ValueError('private allocation path/mode')
    a = json.loads(allocation.read_text())
    if os.name != 'posix' or os.geteuid() != a['owner_uid']: raise ValueError('existing Alma owner route required')
    if hashlib.sha256(allocation.read_bytes()).hexdigest() != digest: raise ValueError('allocation provenance')
    verify_members(here)
    run = allocation.parent
    private_context(context_file, context_pin, a, run)
    verify_current_pins(op, run, digest, journal_pin, release_pin)
    if (run / 'runner-stop.json').exists(): raise ValueError('previous failure retained; no automatic retry/cleanup')
    attempts = list(run.glob('attempt-*.json'))
    for prior in attempts:
        if not (run / prior.name.replace('attempt-', 'result-', 1)).is_file():
            raise ValueError('incomplete prior attempt; preserve evidence, executor recovery required')
    sequence = len(attempts) + 1
    if sequence > 64: raise ValueError('finite command ceiling64')
    receipt = run / ('attempt-%02d.json' % sequence)
    private_json(receipt, {'operation': op, 'label': label, 'status': 'PENDING', 'started': time.time(), 'allocation_sha256':digest, 'journal_before_sha256':journal_pin, 'release_sha256':release_pin, 'context_sha256':context_pin, 'guard_sha256':GUARD_SHA})
    process = subprocess.Popen(command(op, label, argument, allocation, digest, journal_pin, release_pin, context_file, context_pin, here),
                               stdin=subprocess.DEVNULL, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
                               start_new_session=True, cwd=RUN)
    selector = selectors.DefaultSelector()
    for stream in (process.stdout, process.stderr):
        os.set_blocking(stream.fileno(), False); selector.register(stream, selectors.EVENT_READ)
    chunks = {process.stdout: bytearray(), process.stderr: bytearray()}
    start = time.monotonic(); failure = None
    try:
        while selector.get_map():
            if time.monotonic() - start > 18: failure = 'runtime ceiling18s'; break
            for event, _ in selector.select(0.1):
                b = os.read(event.fileobj.fileno(), 4096)
                if not b: selector.unregister(event.fileobj)
                else:
                    remaining = 65536 - sum(map(len, chunks.values()))
                    chunks[event.fileobj] += b[:remaining]
                    if len(b) > remaining: failure = 'aggregate output ceiling64KiB'; break
            if failure: break
    finally:
        # Own fresh process group only. Do not signal workers discovered elsewhere.
        if failure or process.poll() is None:
            try: os.killpg(process.pid, signal.SIGTERM)
            except ProcessLookupError: pass
            try: process.wait(timeout=1)
            except subprocess.TimeoutExpired:
                try: os.killpg(process.pid, signal.SIGKILL)
                except ProcessLookupError: pass
                process.wait(timeout=1)
        selector.close()
    code = process.wait(timeout=1)
    try:
        os.killpg(process.pid, 0)
        failure = failure or 'owned process group remains after parent; executor recovery required'
    except ProcessLookupError:
        pass
    for name, stream in [('stdout', process.stdout), ('stderr', process.stderr)]:
        with open(run / ('attempt-%02d.%s' % (sequence, name)), 'xb') as f:
            os.chmod(f.name, 0o600); f.write(chunks[stream][:65536])
        stream.close()
    try: result = json.loads(chunks[process.stdout].decode())
    except (ValueError, UnicodeError): result = {'status': 'FAIL', 'reason': 'non-JSON/truncated WP output retained privately'}
    after=hashlib.sha256((run/'journal.json').read_bytes()).hexdigest() if (run/'journal.json').is_file() else None
    if result.get('status')=='PASS' and result.get('journal_sha256')!=after: failure=failure or 'post-operation journal pin mismatch'
    if failure or code != 0 or result.get('status') != 'PASS':
        private_json(run / 'runner-stop.json', {'status': 'FAIL', 'operation': op, 'exit': code,
                     'reason': failure or 'operation failed', 'recovery_owner': 'Alma Codex'})
        print(json.dumps({'status': 'FAIL', 'operation': op, 'exit': code, 'reason': failure or 'see private receipt'})); return 1
    private_json(run / ('result-%02d.json' % sequence), {'status': 'PASS', 'exit': code, 'result': result})
    print(json.dumps(result)); return 0

if __name__ == '__main__':
    try: sys.exit(main())
    except Exception as e:
        print(json.dumps({'status': 'FAIL', 'reason': str(e), 'action': 'STOP; preserve private journal/controls'})); sys.exit(1)
