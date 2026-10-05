# Audit Hlasuj! by MiloslavHub

**Aktualizace 2026-10-02 pro 0.8.7:** opravný rozsah původního 0.9.0-dev.1 prošel také 19 integračními kontrolami na WordPressu 7.1.2/MariaDB 11.4.9. Oba SQL exporty byly obnoveny a tabulky zkontrolovány lokálně. Původní body níže označené „dosud neověřeno“ zachycují stav před touto kvalifikací; aktuální souhrn je v RELEASE-0.8.7.md. Nasazení prokazuje samostatná deployment zpráva.

Aktualizace 2026-10-02. Soubory produkce byly načteny pouze pro čtení dne 2026-09-28. Níže jsou odděleny zdrojové důkazy od neověřeného provozního stavu.

## A. CURRENT STATE

Sdílený kořen původně obsahoval datované archivy 2026-09-23 a 2026-09-25, nikoli udržovaný zdrojový checkout. Nový checkout je `work/hlasuj`. Originální archivy zůstaly zachovány. `work/source-snapshot` je snímek frontendových a pluginových souborů; nejde o úplnou produkční zálohu. `outputs` obsahuje předávané výstupy. `Požadavky na systém` obsahuje přečtený centrální registr XLSX z 29. 9. (75 požadavků, 19 rozhodnutí). Odpovídá směru MASTER-SPEC; vazba na tuto iteraci je v TRACEABILITY.md. Sešit nebyl upraven.

## B. VERSIONS

| Vrstva | Důkaz v původních souborech | Stav |
|---|---|---|
| Frontend | manifest 0.7.9, demo changelog/assets 0.8.6.16, další changelogy 0.8.6.x | Nejednotné komponentní verzování |
| Plugin | hlavička a konstanta 0.8.5 | Verze souborů na serveru, nikoli důkaz aktivace |
| WordPress | stažený `wp-includes/version.php`: 7.1.2, DB revize 61833 | Pouze souborový údaj; DB nebyla dotazována |
| Externí schéma | DDL a poslední migrace 0.8.5 | Skutečně aplikované migrace neověřeny |
| Dokumentace/balíčky | datované archivy + více interních verzí | Chybí společný release identifikátor |
| Nová lokální iterace | frontend/backend/docs 0.9.0-dev.1, schéma stále 0.8.5 | Vývojový stav, nenasazen |

34 frontendových souborů pracovního základu má stejné SHA-256 jako odpovídající soubory serverového snímku. Seznam důkazů a hashů: `production-source-inventory.json`, `source-baseline.json`. Z tohoto důkazu nelze určit aktivní plugin, aktuální obsah DB, cache ani změny na serveru po 28. 9.

## C. ARCHITECTURE

PHP frontend na subdoméně používá JavaScript REST klienta `/mhl/v1`. WordPress spravuje obsah v posts/meta. Plugin používá také samostatné MySQL připojení přes `wpdb` a konstanty `MHL_LIVE_DB_*`. Podrobnosti v ARCHITECTURE.md. Demo je samostatná PHP aplikace se souborovým JSON úložištěm, nikoli kopie produkční databáze.

## D. GIT STATUS

Původní sdílené archivy nemají doložitelnou historii původního vývoje. Nový lokální repozitář `work/hlasuj` vznikl během této práce na větvi `master`; první commit uchovává zdrojový základ. Nové commity popisují pouze tuto iteraci. Remote není nastaven. Git není záloha produkční DB ani konfigurace.

## E. DATABASE / MIGRATIONS

Externí tabulky: `mhl_runs`, `mhl_sessions`, `mhl_votes`, `mhl_participants`, `mhl_session_joins`. Unikátní klíč hlasu: session + participant key; přezdívky rezervovány v předmětu/režimu. Identita nebodované ankety používá HMAC pro jednotlivou relaci.

Dodány DDL a migrace 0.3.0, 0.8.2–0.8.5 a varianty z 0.8.1. Bez databáze nelze prokázat aplikovanou kombinaci ani absenci ručních změn. `schema_ready()` kontroluje názvy tabulek/sloupců, nikoli všechny indexy, typy, kolace či obsah. Běžný WordPress request může spustit úklid/uzavření relací; proto byly zdroje čteny přes FTP, nikoli instalovány do produkce. Tato iterace nemění DDL ani nespouští migrace. Verze aplikace a schématu jsou nově oddělené konstanty.

## F. TESTS

Původní balíčky neměly automatickou testovací sadu. Nové testy ověřují oprávnění aktivace, starý stav joining, CSV vzorce, souhlasy dema, HTTP průběh, předčasné/pozdní/duplicitní odpovědi, připojení, lokální QR a projekční cestu. Přesný výsledek každého běhu je v přiloženém `test-results.json`. Dosud chybí reálná integrace WordPress + MySQL, souběhový a zátěžový test, obnovení zálohy a produkční smoke test.

## G. DOCUMENTATION

Původní frontend README, instalační pokyny, changelogy a privacy checklist popisují různé fáze produktu. Nová dokumentace odděluje funkce současné iterace od roadmapy; staré changelogy jsou historické. Technická pole nejsou schváleným jazykem učitelského rozhraní. Nové domácí úkoly ani dvojjazyčné UI nejsou zatím dodané funkce.

## H. CUSTOMER PACKAGES

Nejnovější původní archiv: `Hlasuj-by-MiloslavHub-komplet-2026-09-25-v4.zip`, SHA-256 `da5687e0170dbe1b99d32391f67678d7d96dc7b150737d2b2ba1ba3d57371529`. Starší: `MiloslavHub-Live-VSE-V-JEDNOM-2026-09-24.zip`, SHA-256 `049eadd2d19d738775c897a62e9ab2905460ae710e663baed57489d0c2969ae0`.

Původní interní frontend manifest uváděl 0.7.9 a neodpovídal všem variantám distribučních souborů; správný hash ZIPu sám nedokazuje konzistenci obsahu manifestu. Nový výstup je výslovně vývojový zdrojový balíček s manifestem a hashy. Není prezentován jako zákaznický instalační release.

## I. MARKETING / ASSETS

Marketing je v `frontend/index.php`, styly `assets/landing.css`; původní galerie je v `assets/screenshots`, loga v `assets/brand` a `demo/assets`. Backend obsahuje demo PPTX. Marketing předtím popisoval řízení QR kódem; v této iteraci text odpovídá řízení učitelem. Galerie je označena jako předchozí verze. Nové testovací screenshoty dokládají jen konkrétní lokálně ověřené obrazovky. Tvrzení o testování na 100+ studentech pochází z původních materiálů; v této práci nebylo nezávisle ověřeno.

## J. SECURITY / PRIVACY / LICENSE RISKS

**Opraveno lokálně:** veřejné spuštění live/test, automatické otevření starého joining stavu, veřejné přezdívky anonymních/odmítajících účastníků dema, první souhlas ukončující demo všem, ztráta volby po rejoin, CSV formula injection, lock soubory pro neexistující relace, nefunkční projekční cesta, CDN/external QR závislost v hlavním frontendu/demu, produkční fallback při chybějícím config.php.

**Otevřeno:** mnoho veřejných endpointů a dostupnost výsledků vyžadují ucelenou access-policy; token projekce sám nechrání veškeré výsledkové endpointy. Chybí centrální rate limiting, oddělení účtů učitelů a organizací, ověření souběhu hlasů i tvrdé serverové uzávěrky při konkurenčních požadavcích. Polling zatěžuje opakované kontroly schématu. Přezdívka je pseudonymní údaj. Mazání/retence a všechny zálohy se musí posoudit společně.

Ve staženém zdrojovém základu nebyly při cíleném hledání nalezeny potvrzené vložené produkční heslo/token/privátní klíč. Toto není důkaz jejich neexistence mimo kontrolované soubory. Riziková místa: produkční `config.php`, WordPress `wp-config.php`, uložená přihlašovací relace WinSCP, databázové dumpy, historie a budoucí zákaznické archivy. Produkční konfigurace nebyla stažena, přihlašovací hodnoty nebyly vypsány.

Plugin deklaruje GPL-2.0-or-later. QRCode.js 1.0.0 je lokálně dodán s MIT textem a proveniencí. Autorství a distribuční práva všech grafik/PPTX a samostatného frontendu nejsou úplně doložena. AGPL/komerční varianta je návrh k právnímu posouzení; licence nebyla přepsána. Viz LEGAL-CHECKLIST.md.

## K. INCONSISTENCIES

- Verze frontend manifestu, cache parametrů, changelogů a balíčků se rozcházejí.
- Teacher-controlled požadavek byl v rozporu s veřejným `/activate` a výchozím auto QR.
- Projekce volala koncovku `/current`, kterou backend neregistroval.
- UI obsahovalo tlačítka Síně slávy bez připojených obsluh událostí.
- Privacy text dema sliboval smazání po 15 minutách; skutečný fyzický úklid je vyvolán dalším požadavkem.
- Body za účast v anketě jsou stále starší podporovaná konfigurace, v rozporu s cílovým neutrálním nebodovaným modelem. Je nutné navrhnout přechod bez tiché změny historických výsledků.
- Metadata vyučujících nejsou samostatné uživatelské účty s oprávněními.

## L. TECHNICAL DEBT

Hustě psané velké PHP/JS třídy, smíšená prezentace/logika, řetězce natvrdo česky, minimální izolace doménové logiky, výchozí nastavení závislá na produkční doméně, schéma bez úplného migračního registru, opakované SQL kontroly, nejasný public/private API kontrakt, neschválená licenční strategie a chybějící obnovovací/zátěžové testy.

## M. NOW / NEXT / LATER

Konkrétní rozsah a pořadí jsou v ITERATION-PLAN.md. NOW obsahuje výše uvedené lokální opravy a jejich testy. NEXT řeší WordPress/DB ověření, učitelský tok, domácí úkoly, i18n, oprávnění a přenos obsahu. LATER zahrnuje obchodní/licenční moduly, Cards, AI a standalone distribuci.

Před implementací dalších produktových větví je potřeba určit první pilotní skupinu a očekávaný počet souběžných účastníků. Před produkcí je nezbytné potvrdit provozovatele/správce dat, retenční pravidla, servisní okno a konkrétní schválený release. Rizikové jsou zejména DB migrace, změny oprávnění/identit, přepis stabilních URL, mazání historie, licenční změny a rollback po nových hlasech. Zálohovat obě databáze, kód, config, média a verzovací metadata; ověřit obnovu mimo produkci.
