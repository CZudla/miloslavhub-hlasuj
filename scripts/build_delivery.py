"""Build the two requested distributions from clean sources and verified evidence."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import hashlib, html, io, json, shutil, subprocess

ROOT=Path(__file__).resolve().parents[1]
OUT=ROOT.parent.parent/'outputs/final-0.8.7'
OUT.mkdir(parents=True,exist_ok=True)
def git(*args):return subprocess.check_output(['git','-C',str(ROOT),*args])
if git('status','--porcelain').strip():raise SystemExit('Commit the final delivery sources first')
commit=git('rev-parse','HEAD').decode().strip()
deploy=json.loads((ROOT/'runtime/deployment.json').read_text(encoding='utf8'))
if not deploy['restore_verified'] or not deploy['remote_staging_removed']:raise SystemExit('Incomplete deployment evidence')
tests=json.loads((ROOT/'runtime/test-results.json').read_text(encoding='utf8'))
qualification=json.loads((ROOT/'runtime/qualification.json').read_text(encoding='utf8'))
assert tests['status']=='passed' and tests['browser']['status']=='passed' and qualification['wordpress_integration']['status']=='passed'
tracked=[p.decode('utf8') for p in git('ls-files','-z').split(b'\0') if p]
sources={name:(ROOT/name).read_bytes() for name in tracked}
code_names=[n for n in sorted(sources) if n.startswith(('frontend/','wordpress/','tests/')) and n!='frontend/manifest.json']
assert hashlib.sha256(b''.join(n.encode()+b'\0'+sources[n] for n in code_names)).hexdigest()==tests['tested_source_sha256']
for name in sources:
    p=Path(name)
    assert p.name not in ('config.php','wp-config.php') and not p.name.startswith('.env')
    assert not set(p.parts)&{'runtime','.git','node_modules'}
bundle=ROOT/'runtime/hlasuj-history.bundle'
subprocess.run(['git','-C',str(ROOT),'bundle','create',str(bundle),'--all'],check=True,capture_output=True)
subprocess.run(['git','-C',str(ROOT),'bundle','verify',str(bundle)],check=True,capture_output=True)

common={}
for p in sorted((ROOT/'docs/customer').glob('*.md')):common['dokumentace/zdroje/'+p.name]=p.read_bytes()
for p in sorted((ROOT/'runtime/handbooks').iterdir()):
    if p.is_file():common['dokumentace/'+p.name]=p.read_bytes()
for p in sorted((ROOT/'docs/licenses').iterdir()):common['licence/'+p.name]=p.read_bytes()
for p in sorted((ROOT/'frontend/assets/vendor').glob('*')):
    if p.is_file() and p.suffix!='.js':common['licence/QRCode.js/'+p.name]=p.read_bytes()
for p in sorted((ROOT/'docs/marketing').glob('*')):common['marketing/'+p.name]=p.read_bytes()
for p in sorted((ROOT/'runtime/marketing').glob('*.png')):common['marketing/'+p.name]=p.read_bytes()
common['marketing/TEXTY.md']=(ROOT/'docs/customer/08-MARKETINGOVE-PODKLADY.md').read_bytes()
common['marketing/produktovy-list.pdf']=(ROOT/'runtime/handbooks/Hlasuj-0.8.7-produktovy-list.pdf').read_bytes()
common['marketing/SCREENSHOTY.txt']='Aktuální snímky 0.8.7 jsou z izolovaného testovacího prostředí se syntetickými daty. Nejde o živé zákaznické výsledky. Historická galerie uvnitř aplikace je označena samostatně.\n'.encode('utf8')
for p in sorted((ROOT/'runtime/screenshots').glob('*.png')):common['marketing/screenshots/'+p.name]=p.read_bytes()
summary=f'''# Hlasuj! by MiloslavHub 0.8.7

Nasazený aplikační commit: {deploy['application_commit']}.
Commit předání zdrojů a dokumentace: {commit}.
Schéma: 0.8.5, bez migrace pro přechod z 0.8.5.
Ověření: syntaxe, 51 kontraktových, 18 HTTP, 11 prohlížečových a 19 skutečných WordPress/DB integračních kontrol.
Nasazeno 2. 10. 2026 na hlasuj.miloslavhub.cz. Stav původního nasazení nedokládá stav nové zákaznické instalace.
Domácí úkoly, kompletní i18n, obsahový import/export a oddělené organizace zůstávají dalšími etapami.
Licenční a obchodní podmínky vlastního frontendu a assets musí autor dokončit; plugin deklaruje GPL-2.0-or-later, QRCode.js MIT.
'''
common['VYDANI.md']=summary.encode('utf8')
author={**common,**{'zdroje/'+n:d for n,d in sources.items()}}
author['hlasuj-history.bundle']=bundle.read_bytes()
for p in ['test-results.json','qualification.json','deployment.json','delivery-secret-scan.json','cleanup.json']:
    author['overeni/'+p]=(ROOT/'runtime'/p).read_bytes()
author['ZPRAVA-O-NASAZENI.md']=(ROOT/'docs/DEPLOYMENT-2026-10-02.md').read_bytes()
author['PREDANI-AUTOROVI.md']=(ROOT/'docs/AUTHOR-HANDOVER.md').read_bytes()
author['STAV-UKLIDU.md']=(ROOT/'docs/CLEANUP-STATUS.md').read_bytes()
customer=dict(common)
for n,d in sources.items():
    if n.startswith('frontend/'):customer['instalace/'+n]=d
# Plugin installer retains exactly the deployed plugin tree, without instance config.
plugin=io.BytesIO()
with ZipFile(plugin,'w',ZIP_DEFLATED) as z:
    for n,d in sources.items():
        if n.startswith('wordpress/'):z.writestr(n.removeprefix('wordpress/'),d)
customer['instalace/miloslavhub-live-0.8.7.zip']=plugin.getvalue()
customer['instalace/frontend/release.json']=(json.dumps({'product':deploy['product'],'version':'0.8.7','schema_version':'0.8.5','git_commit':deploy['application_commit'],'database_migration_required':False},indent=2)+'\n').encode()
customer['instalace/CTETE-PRED-INSTALACI.txt']='Začněte dokumentem dokumentace/PRIRUCKY.html, kapitola Příručka správce. Frontend a plugin instalujte koordinovaně. Vlastní config.php není dodán. V config.example.php nahraďte adresy projektu adresami své instalace. Novou DB vytvořte ze schema SQL; existující schéma 0.8.5 nemigrujte. Historické SQL migrace neimportujte všechny.\n'.encode('utf8')

def finish(kind,files):
    title='Autorský balíček' if kind=='AUTORSKY' else 'Zákaznický balíček'
    intro='Zdroje, Git historie, audit, testy, architektura, plán a provozní doklady.' if kind=='AUTORSKY' else 'Instalační soubory, manuály a materiály pro vlastní provoz.'
    links='<li><a href="dokumentace/PRIRUCKY.html">Otevřít příručky v prohlížeči</a></li><li><a href="dokumentace/Hlasuj-0.8.7-prirucky.pdf">Příručky PDF (16 stran)</a></li><li><a href="marketing/produktovy-list.pdf">Produktový list PDF</a></li><li><a href="marketing/TEXTY.md">Marketingové texty</a></li><li><a href="VYDANI.md">Verze a omezení</a></li><li><a href="licence/NOTICE.md">Licence</a></li>'
    if kind=='AUTORSKY':links+='<li><a href="PREDANI-AUTOROVI.md">Předání a další kroky</a></li><li><a href="ZPRAVA-O-NASAZENI.md">Zpráva o nasazení</a></li><li><a href="zdroje/docs/AUDIT.md">Audit A–M</a></li>'
    else:links+='<li><a href="instalace/CTETE-PRED-INSTALACI.txt">Před instalací</a></li><li><a href="instalace/miloslavhub-live-0.8.7.zip">WordPress plugin ZIP</a></li>'
    files['ZACNETE-ZDE.html']=('<!doctype html><html lang="cs"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'+title+' - Hlasuj!</title><style>body{font:18px/1.6 system-ui;color:#16334d;max-width:850px;margin:60px auto;padding:24px;background:#f5f8fc}h1{line-height:1.2}a{color:#1762a5}li{margin:10px 0}</style><h1>Hlasuj! by MiloslavHub</h1><h2>'+title+' · 0.8.7</h2><p>'+intro+'</p><ul>'+links+'</ul><p>ZIP nejprve rozbalte. Produkční konfigurace, hesla a databáze jsou záměrně mimo distribuci. Plánované funkce a licenční otevřené body jsou výslovně uvedeny v příručkách.</p></html>').encode('utf8')
    files['ZACNETE-ZDE.txt']=(title+' Hlasuj! 0.8.7\nRozbalte ZIP a otevřete ZACNETE-ZDE.html.\n'+intro+'\n'+summary).encode('utf8')
    manifest={'product':deploy['product'],'version':'0.8.7','package':kind,'application_commit':deploy['application_commit'],'delivery_commit':commit,'schema_version':'0.8.5','production_secrets_included':False,'files':[{'path':n,'size':len(d),'sha256':hashlib.sha256(d).hexdigest()} for n,d in sorted(files.items())]}
    files['manifest.json']=(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n').encode('utf8')
    files['SHA256SUMS.txt']=''.join(hashlib.sha256(d).hexdigest()+'  '+n+'\n' for n,d in sorted(files.items())).encode('utf8')
    out=OUT/f'Hlasuj-0.8.7-{kind}.zip'
    with ZipFile(out,'w',ZIP_DEFLATED) as z:
        for n,d in sorted(files.items()):z.writestr(n,d)
    with ZipFile(out) as z:
        assert z.testzip() is None and set(z.namelist())==set(files)
        for entry in manifest['files']:assert hashlib.sha256(z.read(entry['path'])).hexdigest()==entry['sha256']
        if kind=='ZAKAZNICKY':
            with ZipFile(io.BytesIO(z.read('instalace/miloslavhub-live-0.8.7.zip'))) as inner:assert inner.testzip() is None
    print(json.dumps({'package':kind,'file':out.name,'files':len(files),'bytes':out.stat().st_size,'sha256':hashlib.sha256(out.read_bytes()).hexdigest(),'verified':True}))
    return out
archives=[finish('AUTORSKY',author),finish('ZAKAZNICKY',customer)]
(OUT/'SHA256SUMS.txt').write_text(''.join(hashlib.sha256(p.read_bytes()).hexdigest()+'  '+p.name+'\n' for p in archives),encoding='utf8')
# Keep convenient PDF previews alongside the two archives.
for p in (ROOT/'runtime/handbooks').glob('*.pdf'):shutil.copy2(p,OUT/p.name)
