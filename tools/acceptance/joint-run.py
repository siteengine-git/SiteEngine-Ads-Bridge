"""Linux owner-side bounded launcher; local preparation only, never SSH or sudo setup."""
import hashlib, json, os, pathlib, selectors, signal, subprocess, sys, time

OPS = {'admit', 'form', 'contact', 'attest', 'milestone', 'adopt', 'prepare', 'probe',
       'dispatch', 'prepare-effective', 'dispatch-effective', 'rejection', 'fault', 'missing', 'policy', 'dnc-on', 'dnc-off', 'archive', 'unarchive', 'rebind', 'browser-evidence', 'begin', 'stage', 'close'}
RUN = '/home/alma.siteengine.space/.se-joint-acceptance/joint-consent-ads-20261031-01'

def command(op, label, argument, allocation, digest, here):
    if op not in OPS or label not in {'-', 'A', 'B', 'C', 'J0', 'J1', 'J2', 'J3', 'J4', 'J5', 'J6', 'J7'} or len(argument) > 32:
        raise ValueError('unallowlisted operation/label')
    if len(digest) != 64 or any(x not in '0123456789abcdef' for x in digest):
        raise ValueError('allocation pin missing')
    argv = ['/usr/local/lsws/lsphp83/bin/php', '/usr/bin/wp',
            '--path=/home/alma.siteengine.space/public_html']
    if op == 'missing': argv += ['--skip-plugins=siteengine-consent']
    return argv + ['eval-file', str(here / 'joint-ops.php'), op, label, argument, str(allocation), digest]

def private_json(path, value):
    with open(path, 'x', encoding='utf-8') as f:
        os.chmod(path, 0o600); json.dump(value, f); f.flush(); os.fsync(f.fileno())

def main():
    if len(sys.argv) != 6: raise ValueError('usage: joint-run.py OP LABEL ARG allocation.json SHA256')
    op, label, argument, filename, digest = sys.argv[1:]
    here = pathlib.Path(__file__).resolve().parent
    allocation = pathlib.Path(filename).resolve()
    if str(allocation.parent) != RUN or allocation.stat().st_mode & 0o777 != 0o600:
        raise ValueError('private allocation path/mode')
    a = json.loads(allocation.read_text())
    if os.name != 'posix' or os.geteuid() != a['owner_uid']: raise ValueError('existing Alma owner route required')
    if hashlib.sha256(allocation.read_bytes()).hexdigest() != digest: raise ValueError('allocation provenance')
    manifest = json.loads((here / 'OPERATIONS-MANIFEST.json').read_text())
    for p, expected in manifest['files'].items():
        if pathlib.PurePosixPath(p).name != p or hashlib.sha256((here / p).read_bytes()).hexdigest() != expected:
            raise ValueError('operations package member provenance')
    run = allocation.parent
    if (run / 'runner-stop.json').exists(): raise ValueError('previous failure retained; no automatic retry/cleanup')
    attempts = list(run.glob('attempt-*.json'))
    for prior in attempts:
        if not (run / prior.name.replace('attempt-', 'result-', 1)).is_file():
            raise ValueError('incomplete prior attempt; preserve evidence, executor recovery required')
    sequence = len(attempts) + 1
    if sequence > 64: raise ValueError('finite command ceiling64')
    receipt = run / ('attempt-%02d.json' % sequence)
    private_json(receipt, {'operation': op, 'label': label, 'status': 'PENDING', 'started': time.time()})
    process = subprocess.Popen(command(op, label, argument, allocation, digest, here),
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
