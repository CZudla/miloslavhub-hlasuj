"""Build a public cs/en documentation directory from an explicit safe link catalogue."""
from pathlib import Path
import hashlib,html,json

ROOT=Path(__file__).resolve().parents[1]
OUT=ROOT/'frontend/docs'
REPO='https://github.com/CZudla/miloslavhub-hlasuj'
SOURCE=REPO+'/blob/feature/requirements-feedback/'
CATALOG=[
 ('teacher','customer/02-MANUAL-UCITELE.md','Manuál učitele','Teacher manual (Czech)'),
 ('student','customer/03-MANUAL-STUDENTA.md','Manuál studenta','Student manual (Czech)'),
 ('reference','customer/04-REFERENCNI-PRIRUCKA.md','Referenční příručka','Reference manual (Czech)'),
 ('admin','customer/05-PRIRUCKA-SPRAVCE.md','Příručka správce','Administrator manual (Czech)'),
 ('help','customer/06-RESENI-PROBLEMU.md','Řešení problémů','Troubleshooting (Czech)'),
 ('privacy','customer/07-SOUKROMI-A-LICENCE.md','Soukromí a licence','Privacy and licences (Czech)'),
 ('cheatsheet','customer/09-TAHAK-PRO-VYUKU.md','Tahák před hodinou','Lesson checklist (Czech)'),
 ('transfer','customer/10-PRENOS-OBSAHU.md','Export a import obsahu','Content export and import'),
 ('en-teacher','customer/en/USER-GUIDE.md','English teacher guide','Teacher guide'),
 ('en-student','customer/en/STUDENT-GUIDE.md','English student guide','Student guide'),
 ('en-admin','customer/en/ADMINISTRATOR-GUIDE.md','English administrator guide','Administrator guide'),
 ('en-reference','customer/en/REFERENCE.md','English reference guide','Reference guide'),
 ('feedback','customer/11-VYSVETLENI-A-POZNAMKY.md','Vysvětlení a poznámky — vývoj','Explanations and notes — development'),
 ('polls','customer/12-ANKETY.md','Neutrální ankety — vývoj','Neutral polls — development'),
 ('organisations','customer/13-ORGANIZACE-A-JAZYKY.md','Organizace a jazyky — vývoj','Organisations and languages — development'),
 ('en-organisations','customer/en/TEACHERS-AND-ORGANISATIONS.md','English organisation guide — vývoj','Teachers and organisations — development'),
 ('architecture','ARCHITECTURE.md','Architektura','Architecture'),
 ('api','API.md','API a integrační hranice','API and integration boundaries'),
 ('format','CONTENT-FORMAT.md','Formát přenosu obsahu','Portable content format'),
 ('access','ORGANIZATIONS-I18N-REFERENCE.md','Reference oprávnění a i18n','Permissions and i18n reference'),
 ('deployment','DEPLOYMENT.md','Nasazení a rollback','Deployment and rollback'),
 ('routing','HOMEPAGE-AND-ROUTING.md','Domény a směrování','Domains and routing'),
 ('security','SECURITY.md','Bezpečnost','Security'),
 ('data','PRIVACY.md','Technické soukromí','Technical privacy reference'),
 ('licence','licenses/NOTICE.md','Licenční oznámení','Licence notices'),
 ('legal','LEGAL-CHECKLIST.md','Otevřené právní body','Open legal items'),
 ('coverage','DOCUMENTATION-COVERAGE.md','Pokrytí dokumentace','Documentation coverage'),
 ('status','DEVELOPMENT-ORGANIZATIONS-I18N-2026-10-08.md','Stav vývoje a skutečné testy','Development status and actual tests'),
 ('marketing','customer/08-MARKETINGOVE-PODKLADY.md','Marketingové podklady','Marketing materials (Czech)'),
 ('en-marketing','marketing/en/PRODUCT-OVERVIEW.md','English product overview','Product overview'),
 ('index','INDEX.md','Úplný index dokumentace','Complete documentation index'),
]

def build():
 for _,path,_,_ in CATALOG:assert (ROOT/'docs'/path).is_file(),path
 links={key:(SOURCE+'docs/'+path,cs,en) for key,path,cs,en in CATALOG}
 def link(key,lang):
  url,cs,en=links[key];return '<a href="'+html.escape(url,quote=True)+'">'+html.escape(en if lang=='en' else cs)+'</a>'
 def group(keys,lang):return '<ul>'+''.join('<li>'+link(k,lang)+'</li>' for k in keys)+'</ul>'
 for lang in ['cs','en']:
  en=lang=='en';base='../' if en else './';title='Guides for teaching. References for running Hlasuj!.' if en else 'Příručky pro výuku. Podklady pro správu.'
  t=lambda cs,eng:eng if en else cs
  role_keys=['en-teacher','en-student','en-admin'] if en else ['teacher','student','admin']
  role_titles=t(['Učím','Odpovídám','Spravuji'],['I teach','I participate','I administer'])
  role_texts=t(['Příprava otázek, spuštění hlasování a společná diskuse.','Připojení přes QR, odpovědi a zobrazení výsledků.','Instalace, aktualizace, zálohy a řešení problémů.'],['Prepare questions, open voting and discuss results.','Join by QR, answer and view the results.','Install, update, back up and troubleshoot.'])
  cards=''.join('<article class="role-card"><span class="step">0'+str(i+1)+'</span><h2>'+role_titles[i]+'</h2><p>'+role_texts[i]+'</p>'+link(key,lang)+'</article>' for i,key in enumerate(role_keys))
  pdf=REPO+'/raw/refs/heads/main/releases/0.8.9/Hlasuj-0.8.9-prirucky.pdf'
  release=REPO+'/tree/main/releases/0.8.9'
  page=f'''<!doctype html>
<html lang="{lang}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>{t('Dokumentace','Documentation')} | Hlasuj! by MiloslavHub</title>
<meta name="description" content="{t('Manuály učitele, studenta a správce, reference, soukromí a dokumentace Hlasuj! by MiloslavHub.','Teacher, student and administrator guides, reference manuals, privacy and Hlasuj! documentation.')}">
<link rel="canonical" href="https://hlasuj.miloslavhub.cz/docs/{'en/' if en else ''}">
<link rel="alternate" hreflang="cs" href="https://hlasuj.miloslavhub.cz/docs/"><link rel="alternate" hreflang="en" href="https://hlasuj.miloslavhub.cz/docs/en/">
<link rel="stylesheet" href="{base}style.css"><link rel="icon" href="/assets/brand/hlasuj-icon-512.png"></head>
<body><a class="skip" href="#content">{t('Přejít na obsah','Skip to content')}</a>
<header class="wrap"><a class="brand" href="/"><img src="/assets/brand/hlasuj-logo.png" alt="Hlasuj! by MiloslavHub" width="210" height="70"></a>
<nav aria-label="{t('Navigace dokumentace','Documentation navigation')}"><a href="/">{t('Domů','Home')}</a><a href="{REPO}">GitHub</a><a href="{base}{'' if en else 'en/'}" lang="{'cs' if en else 'en'}">{'Čeština' if en else 'English'}</a></nav></header>
<main id="content" class="wrap"><section class="intro"><p class="eyebrow">{t('DOKUMENTACE A PODPORA','DOCUMENTATION AND SUPPORT')}</p><h1>{title}</h1>
<p class="lead">{t('Od první otázky ve výuce po bezpečný provoz vlastní instalace. Vyberte svou roli a pokračujte do příručky.','From your first classroom question to running an installation. Choose your role and open the relevant guide.')}</p>
<div class="actions"><a class="button" href="{pdf}">{t('PDF příručky 0.8.9','0.8.9 handbook PDF (Czech)')}</a><a href="{SOURCE}docs/INDEX.md">{t('Všechny dokumenty','All documentation')}</a></div></section>
<section class="roles" aria-label="{t('Vyberte svou roli','Choose your role')}">{cards}</section>
<aside class="version"><strong>{t('Vyberte příručku podle své instalace','Match the guide to your installation')}</strong><p>{t('Kvalifikovaná baseline je 0.8.9 se schématem 0.8.5. Nové organizace, rozšířená angličtina a další vývojové funkce ještě nejsou finálním vydáním. AUTH/licence a závěrečná integrace zůstávají nedokončené.','The qualified baseline is 0.8.9 with schema 0.8.5. Organisation management, the expanded English UI and other development features are unreleased. AUTH/licences and final integration remain unfinished.')}</p><p>{t('Anglická příručka sama nezapíná anglické ovládání ve starší instalaci.','An English guide does not enable English controls in an older installation.')}</p><a href="{release}">{t('Balíčky a doklady vydání 0.8.9','0.8.9 packages and release records')}</a></aside>
<div class="sections"><section><h2>{t('Výuka a běžné použití','Teaching and everyday use')}</h2>{group(['reference','cheatsheet','transfer','help','privacy'] if not en else ['en-reference','transfer','help','privacy'],lang)}</section>
<section><h2>{t('Novinky ve vývoji','Development guides')}</h2>{group(['feedback','polls','organisations','en-organisations','status'],lang)}</section>
<section><h2>{t('Technická reference','Technical reference')}</h2>{group(['architecture','api','format','access','deployment','routing'],lang)}</section>
<section><h2>{t('Bezpečnost, licence a materiály','Security, licences and materials')}</h2>{group(['security','data','licence','legal','coverage','marketing','en-marketing'],lang)}</section></div>
<section class="contact"><h2>{t('Potřebujete poradit?','Need a hand?')}</h2><p>{t('Napište, s jakou instalací a verzí pracujete. Do zprávy nevkládejte hesla ani osobní výsledky studentů.','Tell us which installation and version you use. Keep passwords and personal student results out of your message.')}</p><a href="mailto:hlasuj@miloslavhub.cz">hlasuj@miloslavhub.cz</a></section>
</main><footer class="wrap"><span>Hlasuj! by MiloslavHub · {t('Aktualizace 8. 10. 2026','Updated 8 October 2026')}</span><a href="/privacy">{t('Soukromí','Privacy')}</a><a href="{SOURCE}docs/INDEX.md">{t('Verzované zdroje','Versioned sources')}</a></footer></body></html>
'''
  path=OUT/('en/index.html' if en else 'index.html');path.parent.mkdir(parents=True,exist_ok=True);path.write_text(page,encoding='utf8',newline='\n')
 files=[p for p in sorted(OUT.rglob('*')) if p.is_file() and p.name!='site-manifest.json']
 manifest=dict(product='Hlasuj! by MiloslavHub',documentation_date='2026-10-08',qualified_application_baseline='0.8.9',unreleased_features_deployed=False,sources=[dict(path='docs/'+path,sha256=hashlib.sha256((ROOT/'docs'/path).read_bytes()).hexdigest()) for _,path,_,_ in CATALOG],files=[dict(path=p.relative_to(OUT).as_posix(),sha256=hashlib.sha256(p.read_bytes()).hexdigest(),size=p.stat().st_size) for p in files])
 (OUT/'site-manifest.json').write_text(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n',encoding='utf8',newline='\n')
 print(json.dumps(dict(pages=2,catalogue_links=len(CATALOG),files=len(files))))

if __name__=='__main__':build()
