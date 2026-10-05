"""Refresh the frontend manifest before committing a source release."""
from pathlib import Path
import hashlib, json

root = Path(__file__).resolve().parents[1]
frontend = root/'frontend'
files = []
for file in sorted(frontend.rglob('*')):
    if not file.is_file() or file.name in ('manifest.json', 'config.php'): continue
    data = file.read_bytes()
    files.append({'path':file.relative_to(frontend).as_posix(), 'size':len(data), 'sha256':hashlib.sha256(data).hexdigest()})
manifest = {'product':'Hlasuj! by MiloslavHub', 'frontend_version':'0.8.7', 'backend_version':'0.8.7', 'schema_version':'0.8.5', 'status':'release-candidate', 'backend_changed':True, 'database_migration_required':False, 'files':files}
(frontend/'manifest.json').write_text(json.dumps(manifest, ensure_ascii=False, indent=2)+'\n', encoding='utf8')
print(f'Refreshed frontend manifest: {len(files)} files')
