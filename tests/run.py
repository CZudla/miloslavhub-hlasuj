"""Local regressions. Uses synthetic data and loopback only; never touches production."""
from pathlib import Path
from datetime import datetime, timezone
import argparse, hashlib, json, os, re, shutil, socket, subprocess, sys, tempfile, time
import urllib.request, urllib.parse, urllib.error
from source_fingerprint import source_sha256

ROOT = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser()
parser.add_argument('--php', default=shutil.which('php') or 'C:/php84/php.exe')
parser.add_argument('--browser', action='store_true')
args = parser.parse_args()
report = {'status': 'running', 'production_access': False, 'wordpress_database_integration': 'not run'}
report['started_at'] = datetime.now(timezone.utc).isoformat()
runtime = ROOT/'runtime'
runtime.mkdir(exist_ok=True)
# Invalidate the previous successful run before any new checks start.
(runtime/'test-results.json').write_text(json.dumps(report, indent=2), encoding='utf8')
tested_source = source_sha256(ROOT)
adapter_sha = hashlib.sha256((ROOT/'scripts/requirements_context.py').read_bytes()).hexdigest()

def run(command):
    result = subprocess.run(command, cwd=ROOT, capture_output=True, text=True, encoding='utf8')
    if result.returncode:
        raise AssertionError(result.stdout + result.stderr)
    return result.stdout

for file in ROOT.rglob('*.php'):
    if 'runtime' not in file.parts:
        run([args.php, '-l', str(file)])
report['php_lint'] = 'passed'
for folder in ['frontend', 'wordpress']:
    for file in (ROOT/folder).rglob('*.js'):
        run(['node', '--check', str(file)])
report['javascript_lint'] = 'passed'
report['security_contracts'] = json.loads(run([args.php, str(ROOT/'tests/security.php')]))
report['ai_contracts'] = json.loads(run([args.php, str(ROOT/'tests/ai.php')]))
report['ai_disabled'] = json.loads(run([args.php, str(ROOT/'tests/ai.php'), 'disabled']))

run([sys.executable, str(ROOT/'tests/requirements_context.py')])
report['requirements_context'] = 'passed'
checks = 0
def check(condition, message):
    global checks
    checks += 1
    if not condition: raise AssertionError(message)

with tempfile.TemporaryDirectory(prefix='regression-', dir=runtime) as temp:
    temp = Path(temp)
    frontend = temp/'frontend'
    shutil.copytree(ROOT/'frontend', frontend)
    (frontend/'test-ai').mkdir()
    shutil.copy2(ROOT/'wordpress/miloslavhub-live/assets/ai-admin.js', frontend/'test-ai/ai-admin.js')
    # Test storage cannot collide with existing demos, even on the developer machine.
    storage = temp/'miloslavhub-live-demo'
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0)); port = sock.getsockname()[1]
    base = f'http://127.0.0.1:{port}'
    router = temp/'router.php'
    router.write_text("<?php $path=parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH); if($path!=='/' && is_file(__DIR__.'/frontend'.$path))return false; if(str_starts_with($path,'/demo/')){require __DIR__.'/frontend/demo/index.php';}else{require __DIR__.'/frontend/index.php';}", encoding='utf8')
    fixture = (ROOT/'tests/ai-admin-fixture.php').as_posix().replace("'", "\\'")
    router.write_text(router.read_text(encoding='utf8').replace("if($path!=='/'", "if($path==='/test-ai-admin'){require '"+fixture+"';return;} if($path!=='/'"), encoding='utf8')
    log = (temp/'server.log').open('w', encoding='utf8')
    server = subprocess.Popen([args.php, '-d', f'sys_temp_dir={temp}', '-S', f'127.0.0.1:{port}', '-t', str(frontend), str(router)], stdout=log, stderr=log,
                              creationflags=getattr(subprocess, 'CREATE_NO_WINDOW', 0))
    def request(path, body=None):
        data = urllib.parse.urlencode(body).encode() if body is not None else None
        try:
            response = urllib.request.urlopen(base+path, data=data, timeout=10)
        except urllib.error.HTTPError as error:
            response = error
        raw=response.read().decode('utf8')
        return response.status, raw, dict(response.headers)
    def api(action, sid, **fields):
        status, raw, _ = request('/demo/api.php', {'action': action, 's': sid, **fields})
        return status, json.loads(raw)
    try:
        for _ in range(50):
            try:
                status, html, _ = request('/'); break
            except urllib.error.URLError: time.sleep(.1)
        else: raise AssertionError('PHP test server did not start')
        check(status == 503, 'Missing configuration must fail closed')
        check('miloslavhub.cz/wp-json' not in html, 'Missing config cannot contact production')
        (frontend/'config.php').write_text("<?php\ndefine('MHL_BRAND_NAME', 'Hlasuj!');\ndefine('MHL_API_BASE', '"+base+"/test-api');\ndefine('MHL_FRONTEND_BASE', '"+base+"');\ndefine('MHL_MAIN_SITE', '"+base+"');\ndefine('MHL_DEMO_URL', '"+base+"/demo/');", encoding='utf8')
        status, html, headers = request('/demo/')
        check(status == 200, 'Demo host must load')
        check('no-store' in headers.get('Cache-Control',''), 'Demo cannot be cached')
        sid = re.search(r'const session = "([a-f0-9]{32})"', html).group(1)
        check('https://cdn.jsdelivr.net' not in html and 'https://api.qrserver' not in html, 'QR must be local')
        check('new URL(' in html and 'location.origin' in html, 'Demo join URL follows this installation')
        path = storage/(sid+'.json')
        def load(): return json.loads(path.read_text(encoding='utf8'))
        def save(session): path.write_text(json.dumps(session), encoding='utf8')
        first, second = 'a'*32, 'b'*32
        status, data = api('join', sid, p=first, nickname='PRIVATE-FIRST')
        check(status == 200 and data['stage'] == 1, 'Isolated demo keeps its automatic flow')
        status, _ = api('answer', sid, p=first, answer='C')
        check(status == 400, 'Vote before shared START is rejected')
        api('join', sid, p=second, nickname='PRIVATE-SECOND')
        session = load(); session['question_starts_at'] = time.time()-1; session['question_ends_at'] = time.time()+30; save(session)
        status, data = api('answer', sid, p=first, answer='C')
        check(status == 200 and 'correct' not in data and 'my_question_points' not in data, 'Open vote does not reveal correctness')
        api('answer', sid, p=first, answer='A')
        check(load()['answers']['1'][first] == 'C', 'Duplicate answer cannot replace first vote')
        session = load(); session['question_ends_at'] = time.time()-1; save(session)
        status, _ = api('answer', sid, p=second, answer='C')
        check(status == 400 and second not in load()['answers']['1'], 'Late answer is rejected without storing a vote')
        session = load(); session['stage'] = 7; session['auto_advance_at'] = None; save(session)
        status, data = api('hall', sid, p=first, choice='anonymous')
        check(status == 200 and data['stage'] == 8, 'First participant finishes personally')
        check(load()['stage'] == 7, 'First choice must not finish shared session')
        status, public, _ = request('/demo/api.php?action=state&s='+sid)
        check('PRIVATE-FIRST' not in public and 'PRIVATE-SECOND' not in public, 'Anonymous/unanswered names must not appear in public response')
        status, data = api('hall', sid, p=second, choice='skip')
        check(status == 200 and data['stage'] == 8, 'Second participant can still choose')
        api('join', sid, p=first, nickname='PRIVATE-FIRST')
        check(load()['participants'][first]['hall_choice'] == 'anonymous', 'Rejoining preserves privacy choice')
        status, _ = api('next', sid, pt='wrong-token')
        check(status == 400, 'Invalid presenter token rejected')
        missing = 'f'*32
        request('/demo/api.php?action=state&s='+missing)
        check(not (storage/(missing+'.lock')).exists(), 'Unknown session does not leave lock file')
        report['demo_http_checks'] = checks
        if args.browser:
            report['browser'] = json.loads(run(['node', str(ROOT/'tests/browser.cjs'), base]))
            report['ai_browser'] = json.loads(run(['node', str(ROOT/'tests/ai-browser.cjs'), base]))
        report['status'] = 'passed'
    finally:
        server.terminate(); server.wait(timeout=10); log.close()
        (runtime/'last-server.log').write_text((temp/'server.log').read_text(encoding='utf8'), encoding='utf8')

if source_sha256(ROOT) != tested_source or hashlib.sha256((ROOT/'scripts/requirements_context.py').read_bytes()).hexdigest() != adapter_sha:
    raise AssertionError('Source changed during tests; rerun against stable inputs.')
report['tested_source_sha256'] = tested_source
report['requirements_context_script_sha256'] = adapter_sha
report['finished_at'] = datetime.now(timezone.utc).isoformat()
(runtime/'test-results.json').write_text(json.dumps(report, indent=2), encoding='utf8')
print(json.dumps(report, indent=2))
