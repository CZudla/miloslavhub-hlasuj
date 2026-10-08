"""Describe an isolated public documentation overlay without changing release.json."""
from pathlib import Path
import argparse,hashlib,json,re

ROOT=Path(__file__).resolve().parents[1]

def main():
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('--landing',required=True,type=Path);p.add_argument('--commit',required=True);p.add_argument('--output',required=True,type=Path)
 a=p.parse_args()
 if not re.fullmatch('[a-f0-9]{40}',a.commit):raise ValueError('Exact published source commit required')
 files=[dict(path='index.php',size=a.landing.stat().st_size,sha256=hashlib.sha256(a.landing.read_bytes()).hexdigest())]
 for file in sorted((ROOT/'frontend/docs').rglob('*')):
  if file.is_file():files.append(dict(path=file.relative_to(ROOT/'frontend').as_posix(),size=file.stat().st_size,sha256=hashlib.sha256(file.read_bytes()).hexdigest()))
 result=dict(product='Hlasuj! by MiloslavHub',website_revision='2026-10-08-docs',source_commit=a.commit,scope='Public documentation directory, two homepage links and exact main-domain /hlasuj alias; not an application feature release',application_release_changed=False,plugin_changed=False,database_changed=False,unreleased_features_deployed=False,files=files)
 a.output.parent.mkdir(parents=True,exist_ok=True);a.output.write_text(json.dumps(result,ensure_ascii=False,indent=2)+'\n',encoding='utf8',newline='\n')
 print(json.dumps(dict(website_files=len(files),source_commit=a.commit)))

if __name__=='__main__':main()
