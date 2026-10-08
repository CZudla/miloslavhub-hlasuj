"""Prepare isolated website copies. Never overwrite the supplied source file."""
from pathlib import Path
import argparse,hashlib,json,re

ROOT=Path(__file__).resolve().parents[1]

def patch_htaccess(original:bytes)->bytes:
 snippet=(ROOT/'deploy/apache/hlasuj-alias.conf').read_bytes()
 snippet=snippet[snippet.index(b'# BEGIN Hlasuj homepage alias'):].replace(b'\r\n',b'\n').rstrip(b'\n')+b'\n'
 newline=b'\r\n' if b'\r\n' in original else b'\n';snippet=snippet.replace(b'\n',newline)
 if b'# BEGIN Hlasuj homepage alias' in original:
  if original.count(snippet)!=1:raise ValueError('Existing alias differs; review before changing it')
  return original
 match=re.search(rb'(?mi)^RewriteEngine[ \t]+On[ \t]*\r?\n',original)
 if not match:raise ValueError('No standalone RewriteEngine On line')
 inserted=newline+snippet+newline
 result=original[:match.end()]+inserted+original[match.end():]
 assert result.replace(inserted,b'',1)==original
 return result

def patch_landing(original:bytes)->bytes:
 if b'href="/docs/"' in original:raise ValueError('Documentation link already exists; review current page')
 newline=b'\r\n' if b'\r\n' in original else b'\n'
 anchor=re.search(rb'(?m)^([ \t]*)<a href="#kontakt">',original)
 if not anchor:raise ValueError('Expected contact navigation anchor not found')
 nav=anchor[1]+'<a href="/docs/">Dokumentace</a>'.encode()+newline
 result=original[:anchor.start()]+nav+original[anchor.start():]
 footer=b'<span class="footer-links">'
 if result.count(footer)!=1:raise ValueError('Expected unique footer not found')
 extra='<a href="/docs/">Dokumentace</a> · '.encode()
 result=result.replace(footer,footer+extra,1)
 assert result.replace(nav,b'',1).replace(footer+extra,footer,1)==original
 return result

def main():
 p=argparse.ArgumentParser(description=__doc__);p.add_argument('--kind',required=True,choices=['htaccess','landing']);p.add_argument('--input',required=True,type=Path);p.add_argument('--output',required=True,type=Path);p.add_argument('--expected-sha256',required=True)
 a=p.parse_args()
 if a.input.resolve()==a.output.resolve():raise ValueError('Use a separate output copy')
 original=a.input.read_bytes()
 if hashlib.sha256(original).hexdigest()!=a.expected_sha256:raise ValueError('Input changed; preserve and review current server files')
 result=(patch_htaccess if a.kind=='htaccess' else patch_landing)(original)
 a.output.parent.mkdir(parents=True,exist_ok=True);a.output.write_bytes(result)
 print(json.dumps(dict(kind=a.kind,original_sha256=a.expected_sha256,result_sha256=hashlib.sha256(result).hexdigest(),added_bytes=len(result)-len(original),application_logic_changed=False)))

if __name__=='__main__':main()
