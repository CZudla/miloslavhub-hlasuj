"""Build the Czech handbook and a one-page product sheet from release documentation."""
from pathlib import Path
import html, re, shutil
from reportlab.platypus import SimpleDocTemplate, Paragraph, Spacer, PageBreak, Table, TableStyle, Image, KeepTogether
from reportlab.lib import colors
from reportlab.lib.styles import getSampleStyleSheet, ParagraphStyle
from reportlab.lib.enums import TA_LEFT
from reportlab.lib.pagesizes import A4
from reportlab.pdfbase import pdfmetrics
from reportlab.pdfbase.ttfonts import TTFont
from pypdf import PdfReader

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / 'runtime/handbooks'
OUT.mkdir(parents=True, exist_ok=True)
for name, file in [('Arial', 'arial.ttf'), ('Arial-Bold', 'arialbd.ttf'), ('Arial-Italic', 'ariali.ttf')]:
    pdfmetrics.registerFont(TTFont(name, 'C:/Windows/Fonts/' + file))
pdfmetrics.registerFontFamily('Arial', normal='Arial', bold='Arial-Bold', italic='Arial-Italic', boldItalic='Arial-Bold')
styles = getSampleStyleSheet()
for name in ['Normal', 'BodyText', 'Heading1', 'Heading2', 'Heading3', 'Title']:
    styles[name].fontName = 'Arial-Bold' if name.startswith('Heading') or name == 'Title' else 'Arial'
    styles[name].textColor = colors.HexColor('#16334d')
styles['BodyText'].fontSize = 9.4; styles['BodyText'].leading = 14; styles['BodyText'].spaceAfter = 8
styles['Heading1'].fontSize = 22; styles['Heading1'].leading = 27; styles['Heading1'].spaceAfter = 16
styles['Heading2'].fontSize = 13; styles['Heading2'].leading = 18; styles['Heading2'].spaceBefore = 14
styles['Heading3'].fontSize = 11; styles['Heading3'].leading = 15
styles.add(ParagraphStyle(name='Cell', parent=styles['BodyText'], fontSize=8, leading=11, spaceAfter=0))
styles.add(ParagraphStyle(name='Small', parent=styles['BodyText'], fontSize=8, leading=11))
styles.add(ParagraphStyle(name='Cover', parent=styles['Heading1'], fontSize=34, leading=41, spaceAfter=24))

def inline(s):
    s=html.escape(s.replace('—','-').replace('–','-').replace('\u2011','-'))
    s=re.sub(r'\*\*(.*?)\*\*', r'<b>\1</b>', s)
    return re.sub(r'`([^`]+)`', r'<font color="#1762a5">\1</font>', s)

def blocks(text):
    lines=text.splitlines(); i=0
    while i<len(lines):
        l=lines[i].strip()
        if not l: i+=1; continue
        if l.startswith('|'):
            rows=[]
            while i<len(lines) and lines[i].strip().startswith('|'):
                r=[c.strip() for c in lines[i].strip().strip('|').split('|')]
                if not all(re.fullmatch(r'[:\- ]+',c or '-') for c in r): rows.append(r)
                i+=1
            yield 'table',rows; continue
        m=re.match(r'^(#{1,3}) (.+)$',l)
        if m: yield 'h'+str(len(m[1])),m[2];i+=1;continue
        if re.match(r'^(?:- |\d+\. )',l):yield 'li',l;i+=1;continue
        p=[l];i+=1
        while i<len(lines) and lines[i].strip() and not re.match(r'^(?:#|\||- |\d+\. )',lines[i].strip()):p.append(lines[i].strip());i+=1
        yield 'p',' '.join(p)

def flow(text):
    story=[]
    for kind,value in blocks(text):
        if kind=='table':
            rows=[[Paragraph(inline(c),styles['Cell']) for c in row] for row in value]
            n=len(rows[0]);widths=([165,342] if n==2 else [145,175,187])
            table=Table(rows,colWidths=widths,repeatRows=1,hAlign='LEFT')
            table.setStyle(TableStyle([('BACKGROUND',(0,0),(-1,0),colors.HexColor('#e7f0fa')),('VALIGN',(0,0),(-1,-1),'TOP'),('LINEBELOW',(0,0),(-1,0),.8,colors.HexColor('#1762a5')),('LINEBELOW',(0,1),(-1,-1),.3,colors.HexColor('#d2dfe9')),('LEFTPADDING',(0,0),(-1,-1),7),('RIGHTPADDING',(0,0),(-1,-1),7),('TOPPADDING',(0,0),(-1,-1),7),('BOTTOMPADDING',(0,0),(-1,-1),7)]))
            story.extend([table,Spacer(1,10)])
        else:
            style={'h1':'Heading1','h2':'Heading2','h3':'Heading3'}.get(kind,'BodyText')
            story.append(Paragraph(inline(value),styles[style]))
    return story

def footer(canvas,doc):
    canvas.saveState();canvas.setStrokeColor(colors.HexColor('#d2dfe9'));canvas.line(44,35,551,35)
    canvas.setFont('Arial',8);canvas.setFillColor(colors.HexColor('#526a80'))
    canvas.drawString(44,23,'Hlasuj! by MiloslavHub | 0.8.7 | 2. 10. 2026')
    canvas.drawRightString(551,23,str(doc.page));canvas.restoreState()

def document(path,story):
    SimpleDocTemplate(str(path),pagesize=A4,leftMargin=44,rightMargin=44,topMargin=42,bottomMargin=49,title='Hlasuj! by MiloslavHub 0.8.7',author='MiloslavHub',pageCompression=1).build(story,onFirstPage=footer,onLaterPages=footer)

texts=[(p,p.read_text(encoding='utf8')) for p in sorted((ROOT/'docs/customer').glob('*.md'))]
story=[Spacer(1,80),Paragraph('Hlasuj!<br/>by MiloslavHub',styles['Cover']),Paragraph('Manuály a referenční příručka',styles['Heading1']),Paragraph('Učitel · student · správce',styles['Heading2']),Spacer(1,24),Paragraph('Vydání 0.8.7<br/>2. října 2026',styles['BodyText']),Spacer(1,20),Paragraph('Praktické postupy, význam nastavení, instalace, obnova, řešení problémů a podklady k představení aplikace.',styles['BodyText']),Spacer(1,20),Paragraph('Tato příručka popisuje skutečné funkce vydání 0.8.7. Plánované domácí úkoly, plné i18n a oddělené účty organizací jsou další etapy vývoje.',styles['Small'])]
for p,t in texts:story.extend([PageBreak(),*flow(t)])
story.extend([PageBreak(),Paragraph('Obrazové ukázky 0.8.7',styles['Heading1']),Paragraph('Testovací prostředí, syntetická data. Snímky ilustrují studentský pohled a úvod samostatného dema.',styles['BodyText'])])
for name in ['student-live.png','demo-intro.png']:
    source=ROOT/'runtime/screenshots'/name
    if source.exists():
        from PIL import Image as PILImage
        with PILImage.open(source) as im:w,h=im.size
        scale=min(490/w,290/h)
        story.extend([Paragraph(name,styles['Small']),Image(str(source),width=w*scale,height=h*scale),Spacer(1,15)])
document(OUT/'Hlasuj-0.8.7-prirucky.pdf',story)

product='''# Hlasuj! by MiloslavHub
## Zapojte publikum. Bez zdržování.
Webové ankety a kvízy pro společnou výuku. Studenti otevřou QR kód v prohlížeči. Učitel určí, kdy hlasování začne.
## Od otázky ke společné diskusi
1. Připravte otázku a ukažte QR.
2. Nechte studenty připojit a spusťte hlasování.
3. Proberte odpovědi na projekci.
## Co nabízí vydání 0.8.7
- Živé ankety a kvízy s učitelským spuštěním.
- Trvalé odkazy otázek a připojení bez studentského účtu.
- Projekci výsledků a volitelný soutěžní režim.
- Dlouhodobé ankety a CSV export živých výsledků.
## Vyzkoušejte ukázku
hlasuj.miloslavhub.cz/demo/
Navrženo učitelem pro učitele. Samostatný projekt MiloslavHub.
## Pro vlastní provoz
Frontend + WordPress plugin + samostatná hlasovací databáze. Instalační podmínky, soukromí a licence jsou popsány v dodané příručce. Domácí úkoly, plné cs/en rozhraní a oddělené organizace jsou plánované funkce.
'''
document(OUT/'Hlasuj-0.8.7-produktovy-list.pdf',flow(product))
(OUT/'produktovy-list.md').write_text(product,encoding='utf8')

html_parts=[];nav=[]
for p,t in texts:
    anchor=p.stem;title=t.splitlines()[0].removeprefix('# ');nav.append(f'<a href="#{anchor}">{html.escape(title)}</a>')
    html_parts.append(f'<section id="{anchor}">')
    for k,v in blocks(t):
        if k=='table':html_parts.append('<div class="table"><table>'+''.join('<tr>'+''.join('<'+('th' if i==0 else 'td')+'>'+inline(c)+'</'+('th' if i==0 else 'td')+'>' for c in r)+'</tr>' for i,r in enumerate(v))+'</table></div>')
        elif k=='li':html_parts.append('<p class="list">'+inline(v)+'</p>')
        else:tag=k if k.startswith('h') else 'p';html_parts.append(f'<{tag}>{inline(v)}</{tag}>')
    html_parts.append('</section>')
style='body{font:17px/1.6 system-ui,sans-serif;color:#16334d;background:#f3f7fb;margin:0}header,main{max-width:1000px;margin:auto;padding:30px}header{background:#123d69;color:white}nav{display:flex;gap:12px;flex-wrap:wrap}nav a{color:white}section{background:white;padding:32px;margin:24px 0;border-radius:12px}h1,h2{line-height:1.25}h2{margin-top:28px}table{width:100%;border-collapse:collapse;font-size:15px}th,td{text-align:left;vertical-align:top;border-bottom:1px solid #cddaea;padding:10px;overflow-wrap:anywhere}th{background:#e7f0fa}.table{overflow-x:auto}.list{margin:8px 0}a{color:#1762a5}@media(max-width:600px){main,header{padding:15px}section{padding:20px}}@media print{body{background:white}nav{display:none}section{break-before:page}}'
(OUT/'PRIRUCKY.html').write_text('<!doctype html><html lang="cs"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Hlasuj! 0.8.7 - příručky</title><style>'+style+'</style><header><h1>Hlasuj! by MiloslavHub</h1><p>Dokumentace vydání 0.8.7</p><nav>'+''.join(nav)+'</nav></header><main>'+''.join(html_parts)+'</main></html>',encoding='utf8')
for name in ['Hlasuj-0.8.7-prirucky.pdf','Hlasuj-0.8.7-produktovy-list.pdf']:
    reader=PdfReader(OUT/name);text='\n'.join(p.extract_text() for p in reader.pages)
    assert 'Hlasuj!' in text and '0.8.7' in text and len(text)>600
    if 'produktovy' in name:assert len(reader.pages)==1
    print(name,len(reader.pages),'pages; text verified')
