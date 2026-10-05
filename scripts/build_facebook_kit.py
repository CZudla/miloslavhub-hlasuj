"""Build a reviewable Facebook launch kit, without accessing or publishing an account."""
from pathlib import Path
from html import escape
from html.parser import HTMLParser
from urllib.parse import unquote
from zipfile import ZipFile, ZIP_DEFLATED
import base64, hashlib, json, os, shutil, subprocess, urllib.request

ROOT=Path(__file__).resolve().parents[1]
SOURCE=ROOT/'docs/marketing/facebook'
OUT=ROOT.parent.parent/'outputs/facebook-2026-10-05'
OUT.mkdir(parents=True,exist_ok=True)
DATA=json.loads((SOURCE/'STRANKA.json').read_text(encoding='utf8'))
assert DATA['status']=='prepared_not_published' and len(DATA['bio'])<=100

class Links(HTMLParser):
    def __init__(self):super().__init__();self.emails=set()
    def handle_starttag(self,tag,attrs):
        if tag!='a':return
        href=dict(attrs).get('href','')
        if href.startswith('mailto:'):self.emails.add(unquote(href[7:].split('?')[0]))

checks={}
for name,url in [('website',DATA['website']),('demo',DATA['demo']),('privacy',DATA['website']+'privacy')]:
    with urllib.request.urlopen(url,timeout=30) as response:
        assert response.status==200;checks[name]=response.status
        if name=='website':
            parser=Links();parser.feed(response.read().decode('utf8'))
            assert DATA['contact_email'] in parser.emails,'Contact must match the public product website'
checks['contact_email_matches_public_website']=True
logo=ROOT/'frontend/assets/brand/hlasuj-logo.png'
icon=ROOT/'frontend/assets/brand/hlasuj-icon-512.png'
shutil.copy2(icon,OUT/'profil.png')
shutil.copy2(logo,OUT/'logo-produktu.png')
image_uri='data:image/png;base64,'+base64.b64encode(logo.read_bytes()).decode('ascii')

def frame(width,height,title,body):
    return f'<svg xmlns="http://www.w3.org/2000/svg" width="{width}" height="{height}" viewBox="0 0 {width} {height}" role="img" aria-label="{escape(title,quote=True)}"><title>{escape(title)}</title><g font-family="Arial, sans-serif">{body}</g></svg>'

def text(x,y,value,size=28,fill='#102e50',weight=400,anchor='start'):
    return f'<text x="{x}" y="{y}" font-size="{size}" font-weight="{weight}" fill="{fill}" text-anchor="{anchor}">{escape(value)}</text>'

cover='<rect width="1640" height="720" fill="white"/><rect width="1640" height="18" fill="#087cf4"/>'
cover+='<circle cx="90" cy="560" r="180" fill="#f0f7ff"/><circle cx="1530" cy="130" r="220" fill="#f0f7ff"/>'
cover+=f'<image href="{image_uri}" x="490" y="110" width="660" height="230"/>'
cover+=text(820,430,'Zapojte studenty do výuky.',48,weight=700,anchor='middle')
cover+=text(820,490,'Ankety a kvízy · Připojení přes QR · Start řídí učitel',28,anchor='middle')
cover+=text(820,553,'hlasuj.miloslavhub.cz',30,'#087cf4',700,'middle')
(OUT/'uvodni-obrazek.svg').write_text(frame(1640,720,'Hlasuj! by MiloslavHub — ankety a kvízy pro výuku',cover),encoding='utf8')

def post_graphic(kind):
    body='<rect width="1200" height="630" fill="#102e50"/><circle cx="1170" cy="15" r="300" fill="#164779"/>'
    body+='<rect x="64" y="42" width="340" height="118" rx="14" fill="white"/>'
    body+=f'<image href="{image_uri}" x="80" y="49" width="309" height="107"/>'
    body+='<rect x="850" y="203" width="270" height="246" rx="24" fill="#f4f9ff"/>'
    if kind=='welcome':
        body+=text(64,270,'Zapojte studenty.',58,'white',700)
        body+=text(64,337,'Otázka. QR kód. Diskuse.',38,'#8fdace',700)
        body+=text(64,385,'Hlasování spustíte, až budete připraveni.',26,'#d3e3f4')
        for i,(w,color) in enumerate([(190,'#087cf4'),(136,'#1e5e99'),(85,'#8fdace')]):
            body+=f'<rect x="877" y="{245+i*57}" width="{w}" height="32" rx="7" fill="{color}"/>'
    elif kind=='poll':
        body+=text(64,257,'Které téma si dnes',48,'white',700)
        body+=text(64,316,'zopakujeme?',48,'#8fdace',700)
        body+=text(64,374,'Jedna anketa jako začátek společné diskuse.',26,'#d3e3f4')
        for i,value in enumerate(['A  Základní pojmy','B  Praktické příklady','C  Souvislosti']):
            body+=f'<rect x="869" y="{237+i*59}" width="230" height="42" rx="8" fill="#e4effb"/>'
            body+=text(882,265+i*59,value,19,weight=700)
    else:
        body+=text(64,257,'Sdílejte připravené',47,'white',700)
        body+=text(64,316,'otázky s kolegou.',47,'#8fdace',700)
        body+=text(64,374,'Export předmětu · Náhled · Nové koncepty',26,'#d3e3f4')
        for i,value in enumerate(['1  Export obsahu','2  Kontrola náhledu','3  Nové koncepty']):
            body+=text(877,269+i*60,value,23,weight=700)
    body+='<rect x="64" y="436" width="477" height="63" rx="14" fill="white"/>'
    body+=text(91,478,'Vyzkoušejte veřejné demo',29,weight=700)
    body+=text(64,555,'hlasuj.miloslavhub.cz/demo/',30,'white',700)
    body+=text(64,595,'Navrženo učitelem pro učitele.',21,'#bcd8f6')
    return frame(1200,630,'Hlasuj! — '+kind,body)

for filename,kind in [('prispevek-01','welcome'),('prispevek-03','poll'),('prispevek-04','transfer')]:
    (OUT/(filename+'.svg')).write_text(post_graphic(kind),encoding='utf8')

runtime=ROOT/'runtime';runtime.mkdir(exist_ok=True)
renderer=runtime/'render-facebook-kit.cjs'
renderer.write_text("""const {chromium}=require('playwright');
const fs=require('node:fs'),path=require('node:path');const{pathToFileURL}=require('node:url');
(async()=>{const out=process.argv[2],browser=await chromium.launch({channel:'msedge',headless:true});
try{for(const name of ['uvodni-obrazek','prispevek-01','prispevek-03','prispevek-04']){
const size=name==='uvodni-obrazek'?{width:1640,height:720}:{width:1200,height:630};
const page=await browser.newPage({viewport:size,deviceScaleFactor:1});
await page.route('**/*',r=>r.request().url().startsWith('file:')||r.request().url().startsWith('data:')?r.continue():r.abort());
await page.goto(pathToFileURL(path.join(out,name+'.svg')).href);await page.evaluate(()=>document.fonts.ready);
const errors=await page.evaluate(()=>[...document.querySelectorAll('text')].filter(el=>{const b=el.getBBox(),v=document.documentElement.viewBox.baseVal;return b.x<0||b.y<0||b.x+b.width>v.width||b.y+b.height>v.height;}).map(el=>el.textContent));
if(errors.length)throw Error('Text outside image: '+JSON.stringify(errors));
await page.screenshot({path:path.join(out,name+'.png')});await page.close();}
}finally{await browser.close();}console.log('Four graphics rendered; no text clipping.');})().catch(e=>{console.error(e);process.exit(1)});
""",encoding='utf8')
subprocess.run(['node',str(renderer),str(OUT)],check=True,env=os.environ)

fields='\n\n'.join(label+'\n'+DATA[key] for label,key in [('NÁZEV','name'),('NAVRŽENÉ UŽIVATELSKÉ JMÉNO — NEOVĚŘENO','proposed_username'),('NAVRŽENÁ KATEGORIE — OVĚŘIT NABÍDKU','proposed_category'),('BIO','bio'),('DELŠÍ POPIS','about'),('WEB','website'),('KONTAKT','contact_email'),('TLAČÍTKO — NÁVRH','proposed_action_button'),('ODKAZ TLAČÍTKA','action_url')])
(OUT/'NASTAVENI-STRANKY.txt').write_text(fields+'\n',encoding='utf8')
for post in DATA['launch_posts']:
    assert post['text'] and len(post['text'])<2200
    (OUT/(post['id']+'.txt')).write_text(post['text']+'\n',encoding='utf8')
shutil.copy2(SOURCE/'STRANKA.json',OUT/'STRANKA.json')
shutil.copy2(SOURCE/'README.md',OUT/'STAV-A-DOKONCENI.md')

def copy_block(label,value):
    return f'<div class="copy"><h3>{escape(label)}</h3><textarea readonly>{escape(value)}</textarea><button type="button">Zkopírovat text</button></div>'

blocks=copy_block('Název',DATA['name'])+copy_block('Bio',DATA['bio'])+copy_block('O stránce',DATA['about'])+copy_block('Web',DATA['website'])+copy_block('Kontakt',DATA['contact_email'])
posts=''
for post in DATA['launch_posts']:
    picture=f'<img class="post-image" src="{escape(post["image"])}" alt="Grafika příspěvku">' if post['image'] else ''
    posts+=f'<article><h2>{post["publish_order"]}. {escape(post["title"])}</h2>{picture}{copy_block("Text příspěvku",post["text"])}</article>'
style='body{margin:0;background:#f3f7fc;color:#102e50;font:17px/1.55 system-ui,sans-serif}main{max-width:1000px;margin:auto;padding:28px}header{padding:26px;background:#102e50;color:white;border-radius:18px}h1,h2{line-height:1.2}section,article{background:white;border-radius:18px;padding:26px;margin:24px 0}.cover,.post-image{width:100%;height:auto;border-radius:12px}.profile{width:110px;border-radius:50%;background:white;border:3px solid #087cf4}textarea{box-sizing:border-box;width:100%;min-height:150px;border:1px solid #c9d8e9;border-radius:10px;padding:15px;font:16px/1.55 system-ui;resize:vertical}button{border:0;border-radius:9px;background:#087cf4;color:white;padding:12px 18px;margin:8px 0 20px;font:16px system-ui;cursor:pointer}a{color:#1762a5}header a{color:#c6e2ff}.status{background:#fff1d6;color:#60450c;border-radius:12px;padding:15px}.copy h3{margin-bottom:10px}@media(max-width:600px){main{padding:12px}section,article,header{padding:18px}}'
script="document.querySelectorAll('.copy button').forEach(button=>button.addEventListener('click',async()=>{const area=button.previousElementSibling;try{await navigator.clipboard.writeText(area.value);button.textContent='Zkopírováno';}catch(e){area.focus();area.select();button.textContent='Text vybrán — stiskněte Ctrl+C';}}));"
preview=f'<!doctype html><html lang="cs"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Hlasuj! — obsah facebookové stránky</title><style>{style}</style><main><header><h1>Hlasuj! by MiloslavHub</h1><p>Facebook: připravené nastavení a šest příspěvků</p></header><p class="status"><strong>Připraveno k zveřejnění.</strong> Facebook stránka nebyla vytvořena a žádný příspěvek nebyl publikován. Přístup k ovládání prohlížeče vyžaduje obnovu.</p><section><h2>Vzhled stránky</h2><img class="cover" src="uvodni-obrazek.png" alt="Úvodní obrázek Hlasuj!"><p><img class="profile" src="profil.png" alt="Profilová ikona Hlasuj!"></p><p>Návrh uživatelského jména: <strong>{escape(DATA["proposed_username"])}</strong>. Dostupnost neověřena. Kategorie: {escape(DATA["proposed_category"])}; ověřit aktuální nabídku Facebooku.</p></section><section><h2>Nastavení stránky</h2>{blocks}</section>{posts}<section><h2>Dokončení</h2><p>Po obnovení ovládání prohlížeče lze stránku vytvořit, doplnit grafiku a zveřejnit úvodní příspěvky. Další příspěvky jsou připravené do zásoby. Zpráva: STAV-A-DOKONCENI.md.</p><p><a href="{escape(DATA["demo"])}">Otevřít veřejné demo</a></p></section></main><script>{script}</script></html>'
(OUT/'ZACNETE-ZDE.html').write_text(preview,encoding='utf8')
(OUT/'ZACNETE-ZDE.txt').write_text('Hlasuj! Facebook — připravený obsah, dosud nezveřejněný.\nRozbalte ZIP a otevřete ZACNETE-ZDE.html. Tlačítka umožňují kopírovat texty.\nGrafika: profil 512×512, cover 1640×720, příspěvky 1200×630. Ořez ověřte v aktuálním Facebooku.\nPodrobný stav a dokončení: STAV-A-DOKONCENI.md.\n',encoding='utf8')
entries=[p for p in sorted(OUT.iterdir()) if p.is_file() and p.name not in ['manifest.json','SHA256SUMS.txt'] and p.suffix!='.zip']
manifest={'product':DATA['name'],'prepared_on':DATA['prepared_on'],'facebook_page_created':False,'posts_published':0,'status':'prepared_not_published','checks':checks,'profile_copied_unchanged':hashlib.sha256(icon.read_bytes()).hexdigest()==hashlib.sha256((OUT/'profil.png').read_bytes()).hexdigest(),'files':[{'path':p.name,'bytes':p.stat().st_size,'sha256':hashlib.sha256(p.read_bytes()).hexdigest()} for p in entries]}
(OUT/'manifest.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2),encoding='utf8')
archive=OUT/'Hlasuj-Facebook-start-2026-10-05.zip'
with ZipFile(archive,'w',ZIP_DEFLATED) as z:
    for p in entries+[OUT/'manifest.json']:z.write(p,p.name)
with ZipFile(archive) as z:
    assert z.testzip() is None
    for entry in manifest['files']:assert hashlib.sha256(z.read(entry['path'])).hexdigest()==entry['sha256']
(OUT/'SHA256SUMS.txt').write_text(hashlib.sha256(archive.read_bytes()).hexdigest()+'  '+archive.name+'\n',encoding='utf8')
print(json.dumps({'status':'prepared_not_published','files':len(entries)+1,'posts':len(DATA['launch_posts']),'archive':str(archive),'bytes':archive.stat().st_size,'sha256':hashlib.sha256(archive.read_bytes()).hexdigest(),'checks':checks},ensure_ascii=True))
