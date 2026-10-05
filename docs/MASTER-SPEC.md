# ============================================================
# HLASUJ! BY MILOSLAVHUB
# MASTER PRODUCT + DEVELOPMENT SPECIFICATION
# ============================================================

Jsi hlavní implementační agent projektu Hlasuj! by MiloslavHub.

Tvým úkolem je postupně vytvořit a dlouhodobě rozvíjet aplikaci Hlasuj!
podle této specifikace.

Nejde o jednorázové vytvoření „finálního produktu“.

Hlasuj! se vyvíjí iterativně:

návrh
→ implementace
→ testování
→ pilotní použití
→ poznatky
→ další verze

Každá verze musí být použitelná a bezpečná, ale zároveň očekáváme,
že UX, funkcionalitu, bezpečnost a architekturu budeme podle reálných
zkušeností postupně upravovat.

Zdrojový kód a skutečné prostředí jsou source of truth.

Pokud už nějaká část systému existuje:
- nejdříve ji prozkoumej,
- zachovej fungující data a kompatibilitu, pokud je to rozumné,
- nedělej zbytečný kompletní rewrite.

Pokud něco neexistuje:
- implementuj to podle této specifikace,
- nebo vytvoř správný základ / extension point / ADR, pokud plná
  implementace patří až do další verze.

# ============================================================
# 1. ZNAČKA
# ============================================================

Veřejný název:

Hlasuj!

Preferované označení:

Hlasuj! by MiloslavHub

Technický název:

Hlasuj

V technických identifikátorech nepoužívej vykřičník, pokud by komplikoval:

- URL
- API
- namespace
- databázové názvy
- package names
- repozitář
- názvy souborů
- systémové identifikátory

Hlavní positioning:

„Navrženo učitelem pro učitele.“

Hlavní claim:

„Zapojte publikum. Bez zdržování.“

Další schválené formulace:

„Vzniklo ve výuce. Pro výuku.“
„Techniku řeší Hlasuj. Učitel se věnuje výuce.“
„Složitost necháváme na sobě. Vám zůstává výuka.“
„Méně techniky. Více výuky.“
„Každý hlas se počítá.“
„Hlas každého má mít prostor.“

Důvěryhodnost:

„Testováno v reálné vysokoškolské výuce na více než 100 studentech.“

Nevytvářej marketingová tvrzení o funkcích, které reálně neexistují.

Produkt má být vizuálně spojen s MiloslavHub decentně.

Nevytvářej dojem, že produkt vytvořila nebo provozuje univerzita
či jiný zaměstnavatel autora.

Produkt je samostatný projekt MiloslavHub.

# ============================================================
# 2. HLAVNÍ FILOZOFIE
# ============================================================

Interně může být systém velmi komplexní.

Centrální administrace provozovatele na miloslavhub.cz může obsahovat:

- zákazníky
- organizace
- uživatele
- role
- licence
- moduly
- entitlementy
- trialy
- referral
- billing
- logy
- analytiku
- diagnostiku
- konfiguraci
- API
- deployment
- zálohy
- bezpečnost
- monitoring

Tato komplexita však NESMÍ být přenesena na zákazníka.

Hlavní zásada:

KOMPLEXITA UVNITŘ.
JEDNODUCHOST NAVENEK.

Cílový dojem učitele:

„Otevřel jsem to a věděl jsem, co mám dělat.“

# ============================================================
# 3. MAXIMÁLNÍ USABILITY UČITELE
# ============================================================

Teacher UX je jedna z nejvyšších priorit celého projektu.

Rozhraní musí být:

- jednoduché
- přehledné
- srozumitelné
- konzistentní
- bez zbytečné technické terminologie
- použitelné bez školení
- použitelné bez dlouhého návodu

Základní mentální model:

Moje předměty
→ přednáška
→ otázka
→ QR / odkaz
→ Spustit
→ Výsledky

Učitel nemá vidět technické pojmy typu:

- session token
- participant token
- endpoint
- entity activation
- available_from
- timeout
- database ID

Používej pedagogický jazyk.

Například:

„Spustit otázku“
„Připojit studenty“
„Zobrazit QR kód“
„Zobrazit výsledky“
„Domácí úkol“
„Odevzdat do“

Používej:

- minimum kroků
- rozumné defaulty
- progressive disclosure
- pokročilé volby pod „Další nastavení“
- jasná CTA
- prevenci chyb
- bezpečné undo/restore tam, kde dává smysl

Každou novou funkci vyhodnoť otázkou:

„Zvyšuje tato funkce zbytečně složitost práce učitele?“

Pokud ano, navrhni jednodušší UX.

# ============================================================
# 4. OUTCOME-NEUTRAL UX
# ============================================================

Používej princip:

POZITIVNÍ PROCES.
NEUTRÁLNÍ VÝSLEDEK.

Potvrzení provedené akce může být pozitivní:

„Odpověď byla uložena.“

Výsledek samotný ale prezentuj věcně.

Nevytvářej dark patterns ani UX, které emocionálně manipuluje uživatele
směrem k určité odpovědi.

Anketa musí být skutečně neutrální.

U hlasování nesmí UI samo naznačovat, která možnost je „správnější“,
pokud nejde o kvíz se skutečně definovanou správnou odpovědí.

# ============================================================
# 5. ACCESSIBILITY BY DESIGN
# ============================================================

Accessibility není doplněk.

Dodržuj minimálně:

- dostatečný kontrast
- dostatečně velký text
- velké touch targets
- focus states
- klávesové ovládání
- screen reader semantics
- správné labely
- zoom
- reduced motion
- stav nevyjadřovat pouze barvou
- responzivitu
- dobrou čitelnost na projektoru

Používej inkluzivní a nediskriminační jazyk.

Rozhraní má být použitelné pro různorodé skupiny uživatelů.

# ============================================================
# 6. PRODUKTOVÁ HIERARCHIE
# ============================================================

Preferovaný model:

Organizace
→ Učitel
→ Předmět
→ Přednáška
→ Otázka / aktivita

Obsah má být znovupoužitelný.

Otázka nemá být zbytečně technicky svázaná pouze s jedním jediným použitím.

Předmět může být duplikován například pro nový rok.

Přednáška může být opakovaně použita.

Otázky mohou být kopírovány mezi přednáškami.

# ============================================================
# 7. ZPŮSOB POUŽITÍ OTÁZKY
# ============================================================

Učitel nepřemýšlí o technických časových polích.

Primárně vybírá pedagogický scénář.

Například:

„Jak chcete otázku použít?“

[ Ve výuce ]
Otázku spustíte, až budete chtít.

[ Jako domácí úkol ]
Studenti odpoví samostatně do stanoveného termínu.

Technicky lze interně použít pole jako:

available_from
deadline
max_attempts
feedback_mode

ale běžné UI je takto nezobrazuje.

# ============================================================
# 8. ŽIVÉ HLASOVÁNÍ VE VÝUCE
# ============================================================

V produkčním prostředí:

UČITEL ŘÍDÍ TEMPO.

Student se připojí do předmětu / přednášky / relace.

Pokud není aktivní otázka:

student čeká.

Novou otázku aktivuje učitel.

Učitel může:

- spustit otázku
- ukončit otázku
- vysvětlovat libovolně dlouho
- přeskočit otázku
- zopakovat otázku
- vytvořit novou otázku
- udělat přestávku
- pokračovat později

Automatický průchod může být volitelný.

Nesmí být výchozí produkční režim.

Demo může být automatické.

# ============================================================
# 9. DOMÁCÍ ÚKOL
# ============================================================

Otázka nebo sada otázek může být zadána jako domácí úkol.

Teacher UI používá pedagogické pojmy.

Například:

Domácí úkol
Odevzdat do
Povolit další pokus
Zobrazit správnou odpověď po odevzdání
Zobrazit vysvětlení
Zkopírovat odkaz
Zobrazit QR kód

Podporuj:

- jednu otázku
- sadu otázek
- případně celý kvíz

Student postupuje samostatně.

Domácí úkol nepoužívá teacher-controlled live flow.

Výsledek domácího úkolu musí respektovat:
- oprávnění
- privacy
- bodování
- feedback rules
- export

# ============================================================
# 10. TYPY OTÁZEK
# ============================================================

Architektura má umožňovat minimálně:

- kvíz se správnou odpovědí
- anketa bez správné odpovědi

Budoucí rozšíření nesmí vyžadovat zásadní rewrite.

Například později:

- více správných odpovědí
- škála
- textová odpověď
- ranking
- feedback

Neimplementuj vše pouze proto, že je to možné.

# ============================================================
# 11. VYSVĚTLENÍ SPRÁVNÉ ODPOVĚDI
# ============================================================

Podporuj:

correct_answer_explanation

s režimy například:

teacher_only
show_after_close
hidden

Výchozí:

teacher_only

Dále:

teacher_note

teacher_note nikdy nezobrazuj studentovi.

Může obsahovat:

- co říct studentům
- zdroj
- metodickou poznámku
- poznámku k diskusi

U ankety bez správné odpovědi příslušné položky vhodně skryj.

# ============================================================
# 12. SOUTĚŽNÍ OTÁZKY
# ============================================================

Pro soutěžní režim:

- správnost je primární
- rychlost je sekundární

Výchozí model lze použít:

800 bodů za správnost
+
až 200 bodů podle rychlosti

Uchovej výpočet konfigurovatelný.

Společný prestart:

2
→ 1
→ START

Otázka má sdílené serverové otevření.

Například standardní 10sekundové okno,
pokud není nastavena jiná hodnota.

response_time_ms:

answered_at - opened_at

Pozdní připojení nedostává nový vlastní timer.

Server musí odmítnout pozdní hlas.

Během otevřené otázky nezobrazuj časy ostatních.

Po uzavření lze zobrazit:

Klára — 940 b. — 3,1 s

Na mobilu například:

Top 10 + vlastní pozice.

Na projekci například:

Top 5 + aktuální uživatel,
pokud to odpovídá UX.

# ============================================================
# 13. ANKETA
# ============================================================

Anketa:

- nemá správnou odpověď
- nemá špatnou odpověď
- nepřidává soutěžní body

Vybranou odpověď lze zvýraznit pouze jako:

„Vaše odpověď“

Pokud se po anketě zobrazuje soutěžní leaderboard,
jasně ukaž, že pořadí vychází z předchozích soutěžních otázek.

# ============================================================
# 14. DEMO
# ============================================================

Demo je self-guided demonstrace produktu.

Může automaticky postupovat.

Mobilní vstup:

Primární:

„Spustit demo na tomto telefonu“

Sekundární:

„Zobrazit skutečný QR kód pro druhý telefon“

Nevytvářej fake QR scénář, ve kterém by uživatel měl
skenovat vlastní obrazovku.

Demo může používat simulované účastníky,
ale musí být transparentně uvedeno, že jsou fiktivní.

Demo má reálně odpovídat produkční aplikaci.

Demo nesmí dlouhodobě zobrazovat jiné UX než skutečný produkt.

# ============================================================
# 15. STUDENT UX
# ============================================================

Student má mít minimum kroků.

Ideální flow:

QR / odkaz
→ případně přezdívka
→ připojeno
→ čekání
→ otázka
→ odpověď
→ potvrzení
→ výsledek podle pravidel

Bez povinného studentského účtu.

Bez instalace aplikace.

Browser-first.

Student UI má být velmi jednoduché.

# ============================================================
# 16. PŘEZDÍVKA
# ============================================================

Podporuj možnost přezdívky.

Přezdívka může být podle nastavení zachována
napříč otázkami/přednáškou.

Nemusí být povinná.

Privacy režimy musí umožnit anonymní použití.

# ============================================================
# 17. HALL OF FAME
# ============================================================

Síň slávy je dobrovolná.

Uživatel může zvolit například:

- zveřejnit pod přezdívkou
- zveřejnit anonymně
- nezveřejnit

Žádný veřejný opt-in nesmí být předem zapnutý.

Hall of Fame není hlavní funkcí marketingu.

# ============================================================
# 18. STABILNÍ QR KÓDY
# ============================================================

Persistent QR je klíčová retenční funkce.

Učitel vloží QR do prezentačního snímku jednou.

QR má fungovat opakovaně:

- při další přednášce
- další rok
- v novém semestru

Nemá být nutné neustále měnit prezentace.

Preferuj stabilní logický join URL model.

Například:

/join/{stable-code}

Za stabilním odkazem lze měnit aktivní relaci.

QR může být spojen např. s:

- předmětem
- přednáškou
- trvalým join pointem

Bezpečnost nesmí používat snadno odhadnutelné sekvenční ID.

Retention má vznikat z pohodlí, ne ze zadržování dat.

# ============================================================
# 19. EXPORT A IMPORT OBSAHU
# ============================================================

Uživatel musí moci exportovat:

- předmět
- přednášku
- otázky
- ankety
- kvízy
- nastavení
- vysvětlení
- QR logické vazby
- případné assets

Použij otevřený verzovaný formát.

Například:

manifest.json
subject.json
questions.json
assets/

zabalené do ZIP.

Například:

Kyberneticka-bezpecnost.hlasuj.zip

Při importu:

- validuj schema
- kontroluj verzi
- zabraň malicious ZIP/path traversal
- nepřepisuj existující data bez souhlasu
- řeš kolize ID
- na jiné instalaci vytvoř nové interní IDs

Podporuj:

„Exportovat bez výsledků“

pro sdílení s kolegou.

A:

„Exportovat včetně výsledků“

pro archivaci.

# ============================================================
# 20. EXPORT VÝSLEDKŮ
# ============================================================

Výsledky hlasování musí být exportovatelné.

Postupně podporuj:

CSV
XLSX
PDF
JSON

Export může obsahovat:

- otázky
- odpovědi
- agregace
- grafy
- body
- pořadí
- časy odpovědí

Podporuj režimy:

- anonymní
- pseudonymní
- identifikovaný podle oprávnění

Nevkládej osobní údaje do exportu bez důvodu.

# ============================================================
# 21. VÍCE UČITELŮ
# ============================================================

Jedna licence může obsahovat více učitelů.

Organizace/school account nesmí znamenat jeden login sdílený více lidmi.

Každý učitel má mít vlastní účet.

Dlouhodobý model:

organizations
users
organization_memberships
roles
subjects
subject_permissions

Role například:

owner
administrator
teacher
collaborating_teacher
viewer / analyst

Učitel spravuje obsah, ke kterému má práva.

Podporuj:

- pozvání kolegy
- sdílení předmětu
- spolupráci
- převod vlastnictví

Studenti se nepočítají do teacher seats.

# ============================================================
# 22. CENTRÁLNÍ ADMINISTRACE PRO MILOSLAVHUB
# ============================================================

Provozovatel má mít pokročilejší administraci.

Může být komplexnější než customer UX.

Má umožnit postupně:

- zákazníky
- organizace
- učitele
- licence
- moduly
- entitlementy
- trial
- complimentary licences
- sponsored licences
- referral
- expirace
- diagnostiku
- logy
- základní statistiky
- konfiguraci
- deployment metadata

# ============================================================
# 23. BEZPLATNÉ LICENCE UDĚLENÉ PROVOZOVATELEM
# ============================================================

Provozovatel může vybraným lidem/institucím zdarma udělit:

Complimentary
Pilot
Sponsored
Partner

licenci.

Licence může definovat:

- moduly
- teacher seats
- expiraci
- případně bez expirace
- interní poznámku

Například:

kolegové
přátelé
pilotní škola
spolupracovníci

Bezplatná admin-granted licence není totéž jako Free tarif.

Musí být možné:

complimentary → paid

bez ztráty obsahu.

# ============================================================
# 24. TRIAL
# ============================================================

Plánovaný model:

Cloud:
cca 30 dní plné verze zdarma.

Bez nutnosti platební karty.

Instituce:
cca 45denní pilot.

Po skončení trialu nesmí dojít k automatické platbě
bez explicitního souhlasu.

Cloud může přejít na Free.

Zachovej data a možnost exportu.

# ============================================================
# 25. REFERRAL
# ============================================================

Referral program:

„Doporučte Hlasuj. Získáte ho na déle.“

Preferovaný princip:

1 nový platící zákazník
=
+3 měsíce licence doporučujícímu.

Referral benefit vzniká až po placené konverzi.

Ne za samotné odeslání e-mailu.

Podporuj:

referral_code
referral_link
pending
registered
converted
rejected

Počítej s inbound adresou typu:

doporuceni@hlasuj.miloslavhub.cz

případně plus-addressing.

Obsah doporučovacího e-mailu standardně neukládej.

Používej pouze nezbytná metadata.

# ============================================================
# 26. MODULÁRNÍ PRODUKT
# ============================================================

Nechceme jeden gigantický monolit.

Dlouhodobý koncept:

Hlasuj Core

+

jednoúčelové kompatibilní moduly.

Příklady:

Hlasuj! Live
Hlasuj! Cards
Hlasuj! Feedback
Hlasuj! Quiz
Hlasuj! Meetings

Moduly mohou sdílet:

- účet
- organizaci
- licence
- obsah
- výsledky
- design system
- API

Zákazník si může pořídit jen to, co potřebuje.

Nevytvářej mikro-moduly z každé drobné funkce.

# ============================================================
# 27. HLASUJ! CARDS
# ============================================================

Dlouhodobý modul pro hlasování bez vlastního zařízení účastníka.

Žáci/účastníci používají fyzickou kartu.

Marker/orientace reprezentuje např.:

A
B
C
D

Učitel namíří kameru na třídu/místnost.

Preferovaný způsob:

LIVE SCAN z kamery.

Systém používá více po sobě jdoucích snímků
pro robustní rozpoznání.

Fotografie je fallback.

Preferuj strojově čitelné markery.

NEPOUŽÍVEJ:

- face recognition
- biometrickou identifikaci

Standardně:

- neukládat video
- neukládat fotografii
- nerozpoznávat obličeje
- ideálně zpracovat lokálně
- uložit pouze výsledek hlasování

Možné režimy:

anonymní
pseudonymní karta

Cards je nyní roadmapa, pokud není skutečně implementována.

# ============================================================
# 28. VYUŽITÍ MIMO ŠKOLSTVÍ
# ============================================================

Core nemá být technicky svázaný pouze se školami.

Budoucí scénáře mohou zahrnovat:

- školení
- konference
- workshopy
- porady
- spolky
- shromáždění
- SVJ

Pro formální právně významné hlasování je ale nutné
později řešit speciální požadavky:

- identifikaci
- vážené hlasy
- kvórum
- plné moci
- audit trail

Nepropaguj toto jako hotovou funkci bez implementace a právního ověření.

První go-to-market zůstává primárně vzdělávací.

# ============================================================
# 29. DEVICE-AGNOSTIC
# ============================================================

Hlasuj! není aplikace „pro mobily“.

Účastník může používat:

- telefon
- tablet
- notebook
- PC
- Chromebook

Dlouhodobě Cards umožní hlasovat bez vlastního zařízení.

Používej marketingové formulace typu:

„Připojte se z telefonu, tabletu nebo počítače.“

Ne:

„Musíte použít mobil.“

# ============================================================
# 30. I18N
# ============================================================

Aplikace musí být od počátku připravena na více jazyků.

Primární locale:

cs-CZ

Druhý podporovaný locale:

en-US

Architektura musí umožnit snadné přidání dalších jazyků.

User-facing texty nedávej natvrdo do business logiky.

Používej translation keys.

Například:

teacher.start_question
teacher.show_results
student.waiting
student.your_answer
assignment.deadline

Rozliš:

UI LANGUAGE

od:

CONTENT LANGUAGE.

Český učitel může mít aplikaci česky
a vytvořit anglickou přednášku.

Chybějící překlad má používat definovaný fallback.

# ============================================================
# 31. AI
# ============================================================

AI je VOLITELNÝ ASISTENT.

Hlasuj! musí plně fungovat bez AI.

AI lze vypnout:

- uživatelem
- organizací

Potenciální funkce:

- přeformulovat otázku
- zlepšit srozumitelnost
- navrhnout odpovědi
- navrhnout vysvětlení
- vytvořit variantu otázky
- zjednodušit text
- překlad

AI výstup se nikdy automaticky nepublikuje.

Učitel jej musí schválit.

Výsledky studentů neposílej automaticky externí AI službě.

U AI jasně vysvětli případné zpracování dat.

Marketingově AI nedělej hlavním tématem.

Vhodné označení:

„Volitelný AI asistent“

# ============================================================
# 32. ZÁKAZNICKÁ HOMEPAGE
# ============================================================

Zákaznické instalace mohou mít jednoduchou vstupní stránku.

Například:

logo organizace
název
krátký úvod
Připojit se k hlasování
Přihlásit se jako učitel
Nápověda
Soukromí
Kontakt

# ============================================================
# 33. THEMING / ŠABLONY
# ============================================================

Umožni jednoduché přizpůsobení.

Nevytvářej složitý page builder.

Zákazník může například změnit:

- logo
- hlavní barvu
- doplňkovou barvu
- název
- krátký text
- ilustrační obrázek

Lze nabídnout několik připravených šablon.

Důraz:

JEDNODUCHOST.

# ============================================================
# 34. DESIGN SYSTEM
# ============================================================

Vytvoř jednotný Hlasuj Design System.

Definuj:

- buttons
- forms
- inputs
- cards
- modals
- dialogs
- tables
- notifications
- errors
- empty states
- typography
- spacing
- icons
- breakpoints
- focus
- accessibility
- motion

Nedovol, aby každý modul používal jiný vizuální jazyk.

# ============================================================
# 35. VIZUÁLNÍ IDENTITA
# ============================================================

Postupně potřebujeme:

- hlavní logo Hlasuj!
- Hlasuj! by MiloslavHub lockup
- ikonu
- favicon
- light version
- dark version
- monochrome version
- brand guide
- sadu ikon
- screenshoty
- diagramy
- marketingové obrázky
- social graphics
- propagační materiály
- produktový one-pager
- prezentace

Zdrojové assety verzuj.

Screenshoty nesmí zobrazovat zastaralou verzi aplikace.

# ============================================================
# 36. PUBLIC API
# ============================================================

Připrav bezpečné veřejné API pro integrace.

Má být:

- verzované
- dokumentované
- stabilní
- autorizované podle operace
- rate limited
- auditovatelné

Nezpřístupňuj data jen proto, že existují v DB.

# ============================================================
# 37. PRIVATE API
# ============================================================

Odděl interní API.

Použití:

- centrální administrace
- licence
- entitlement
- provisioning
- referral
- interní synchronizace
- deployment / internal tools

Private API nepublikuj jako veřejné.

# ============================================================
# 38. OPENAPI
# ============================================================

Veřejné API dokumentuj pomocí OpenAPI.

Private API může mít interní OpenAPI/specifikaci,
ale nesmí být automaticky veřejně dostupná.

# ============================================================
# 39. DEPLOYMENT MODELY
# ============================================================

Rozliš:

produktový modul

od:

způsobu nasazení.

Dlouhodobé deployment varianty:

Hlasuj Cloud
Hlasuj Standalone
Hlasuj for WordPress

Cloud:

výchozí pro běžného zákazníka.

Cíl:

Bez instalace.
Bez správce.
Bez školení.

Standalone:

budoucí samostatná instalace.

Bez WordPressu.

Bez nutnosti externího databázového serveru.

Preferuj SQLite jako robustní lokální storage.

WordPress:

integrační možnost,
ne povinný základ celého produktu.

# ============================================================
# 40. KOMENTÁŘE A KVALITA KÓDU
# ============================================================

Používej smysluplné komentáře.

Komentuj zejména:

- proč je něco řešeno takto
- business pravidla
- bezpečnostní rozhodnutí
- privacy rozhodnutí
- netriviální algoritmy
- kompatibilitu
- workaroundy

Nekomentuj triviální řádky kódu.

Používej vhodné docblocks.

TODO/FIXME musí být konkrétní.

# ============================================================
# 41. LICENCE SOFTWARE
# ============================================================

Prověř licenční model.

Je zvažována kombinace:

AGPL
+
komerční licence.

Neimplementuj licenční politiku bez kontroly kompatibility
s third-party dependencies.

Udržuj:

LICENSE
THIRD-PARTY-NOTICES

nebo vhodný ekvivalent.

Vytvoř dependency/license inventory.

Právně nejasné položky označ:

REQUIRES LEGAL REVIEW

# ============================================================
# 42. GDPR / PRIVACY BY DESIGN
# ============================================================

U každé nové funkce posuď:

- jaká data vzniknou
- proč jsou potřeba
- kdo k nim má přístup
- retention
- anonymizaci
- pseudonymizaci
- export
- výmaz
- logování
- zálohy

Preferuj data minimization.

Zvláštní pozornost:

- žáci
- studenti
- nezletilí
- přezdívky
- výsledky
- Hall of Fame
- IP adresy
- logs
- analytics
- cookies
- local storage
- referral
- AI
- Cards kamera
- exporty

Nikdy nevytvářej tvrzení:

„100% GDPR compliant.“

# ============================================================
# 43. PRÁVNÍ DOKUMENTACE
# ============================================================

Udržuj podle potřeby:

Privacy Policy
Terms of Service
Cookie information
DPA template
Retention Policy
Subprocessors List
EULA / Commercial License

Dokumenty verzuj.

Každý má mít minimálně:

version
effective date

Při změně funkcionality zkontroluj,
zda právní dokumentace stále odpovídá produktu.

# ============================================================
# 44. LEGAL CHECKLIST
# ============================================================

Udržuj:

LEGAL-CHECKLIST.md

Kontroluj:

- GDPR
- cookies
- děti/nezletilé
- školy
- trial
- billing
- refundace
- spotřebitelé
- B2B
- referral
- licence
- AI
- retention
- export
- odpovědnost
- marketingová tvrzení

Pokud něco vyžaduje právníka:

REQUIRES LEGAL REVIEW

Nevymýšlej právní závěr.

# ============================================================
# 45. SECURITY BY DESIGN
# ============================================================

Bezpečnost není „pozdější fáze“.

Kontroluj v každé verzi:

- authentication
- authorization
- role checks
- organization isolation
- tenant isolation
- CSRF
- XSS
- SQL injection
- input validation
- upload validation
- rate limiting
- session handling
- API authentication
- token entropy
- secrets
- logging
- export permissions
- QR security
- backup/recovery

Secrets nikdy nevkládej do repozitáře.

# ============================================================
# 46. LOGOVÁNÍ
# ============================================================

Logy nesmějí bez důvodu obsahovat citlivá data.

Loguj:

- důležité provozní události
- chyby
- bezpečnostní incidenty
- administrativní změny
- relevantní API stav

Odděl:

debug
production
security/audit

Podporuj retention.

# ============================================================
# 47. MARKETINGOVÁ STRATEGIE
# ============================================================

Hlavní positioning při startu:

Hlasuj!
Navrženo učitelem pro učitele.

Produkt vzniká z reálné potřeby ve výuce.

Primární výhody:

- jednoduchost
- minimum práce učitele
- student bez účtu
- bez instalace aplikace
- QR/web
- učitel řídí tempo
- stabilní QR
- vlastní zařízení není omezeno jen na mobil
- privacy-aware

Neprezentuj roadmapu jako současnou funkčnost.

# ============================================================
# 48. ZERO-BUDGET LAUNCH
# ============================================================

Dokud produkt nezačne generovat příjmy:

- žádná placená reklama
- žádná marketingová agentura
- žádné významné marketingové výdaje

Primární kanály:

- osobní doporučení
- pilotní uživatelé
- referral
- LinkedIn
- SEO
- kvalitní obsah
- reference
- samotné používání produktu

První validační cíle:

20 skutečně aktivních učitelů
5 platících zákazníků

Měř funnel:

homepage
→ demo
→ trial
→ vytvoření otázky
→ první živé hlasování
→ druhé použití
→ placená licence
→ referral

# ============================================================
# 49. PILOTNÍ INSTITUCE
# ============================================================

Podporuj možnost individuálně udělit pilotní/sponzorskou licenci.

Například pilotní základní škole.

Možný model:

12 měsíců zdarma.

Bez:
- povinné reklamy
- automatického placeného prodloužení

Reference nebo logo školy pouze se souhlasem.

# ============================================================
# 50. PARTICIPACE
# ============================================================

Produkt může pracovat s hodnotovou linií:

„Každý hlas se počítá.“
„Hlas každého má mít prostor.“

Hlasuj může podporovat:

- aktivní zapojení
- vyjádření názoru
- společnou diskusi
- respekt k odlišným názorům

Netvrď kauzálně:

„Hlasuj vytváří demokracii.“

# ============================================================
# 51. ZÁKAZNICKÉ BALÍČKY
# ============================================================

Každý stabilní release má synchronizovat:

- zdrojový kód
- produkci na miloslavhub.cz
- customer package
- dokumentaci
- návody
- screenshoty
- marketing

Customer package nesmí obsahovat:

- secrets
- hesla
- produkční credentials
- debug dumps
- interní citlivé dokumenty

Podle podporované varianty připrav:

README
Quick Start
Installation Guide
Upgrade Guide
CHANGELOG
checksums

# ============================================================
# 52. RELEASE MANIFEST
# ============================================================

Vytvoř centrální release manifest.

Například:

release-manifest.json

Obsah minimálně:

release_version
frontend_version
backend_version
schema_version
documentation_version
git_commit
build_timestamp
artifacts
checksums

# ============================================================
# 53. DOKUMENTACE
# ============================================================

Udržuj:

README.md
PRODUCT-PRINCIPLES.md
ARCHITECTURE.md
ROADMAP.md
SECURITY.md
PRIVACY.md
LEGAL-CHECKLIST.md
DEPLOYMENT.md
UPGRADE.md
CHANGELOG.md
LEARNING-LOG.md
API.md
DESIGN-SYSTEM.md
BRANDING.md

Podle potřeby:

ADR/

Uživatelská dokumentace:

- Quick Start pro učitele
- Administrace organizace
- Předměty
- Přednášky
- Otázky
- Domácí úkol
- QR
- Živé hlasování
- Výsledky
- Export/import
- Privacy

V českém textu používej:

„snímek“

nikoli:

„slide“.

# ============================================================
# 54. ADR
# ============================================================

Důležitá rozhodnutí dokumentuj jako ADR.

Například:

- persistent QR
- multi-teacher / organization model
- modular architecture
- export format
- public/private API
- i18n
- AI isolation
- Cards privacy
- Cloud/Standalone/WordPress
- licensing architecture

# ============================================================
# 55. ITERATIVNÍ LEARNING LOG
# ============================================================

Udržuj:

LEARNING-LOG.md

Pro každou verzi:

- co jsme chtěli ověřit
- co jsme změnili
- jak jsme testovali
- co jsme zjistili
- co nefungovalo
- co fungovalo dobře
- co změnit příště
- co jsme se rozhodli nedělat

# ============================================================
# 56. PRODUKCE NA MILOSLAVHUB.CZ
# ============================================================

Produkční instalace autora má být po schváleném release
aktualizována na nejnovější stabilní verzi.

Nesmí dlouhodobě vzniknout:

repo = A
produkce = B
customer ZIP = C
docs = D
marketing = E

# ============================================================
# 57. POVINNÁ ZÁLOHA PŘED DEPLOYEM
# ============================================================

Před každou produkční aktualizací Hlasuj vytvoř
úplnou zálohu aktuální produkční verze.

Podle skutečné architektury:

- frontend
- backend
- WordPress plugin
- relevantní DB
- config
- assets/uploads
- migration state
- version metadata

Příklad:

Hlasuj-backup-<version>-<YYYY-MM-DD-HHMM>

Součástí zálohy:

version
commit SHA
schema version
timestamp
checksums
restore instructions

Zálohu OVĚŘ.

Nestačí pouze vytvořit archiv.

Kontroluj:

- archiv lze otevřít
- očekávané soubory existují
- DB dump není prázdný
- restore je realistický

Pokud backup verification selže:

STOP DEPLOYMENT.

# ============================================================
# 58. DEPLOY PIPELINE
# ============================================================

Použij postup:

AUDIT
→ BACKUP
→ VERIFY BACKUP
→ BUILD
→ TEST
→ STAGING / PRE-DEPLOY
→ MIGRATIONS
→ DEPLOY
→ SMOKE TEST
→ CONFIRM RELEASE

Při kritické chybě:

ROLLBACK
→ VERIFY PREVIOUS VERSION
→ INCIDENT LOG
→ LEARNING LOG

# ============================================================
# 59. DATABASE MIGRATIONS
# ============================================================

Každá změna DB:

- samostatná migrace
- jasný účel
- test
- kompatibilita
- rollback/recovery popis

Nedělej destruktivní změnu bez zálohy.

# ============================================================
# 60. TESTY
# ============================================================

Vybuduj a postupně rozšiřuj test suite.

Testuj podle stacku:

- unit
- integration
- API
- authorization
- roles
- organization isolation
- migrations
- PHP syntax/static analysis
- frontend build/lint
- responsive UI
- accessibility smoke
- demo E2E
- live teacher flow
- student flow
- quiz
- poll
- timer
- late vote
- leaderboard
- final screen
- homework
- i18n
- privacy
- Hall of Fame
- export/import
- export results
- QR stability

# ============================================================
# 61. SCREENSHOTY
# ============================================================

Marketingové screenshoty musí odpovídat skutečné aktuální verzi.

Kontroluj:

- logo
- wording
- leaderboard
- times
- current UI
- mobile layout
- current branding

Pokud je možné vytvořit reprodukovatelný screenshot workflow,
udělej jej.

# ============================================================
# 62. SOURCE CONTROL
# ============================================================

Používej Git.

Pracuj v logických commitech.

Nevytvářej jeden gigantický commit.

Příklad:

feat(homework): add teacher-friendly assignment workflow
fix(demo): align 2-1-start countdown
feat(i18n): add cs-CZ and en-US foundation
feat(export): add versioned portable subject bundle
security(api): validate join tokens and rate limits
docs: synchronize release documentation

Respektuj existující conventions repozitáře.

# ============================================================
# 63. TÝMOVÁ PŘIPRAVENOST
# ============================================================

I když projekt nyní řídí jeden autor, kód nemá být napsán tak,
aby mu rozuměl pouze jeden člověk.

Používej:

- konzistentní strukturu
- naming
- comments
- README
- architecture docs
- ADR
- setup instructions
- development instructions
- test instructions

Nový vývojář má být schopný projekt pochopit a spustit
bez ústního vysvětlování autora.

# ============================================================
# 64. CO NEMÁŠ UDĚLAT
# ============================================================

Nedělej kompletní rewrite bez nutnosti.

Neimplementuj všechny roadmap funkce jen proto,
že jsou zde uvedeny.

Nevytvářej složitost v teacher UX.

Nevytvářej vendor lock-in zadržováním dat.

Nevytvářej neověřené právní závěry.

Nevkládej secrets.

Nevytvářej marketing funkcí, které ještě neexistují.

Nevytvářej AI dependency pro základní funkčnost.

Nevytvářej biometrické zpracování pro Cards.

# ============================================================
# 65. FÁZE PRÁCE
# ============================================================

PHASE 1 – AUDIT

Nejdříve prozkoumej existující projekt.

Zjisti:

- Git status
- branches
- verze
- architecture
- DB
- migrations
- APIs
- frontend
- backend
- plugin
- tests
- docs
- customer packages
- marketing assets
- secrets risks
- licenses
- deployment
- production version, pokud ji lze ověřit

PHASE 2 – RELEASE PLAN

Rozděl požadavky:

NOW
NEXT
LATER

NOW:

bezpečné základy nejbližšího pilotního release.

NEXT:

funkce následující iterace.

LATER:

větší roadmapa.

PHASE 3 – IMPLEMENTATION

Implementuj NOW.

Můžeš připravit extension points a ADR pro NEXT/LATER.

PHASE 4 – TEST

Proveď automatické a manuální testy.

PHASE 5 – RELEASE

Vytvoř:

- artifacts
- customer package
- changelog
- docs
- release manifest
- checksums

PHASE 6 – PRE-PRODUCTION

Připrav:

- backup
- migration plan
- deployment plan
- rollback plan

PHASE 7 – PRODUCTION

Pouze pokud máš reálný přístup a bezpečné podmínky.

Nejdříve backup + verification.

Potom deploy.

PHASE 8 – POST-DEPLOY

Smoke test.

Zapiš deployment a Learning Log.

# ============================================================
# 66. PRVNÍ DOPORUČENÝ RELEASE
# ============================================================

Pracovní označení:

Pilot Foundation

Pokud existující verzování umožňuje:

0.9.0

Jinak respektuj skutečné verzování projektu.

Primární cíle první nové iterace:

1. auditovat skutečný stav
2. zachovat fungující systém
3. sjednotit branding na Hlasuj!
4. zjednodušit teacher UX
5. opravit demo
6. teacher-controlled live flow
7. zavést pedagogické „Ve výuce / Domácí úkol“
8. založit cs-CZ / en-US i18n
9. stabilní QR základ
10. export/import základ
11. export výsledků základ
12. připravit multi-teacher architekturu
13. zkontrolovat privacy/security/licensing
14. sjednotit design system
15. aktualizovat dokumentaci
16. vytvořit zákaznické balíčky
17. aktualizovat screenshoty
18. aktualizovat marketing
19. vytvořit ověřenou produkční zálohu
20. aktualizovat miloslavhub.cz na schválenou verzi
21. smoke test
22. Learning Log

# ============================================================
# 67. VÝSTUP AUDITU
# ============================================================

Před nebo na začátku implementace vytvoř audit:

A. CURRENT STATE
B. GIT STATUS
C. VERSIONS
D. ARCHITECTURE
E. DATABASE
F. MIGRATIONS
G. CURRENT FEATURES
H. TESTS
I. DOCUMENTATION
J. CUSTOMER PACKAGES
K. MARKETING/ASSETS
L. SECURITY RISKS
M. PRIVACY RISKS
N. LICENSE RISKS
O. INCONSISTENCIES
P. TECHNICAL DEBT

A dále:

NOW
NEXT
LATER

# ============================================================
# 68. VÝSTUP RELEASE
# ============================================================

Po dokončení release vrať:

1. Release version
2. Implementované funkce
3. UX změny
4. Změněné soubory
5. Commits
6. DB migrations
7. Security changes
8. Privacy/legal/license changes
9. API changes
10. i18n changes
11. Export/import
12. Result export
13. Documentation
14. Marketing changes
15. Screenshot changes
16. Customer artifacts
17. Checksums
18. Test results
19. Known issues
20. Deferred roadmap items
21. Backup status
22. Deployment status
23. Smoke-test results
24. Learning Log
25. Doporučené priority další verze

# ============================================================
# 69. DEFINITION OF DONE
# ============================================================

Release není hotový, pokud:

[ ] aplikace neprojde kritickými testy
[ ] teacher UX obsahuje známou zbytečnou technickou složitost
[ ] dokumentace neodpovídá aplikaci
[ ] marketing neodpovídá aplikaci
[ ] screenshoty jsou zastaralé
[ ] customer package neodpovídá releasu
[ ] licence nejsou evidované
[ ] privacy text neodpovídá realitě
[ ] API není adekvátně zabezpečené
[ ] není znám stav DB migrací
[ ] není připraven rollback
[ ] před produkční změnou není ověřený backup
[ ] produkční deploy nebyl ověřen smoke testem

# ============================================================
# 70. HLAVNÍ MĚŘÍTKO ÚSPĚCHU
# ============================================================

Nevytvářej Hlasuj! jako systém s maximálním množstvím funkcí.

Vytvářej jej jako systém, který uživatel skutečně chce používat.

Technická sofistikovanost je vítaná uvnitř.

Uživatelská složitost nikoli.

Hlavní kontrolní otázka:

„Dokáže běžný učitel tuto funkci použít správně bez školení
a bez technického přemýšlení?“

Pokud ne:

zjednoduš ji.

# ============================================================
# 71. ZAČNI
# ============================================================

Začni auditem skutečného sdíleného projektu.

Potom vytvoř realistický NOW / NEXT / LATER plán.

Následně pokračuj implementací NOW.

Nevyžaduj schválení každé triviální lokální úpravy.

Zastav se před nevratnou nebo rizikovou produkční změnou,
pokud není k dispozici ověřená záloha, rollback nebo dostatečné informace.

Cílem není dnes vytvořit „finální Hlasuj!“.

Cílem je vytvořit nejlepší další verzi produktu,
nasadit ji, získat poznatky a pokračovat další iterací.