"""Check published documentation targets and generated site hashes without network calls."""
from pathlib import Path
import hashlib,json,re

ROOT=Path(__file__).resolve().parents[1]
checks=0;failures=[]
paths=[ROOT/'README.md',ROOT/'docs/INDEX.md',ROOT/'docs/DOCUMENTATION-COVERAGE.md',ROOT/'docs/HOMEPAGE-AND-ROUTING.md',*sorted((ROOT/'docs/customer').rglob('*.md'))]
for path in paths:
 text=re.sub(r'```.*?```','',path.read_text(encoding='utf8'),flags=re.S)
 for target in re.findall(r'\[[^\]]*\]\(([^)]+)\)',text):
  if re.match(r'^[a-z]+:|^#',target,re.I):continue
  destination=path.parent/target.split('#')[0];checks+=1
  if not destination.exists():failures.append(dict(path=path.relative_to(ROOT).as_posix(),target=target))
manifest=json.loads((ROOT/'frontend/docs/site-manifest.json').read_text(encoding='utf8'))
for item in manifest['sources']:
 checks+=1
 if hashlib.sha256((ROOT/item['path']).read_bytes()).hexdigest()!=item['sha256']:failures.append(dict(stale_source=item['path']))
for item in manifest['files']:
 checks+=1
 if hashlib.sha256((ROOT/'frontend/docs'/item['path']).read_bytes()).hexdigest()!=item['sha256']:failures.append(dict(stale_page=item['path']))
print(json.dumps(dict(status='failed' if failures else 'passed',checks=checks,failures=failures,scope='Local documentation paths and exact source/generated-file hashes')))
if failures:raise SystemExit(1)
