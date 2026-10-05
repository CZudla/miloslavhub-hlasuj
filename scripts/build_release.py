"""Package only committed files; refuse dirty state and sensitive filename patterns."""
from pathlib import Path
from datetime import datetime, timezone
from zipfile import ZipFile, ZIP_DEFLATED
import argparse, hashlib, json, subprocess
from release_config import VERSION, SCHEMA, source_path

root = Path(__file__).resolve().parents[1]
parser = argparse.ArgumentParser()
parser.add_argument('--output', type=Path, default=root/'artifacts')
args = parser.parse_args()
def git(*params):
    return subprocess.check_output(['git', '-C', str(root), *params])
if git('status','--porcelain').strip(): raise SystemExit('Commit all intended changes before building.')
commit = git('rev-parse','HEAD').decode().strip()
version = VERSION
names = [p.decode('utf8') for p in git('ls-files','-z').split(b'\0') if p and source_path(p.decode('utf8'))]
files = {}
for name in names:
    p=Path(name)
    if p.name in ('config.php','wp-config.php') or p.name.startswith('.env') or p.suffix in ('.log','.dump') or set(p.parts)&{'runtime','artifacts','.git','node_modules'}:
        raise SystemExit('Refusing sensitive/runtime path: '+name)
    files[name] = (root/name).read_bytes()
for item in json.loads(files['frontend/manifest.json'])['files']:
    if hashlib.sha256(files['frontend/'+item['path']]).hexdigest() != item['sha256']:
        raise SystemExit('Stale frontend manifest: '+item['path'])
tests = json.loads((root/'runtime/test-results.json').read_text(encoding='utf8'))
qualification = json.loads((root/'runtime/qualification.json').read_text(encoding='utf8'))
if qualification.get('wordpress_integration',{}).get('status') != 'passed' or not qualification.get('database_restore_verified'):
    raise SystemExit('WordPress integration and verified database restore are required.')
plugin_hash=hashlib.sha256(b''.join(name.encode()+b'\0'+files[name] for name in sorted(files) if name.startswith('wordpress/') and name.endswith('.php'))).hexdigest()
if qualification.get('tested_plugin_sha256') != plugin_hash:
    raise SystemExit('Plugin changed since WordPress integration test.')
if qualification.get('version') != VERSION or qualification.get('integration',{}).get('concurrency',{}).get('status') != 'passed' or qualification.get('integration',{}).get('load',{}).get('status') != 'passed':
    raise SystemExit('Current release qualification including concurrency/load is required.')
if tests.get('status') != 'passed' or tests.get('browser',{}).get('status') != 'passed':
    raise SystemExit('Successful local tests including browser are required.')
if tests.get('tested_source_sha256') != hashlib.sha256(b''.join(name.encode()+b'\0'+files[name] for name in sorted(files) if name.startswith(('frontend/','wordpress/','tests/')) and name!='frontend/manifest.json')).hexdigest():
    raise SystemExit('Source has changed since tests; rerun them.')
files['evidence/test-results.json']=(root/'runtime/test-results.json').read_bytes()
files['evidence/qualification.json']=(root/'runtime/qualification.json').read_bytes()
for image in sorted((root/'runtime/screenshots').glob('*.png')):
    files['evidence/screenshots/'+image.name]=image.read_bytes()
out=args.output.resolve();out.mkdir(parents=True,exist_ok=True)
archive_name=f'hlasuj-{version}-source.zip'
manifest={'product':'Hlasuj! by MiloslavHub','version':version,'frontend_version':version,'backend_version':version,'schema_version':SCHEMA,'docs_version':version,'git_commit':commit,'built_at':datetime.now(timezone.utc).isoformat(),'status':'qualified-patch','production_deployed':'see deployment report','backup_restore_verified':True,'wordpress_database_integration_tested':True,'artifact':archive_name,'files':[{'path':n,'size':len(data),'sha256':hashlib.sha256(data).hexdigest()} for n,data in sorted(files.items())]}
manifest_bytes=(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n').encode('utf8')
archive=out/archive_name
with ZipFile(archive,'w',ZIP_DEFLATED) as z:
    for name,data in sorted(files.items()):z.writestr(name,data)
    z.writestr('release-manifest.json',manifest_bytes)
with ZipFile(archive) as z:
    if z.testzip() is not None:raise SystemExit('ZIP CRC failed')
    if set(z.namelist()) != set(files)|{'release-manifest.json'}:raise SystemExit('ZIP file list mismatch')
    for item in manifest['files']:
        if hashlib.sha256(z.read(item['path'])).hexdigest()!=item['sha256']:raise SystemExit('ZIP hash mismatch')
(out/'release-manifest.json').write_bytes(manifest_bytes)
(out/'test-results.json').write_bytes(files['evidence/test-results.json'])
checksums={archive.name:hashlib.sha256(archive.read_bytes()).hexdigest(),'release-manifest.json':hashlib.sha256(manifest_bytes).hexdigest(),'test-results.json':hashlib.sha256(files['evidence/test-results.json']).hexdigest()}
(out/'SHA256SUMS.txt').write_text(''.join(f'{sha}  {name}\n' for name,sha in checksums.items()),encoding='utf8')
print(json.dumps({'archive':str(archive),'git_commit':commit,'files':len(files),'zip_verified':True,'sha256':checksums[archive.name]},indent=2))
