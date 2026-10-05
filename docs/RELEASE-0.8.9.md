# Hlasuj! by MiloslavHub 0.8.9

## Výsledek iterace

Přenos předmětu s přednáškami a jejich otázkami mezi instalacemi. V menu **Živé hlasování → Přenést obsah** učitel stáhne otevřený JSON, v cílové instalaci zobrazí náhled a potvrdí vytvoření nových konceptů. Sdílené otázky se kopírují jednou a zachovávají vazby i pořadí. Původní obsah, výsledky a QR se zachovávají.

Frontend a plugin: **0.8.9**. Hlasovací schéma: **0.8.5**, bez SQL migrace. Nová funkce ukládá obsah do běžných WordPress posts/meta a dočasný náhled a zámek přes standardní WordPress API. Žádné nové vlastní tabulky nevytváří. AI zůstává standardně vypnutá.

## Změny

- Export ověřuje oprávnění správce a právo upravit každý obsažený záznam.
- Import vyžaduje přihlášení, nonce, validní formát a potvrzení jednorázového náhledu daného účtu. Per-user zámek brání souběžným potvrzením.
- Validátor odmítá neznámá pole a verze, neplatné typy, chybné/duplicitní vazby, příliš velký soubor a rozsáhlé seznamy.
- Vznikají nové drafty s novými náhodnými adresami. Async ankety zůstávají vypnuté; nevznikají běhy ani hlasy.
- Při zachycené chybě zápisu se odstraňují právě vytvořené koncepty. Neúspěšné odstranění je oznámeno správci; fatální přerušení vyžaduje kontrolu částečných konceptů.
- Editor přednášky zachovává již přiřazený nepublikovaný předmět a otázky, aby běžné uložení neztratilo vazby importovaných konceptů.
- Nové UI používá WordPress gettext a pedagogické názvy. Kompletní anglické rozhraní zůstává samostatnou etapou.
- Aktualizované návody, reference, technický formát, marketing, kontrola verzí distribuce a cache označení frontendu.

## Ověření

330 kontrol: 51 bezpečnostních kontraktů, 93 AI kontraktů, 3 kontrol vypnuté AI, 18 demo HTTP kontrol, 11 frontendových a 12 AI prohlížečových kontrol, 19 základních WordPress/DB kontrol, 21 AI WordPress kontrol, 50 kontrol přenosu na skutečném WordPressu/MariaDB, 28 kontrol souběhu, 13 kontrol AI administrace a 11 kontrol administrace přenosu v Edge. PHP/JavaScript syntaxe zkontrolována. AI odpovědi jsou syntetické fixtures, bez placených volání.

Navíc místní souběžné dávky 30 a 100 hlasů přes nezávislé PHP procesy, skutečné REST callbacks a MariaDB. Nejde o měření kapacity produkčního hostingu, síťové latence ani dlouhodobého pollingu. Kvůli běžícím kopírováním a místní zátěži nelze časy této iterace srovnávat jako výkonnostní benchmark.

Přenos prošel skutečným exportem → nahráním souboru → náhledem → potvrzením v přihlášené administraci. Testy ověřily zachování textů s lomítky/apostrofy, round trip, nové drafty/QR, odmítnutí jiného účtu a opakovaného potvrzení, chyby formátu a vložené chyby zápisu s odstraněním nových záznamů. Syntetická testovací data a odchozí HTTP blokace. Doklad: `docs/test-evidence/2026-10-05-content-transfer.json`.

## Hranice a rizika

První formát přenáší celý předmět s přiřazenými otázkami a podporovanými nastaveními. Výsledky, lidé, kategorie, soubory, externí URL, termíny, původní ID/QR a globální nastavení jsou vynechány. Soubor obsahuje správné odpovědi a může obsahovat citlivé informace ve vlastních textech. Není to úplná záloha ani výsledkový archiv. Návrat při chybě je odstranění nových záznamů, nikoli transakce všech WordPress pluginů. Podrobnosti: `docs/CONTENT-FORMAT.md` a zákaznická příručka přenosu.

Management stále používá `manage_options`; oddělení učitelů/organizací není hotové. Veřejná dostupnost některých výsledkových endpointů má dosavadní omezení. Plné domácí úkoly, pokusy/feedback, kompletní cs/en UI, privátní integrační API, právní podmínky frontendu a hostingová kvalifikace zůstávají dalšími etapami.

## Nasazení a předání

Místní testy samy neprokazují nasazení. Konkrétní aplikovaný commit, stav produkce, záloha, ověření a úklid se zapisují do `docs/DEPLOYMENT-2026-10-05-0.8.9.md`. Zpráva z nasazení 0.8.8 a historické ZIPy zůstávají zachované. Výsledné dvě distribuce a PDF patří do `releases/0.8.9/`.
