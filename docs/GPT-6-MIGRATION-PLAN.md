# Hlasuj!: plán přechodu na GPT‑6

Datum: 3. října 2026. Stav: návrh k realizaci. Rozsah potvrzen uživatelem: vývoj v Codexu i AI funkce aplikace.

Následný stav realizace je v GPT-6-MIGRATION-STATUS.md. Uživatel schválil pokračování a upřesnil pouze lokální testy bez placeného API. Níže zůstává původní plán; navazující záznam odlišuje provedené a neověřené kroky.

## 1. Doporučené rozhodnutí

**Pro další vývoj zvolit GPT‑6.1 Sol (`gpt-6.1-sol`). Pro první pilot AI funkcí použít stejný model jako kvalitativní základ a porovnat GPT‑6 Luna pro jednoduché operace.** Astra má smysl pro nejobtížnější architektonické a bezpečnostní rozbory, pokud na konkrétních úlohách přinese lepší výsledek.

OpenAI popisuje Sol jako model pro složité programování a odbornou práci s nižší cenou než Astra. Luna je určena pro jasně vymezené opakované úlohy. Volba pro Hlasuj! je doporučení podle jeho architektury a požadavků; zatím ji nepotvrzuje srovnávací měření na tomto projektu. [Oficiální katalog](https://developers.openai.com/api/docs/models), [modely v Codexu](https://learn.chatgpt.com/docs/models).

| Použití | Výchozí návrh | Kdy volbu změnit |
|---|---|---|
| Vývoj PHP, WordPress REST, JavaScriptu, testů a dokumentace | GPT‑6.1 Sol, začít výchozí úrovní uvažování klienta | Pro složité souběhy a autorizaci vyzkoušet vyšší úroveň; pokud nestačí, Astru |
| AI návrhy odpovědí, variant a vysvětlení pro učitele | GPT‑6.1 Sol, `reasoning.effort: medium` | Po změření zkusit `low`, pokud zachová pedagogickou kvalitu |
| Překlad, zjednodušení a přeformulování otázky | Pilot Sol `low`, porovnat Luna `low` | Převést na Lunu pouze operace, které projdou hodnocením |
| Výjimečně složité rozbory | GPT‑6 Astra | Použít cíleně, s porovnáním přínosu a spotřeby |

## 2. Co projekt dnes skutečně obsahuje

- Pracovní Git repozitář je `work/hlasuj`, nikoli nadřazená složka HLASUJ. Při průzkumu byl čistý, HEAD `d6bc71d`.
- README uvádí verzi 0.8.7. Zpráva `docs/DEPLOYMENT-2026-10-02.md` dokumentuje nasazení aplikačního commitu `40dce6213af07b01f4f9f2bf428786709979c109`, frontend/plugin 0.8.7 a externí schéma 0.8.5. Jde o přečtený doklad; aktuální produkce nebyla během přípravy plánu kontaktována.
- Frontend je PHP + JavaScript, backend WordPress plugin s REST namespace `/mhl/v1`, provoz hlasování používá externí MySQL/MariaDB. Demo má samostatné souborové úložiště.
- Ve zdrojích nebyl nalezen OpenAI klient, identifikátor používaného GPT modelu ani volání OpenAI API. Pole `assistant_url` představuje odkaz; AI/RAG metadata jsou politikou pro případný externí indexer.
- Implementace asistenta za případným externím odkazem není součástí prohlédnutého repozitáře. Jeho případná migrace potřebuje samostatný průzkum jeho zdrojů.
- `docs/MASTER-SPEC.md`, kapitola 31, požaduje volitelnou AI, možnost vypnutí uživatelem a organizací, potvrzení výstupu učitelem a zákaz automatického odesílání výsledků studentů.
- AI je v `docs/ROADMAP.md` a `docs/ITERATION-PLAN.md` zařazena mezi pozdější funkce. Realizace aplikační větve tedy vyžaduje vědomé zařazení pilotu do priorit.

Z toho vyplývají dvě samostatné práce: přechod vývojového nástroje na jiný model a doplnění nové aplikační integrace. V aplikaci nyní není identifikátor starého modelu, který by stačilo přepsat.

## 3. Větev A — další vývoj v Codexu

### A1. Zachytit výchozí nastavení

1. Zaznamenat skutečně zvolený model a úroveň uvažování v používaném klientovi. V tomto průzkumu nebyly uživatelské globální konfigurace čteny a aktuální model chatu nebyl ověřován.
2. Ověřit dostupnost GPT‑6.1 Sol pro přihlášený účet. U Enterprise/Edu může být potřeba povolení správcem. [Dostupnost modelů](https://learn.chatgpt.com/docs/models).
3. Pracovat v `work/hlasuj`; zachovat konkrétní výchozí commit a průběžné změny v Gitu.
4. Připravit krátký projektový `AGENTS.md`: autoritativní specifikace, architektura, příkazy testů, kompatibilita QR, oprávnění a pravidla release. Jeho obsah musí vycházet z existujících dokumentů, včetně pozdějších oprav jejich historických poznámek.

### A2. Zvolit model a předat kontext

V desktopovém klientovi vybrat GPT‑6.1 Sol mezi dostupnými modely. Pro CLI lze použít:

```text
codex --model gpt-6.1-sol
```

Při následném zavedení projektového nastavení může `.codex/config.toml` obsahovat:

```toml
model = "gpt-6.1-sol"
model_reasoning_effort = "medium"
```

`medium` je návrh opakovatelného pilotního nastavení; před změnou zaznamenat dosavadní efektivní nastavení a případné přepisy klienta/profilu. [Konfigurace Codexu](https://learn.chatgpt.com/docs/config-file/config-advanced). Konfigurace je zde pouze příkladem, nebyla vytvořena ani aktivována.

Předat modelu README, MASTER-SPEC, PRODUCT-PRINCIPLES, aktuální RELEASE a DEPLOYMENT zprávu a vždy konkrétní zadání. Rozsáhlou specifikaci doplňovat relevantními kapitolami. Produkční konfigurace, databázové exporty a přístupové údaje nejsou potřebným kontextem běžného vývoje.

### A3. Ověřit přínos na třech úlohách

Každou úlohu řešit nad stejným výchozím commitem s předem daným očekávaným výsledkem. Pokud je předchozí model dostupný, porovnat jej se Solem při srovnatelném nastavení.

1. Vysledovat autorizaci spuštění hlasování od REST routy po změnu stavu; doložit skutečné větve kódu.
2. Provést jednu malou schválenou změnu učitelského rozhraní; zachovat studentské URL a ověřit příslušný tok.
3. Navrhnout opravu souběhu uzavření otázky s příjmem hlasu včetně testu, který by původní problém skutečně odhalil.

Hodnotit správnost, zásahy mimo zadání, počet ručních oprav, čas do použitelného výsledku a dostupnou spotřebu. Podmínka přijetí: žádná nová závada oprávnění či ochrany dat a úspěšná ověření dotčených funkcí. Pokud Sol selže, ověřit vyšší úroveň uvažování nebo Astru na téže úloze.

### A4. Běžný provoz a návrat

Po přijetí nastavit Sol jako projektovou volbu a aktualizovat předávací dokumentaci. Při problému vrátit zaznamenanou volbu modelu. Již vytvořené změny kódu mají vlastní Git historii; přepnutí modelu je samo nevrací. Pro tuto větev není potřeba nasazovat nový frontend/plugin ani měnit databázi.

## 4. Větev B — volitelný AI asistent pro učitele

### B1. První rozsah

Začít v editoru otázky dvěma operacemi: **přeformulovat otázku** a **přeložit otázku mezi češtinou a angličtinou**. Po vyhodnocení přidat návrh odpovědí, variantu a vysvětlení. Tyto operace odpovídají kapitole 31 specifikace.

Učitel nejprve vidí, jaký obsah se odešle. Akce vrátí návrh, který může upravit nebo zahodit. Až potvrzení „Použít návrh“ přenese obsah do editoru; uložení/publikování proběhne standardní cestou WordPressu. Nové vysvětlení zůstane soukromým návrhem, dokud nebude implementováno jeho ukládání a pravidlo zveřejnění požadované roadmapou.

### B2. Architektura a místa změn

```mermaid
flowchart LR
    T[Editor učitele] --> R[Chráněná AI REST routa]
    R --> V[Oprávnění, vypnutí, limity, validace]
    V --> A[Serverový adaptér]
    A --> O[OpenAI Responses API]
    O --> J[Validovaný návrh]
    J --> N[Náhled a úprava učitelem]
    N --> S[Potvrzení a běžné uložení]
```

| Soubor / místo | Plánovaná změna |
|---|---|
| `wordpress/miloslavhub-live/includes/class-mhl-ai.php` — nový | Adaptér API, povolené operace, schéma vstupu/výstupu, limity a jednotné chyby |
| `wordpress/miloslavhub-live/miloslavhub-live.php` | Načtení nové komponenty |
| `wordpress/miloslavhub-live/includes/class-mhl-rest.php` | Nová chráněná POST routa, například `/ai/suggest`; název je návrh |
| `wordpress/miloslavhub-live/includes/class-mhl-admin.php` | Ovládání asistenta, náhled, potvrzení, vypnutí pro instalaci a uživatele |
| `wordpress/miloslavhub-live/assets/admin.js` a `admin.css` | Načítání, zrušení, chyba, porovnání návrhu a zachování rozpracovaného textu |
| `tests/` | Testy kontraktů API, chyb, oprávnění, izolace hlasování a UI |
| `docs/API.md`, `PRIVACY.md`, zákaznické příručky | Skutečné chování, odesílaná data, limity a možnost vypnutí |

Novou routu povolit jen autentizovanému oprávněnému správci přes WordPress nonce, s kontrolou práva upravit konkrétní otázku. V současném projektu nepředpokládat existenci hotové role učitele. Dokud není implementován model organizací, omezit pilot na jednu instalaci/správce; vypnutí instalace slouží jako její hlavní přepínač. Izolaci organizací a jejich správu zavést před otevřením funkce více organizacím.

Adaptér volá pouze server. Klíč bude v chráněném serverovém prostředí nebo konfiguraci mimo distribuční ZIP a Git. Hlasovací databáze se do tohoto volání nezapojuje. AI požadavek nesmí běžet uvnitř transakce ukládání hlasu, aktivace otázky nebo studentského pollingu. Omezit souběh AI volání, aby při pomalé odpovědi nevyčerpala PHP procesy potřebné pro hlasování.

### B3. Kontrakt s modelem

Použít Responses API, centrálně nastavit model a úroveň uvažování. Sol podporuje `low`, `medium`, `high`, `xhigh`, `max`; nepodporuje `none` ani `minimal`. Pro reasoning požadavky nepřidávat `temperature`, `top_p` ani parametry logprobs. [Model Sol](https://developers.openai.com/api/docs/models/gpt-6.1-sol), [migrace GPT‑6](https://developers.openai.com/api/docs/guides/latest-model).

Požadovat Structured Outputs přes `text.format` s JSON Schema a `strict: true`. Navrhnout oddělené schéma pro každou operaci, validovat jej také na serveru a zvlášť zpracovat odmítnutí, neúplný výstup a chybný obsah. Formální shoda se schématem nezaručuje správnost učiva. [Structured Outputs](https://developers.openai.com/api/docs/guides/structured-outputs).

Vstup obsahuje pouze zvolený text otázky, potřebné možnosti odpovědi, jazyk a pokyn. Nevkládat výsledky studentů, technické identifikátory, přezdívky ani jiné otázky. Obsah otázky zpracovávat jako data; vložené instrukce nesmějí měnit oprávnění nebo vyvolat akce. První pilot nepotřebuje přístup modelu k nástrojům, databázi, webu ani RAG indexu.

Návrh lokálních provozních limitů pro pilot: nejvýše 1 běžící požadavek na uživatele, omezený počet za minutu, maximální délka vstupu a výstupu a časový limit pod limitem hostingu. Konkrétní čísla určit po měření hostingu a API. Neopakovat bez omezení 429/5xx; opakování zastavit při vyčerpání rozpočtu. Při chybě zachovat obsah editoru a nabídnout nové vyžádání návrhu. Po dobu platného požadavku zabránit dvojímu kliknutí; výsledky staršího požadavku nesmějí přepsat novější editaci.

Logovat model, verzi promptu, stav, dobu běhu, spotřebu a identifikátor požadavku. Běžné logy mají vynechat texty otázek a tajné údaje. Retenci a místo zpracování ověřit před pilotem podle konkrétního účtu; pouhá dostupnost modelu neprokazuje nastavení účtu. Pro případné EU zpracování ověřit podporovaný režim a endpoint; Sol s EU data residency nepodporuje Fast mode. [Model Sol](https://developers.openai.com/api/docs/models/gpt-6.1-sol).

### B4. Porovnání a podmínky přijetí

Připravit 40 syntetických nebo výslovně určených výukových zadání: 10 přeformulování, 10 překladů, 10 návrhů odpovědí/variant/vysvětlení pro pozdější rozšíření a 10 nejednoznačných či rušivých vstupů. U každé operace stanovit očekávané chování a slepě posoudit výsledky učitelem. Klíčové hraniční případy zopakovat, protože model není deterministický.

Nejprve porovnat Sol a Lunu při stejném promptu, schématu a úrovni `low` na jednoduchých operacích. Složitější generování hodnotit se Solem `medium`. Poté měnit vždy jen jednu věc: model, prompt nebo úroveň uvažování.

Navržené brány pro pilot:

- 100 % pokusů o neoprávněný přístup a použití vypnuté AI odmítnuto před voláním poskytovatele.
- Žádné automatické publikování, únik studentských výsledků ani změna právě probíhajícího hlasování.
- Každý přijatý návrh projde serverovou validací; odmítnuté/neúplné odpovědi se zobrazí jako zvládnutá chyba.
- Alespoň 90 % běžných výstupů přijme učitel bez věcné opravy; práh je návrh, nikoli dosažený výsledek. Žádná kritická chyba správné odpovědi v hodnocené sadě.
- Zaznamenat medián a p95 doby návrhu, cenu za přijatý návrh, četnost opakování a ručních oprav. Praktický cíl pilotu: p95 do 15 sekund pro jednu krátkou otázku, ověřit měřením; delší úlohy případně oddělit.
- Stejný souběžný test hlasování s AI vypnutou i zapnutou: žádné nové ztracené hlasy a navržený limit zhoršení p95 hlasování nejvýše 10 %.

Lunu zavést pouze pro operace, kde splní stejné kvalitativní a provozní podmínky. Při nízké kvalitě ponechat Sol. Při nedostatečné kvalitě Solu ověřit Astru na chybových případech. Automatické placené opakování na dražším modelu není nutnou součástí prvního pilotu.

### B5. Regrese, pilot a návrat

Ověřit timeout, 401, 429, 5xx, odmítnutí, neúplný JSON, příliš dlouhý vstup, HTML/script v návrhu, dvojí požadavek, vyčerpaný limit, vypnutí během práce a nedostupné API. Při vypnuté AI či chybějícím klíči musí hlasování i běžná editace fungovat.

Navázat na existující `tests/run.py`, `tests/security.php`, `tests/browser.cjs` a izolované WordPress integrační testy. Dokumentovaná předchozí kvalifikace zahrnuje 51 kontraktových, 18 HTTP, 11 prohlížečových a 19 integračních kontrol. Tyto počty jsou historické důkazy, nikoli nově spuštěné testy tohoto plánu.

Postup: lokální testy s náhradním API → malá skutečná evaluace → staging pro jednoho správce → omezený pilot → vyhodnocení → širší zpřístupnění. Přístup k API a rozpočtový limit ověřit před skutečnými voláními.

Návrat při incidentu: vypnout AI centrálním přepínačem, nechat dokončit či ukončit rozpracované požadavky podle návrhu adaptéru a zachovat učitelem uložené otázky. Pokud je nutný návrat kódu, použít otestovaný release postup. První rozsah plánovat bez změny externí hlasovací DB; tím odpadá její rollback. Před nasazením připravit konkrétní balíček, zálohu, ověření a návrat podle stávajících provozních dokumentů.

## 5. Náklady a odhad práce

Základní ceny API uvedené v oficiálním katalogu k datu plánu:

| Model | 1 milion vstupních tokenů | 1 milion výstupních tokenů |
|---|---:|---:|
| GPT‑6 Luna | 0,10 USD | 0,50 USD |
| GPT‑6.1 Sol | 2 USD | 10 USD |
| GPT‑6 Astra | 10 USD | 50 USD |

Zdroj: [oficiální katalog modelů](https://developers.openai.com/api/docs/models). Jde o základní sazby, nikoli rozpočet hotové funkce nebo cenu používání Codexu. Celkové náklady ovlivní délka vstupů, reasoning, opakování, cache a režim zpracování. U pilotu měřit skutečnou účtovanou spotřebu a cenu za učitelem přijatý návrh. Měsíční částku nelze spolehlivě určit bez objemu použití.

Orientační pracovní odhad pro jednoho vývojáře, bez čekání na přístupy a zpětnou vazbu:

| Etapa | Odhad | Výstup |
|---|---:|---|
| A: nastavení a ověření vývoje | 0,5–1,5 dne | Projektové instrukce, volba modelu, záznam výsledků |
| B1: zadání, kontrakt a hodnoticí sada | 1–2 dny | Přesný rozsah dvou operací a očekávané výstupy |
| B2: server a administrace | 2–4 dny | Vypínatelný návrh s náhledem a potvrzením |
| B3: evaluace a provozní testy | 1–3 dny | Výběr modelu podle měření, testy selhání a zátěže |
| B4: pilot a vyhodnocení | 3–5 pracovních dnů kalendářně | Zpětná vazba a rozhodnutí o rozšíření |

Vývoj první aplikační verze tedy odhadem 4–9 člověkodnů před pilotem. Nezahrnuje dokončení rolí organizací, nový RAG, automatické hodnocení studentů ani rozsáhlý editor vysvětlení. Odhad zpřesnit po měření hostingu a ověření oprávnění.

## 6. Doporučené pořadí a stav předání

1. Zavést větev A a ověřit Sol na skutečných vývojových úlohách.
2. Dokončit potřebné oprávnění a stabilitu hlasovacího jádra podle existující roadmapy.
3. Připravit dva učitelské AI úkony a hodnoticí sadu.
4. Implementovat vypínatelný pilot se Solem, vyhodnotit Lunu pro jednoduché operace.
5. Rozšířit dostupnost a funkce podle výsledků pilotu.

Při přípravě tohoto dokumentu byl proveden pouze průzkum zdrojů a aktuální oficiální dokumentace a vytvořen tento plán. Nebyl přepnut model, změněna konfigurace aplikace, spuštěno API volání či nový regresní test ani provedeno nasazení. Přesná markdownová adresa migračního průvodce vrátila chybu načtení; plán používá úspěšně načtenou oficiální HTML verzi [Using GPT‑6](https://developers.openai.com/api/docs/guides/latest-model).
