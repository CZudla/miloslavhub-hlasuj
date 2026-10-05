# Volitelný AI asistent: lokální pilot

Stav k 4. 10. 2026: implementováno a místně ověřeno včetně REST integrace na WordPressu/MariaDB; funkce je ve výchozím stavu vypnutá. Nebyla nasazena ani ověřena placeným voláním modelu. Tento dokument rozšiřuje vývojovou dokumentaci, nepopisuje funkci již dostupnou v produkční 0.8.7.

## Co pilot umí

V klasickém WordPress editoru otázky nabízí přeformulování názvu otázky a překlad názvu i možností cs/en. Otázka v tomto projektu používá pole názvu WordPressu. Přeformulování vrací možnosti beze změny; překlad zachovává počet, pořadí a prázdné pozice. Správný index se neposílá a nemění.

Učitel zvolí operaci, zobrazí obsah k odeslání a samostatným tlačítkem jej odešle. Návrh se zobrazí jako prostý text. „Použít návrh v editoru“ vyplní pouze editor; standardní uložení/publikování zůstává na učiteli. Změna textu po náhledu nebo během čekání zabrání přepsání novější práce.

Jde o pilot pro správce jedné instalace. Vyžaduje `manage_options`, oprávnění `edit_post` ke konkrétní otázce a platný REST nonce. Před rozšířením na organizace je nutné dokončit jejich vlastnictví obsahu a autorizaci.

## Konfigurace pro budoucí povolené ověření

V nynějším lokálním ověření se skutečná služba nezapíná a klíč není potřeba. Testy používají vlastní náhradu transportu a syntetický klíč.

Až bude schváleno ověření API, je k aktivaci potřeba `MHL_AI_ENABLED = true` v chráněné serverové konfiguraci a klíč v konstantě nebo proměnné prostředí `MHL_OPENAI_API_KEY`. Klíč se nesmí zapisovat do tohoto repozitáře nebo JavaScriptu. Volitelné konstanty:

| Nastavení | Výchozí hodnota | Význam |
|---|---|---|
| `MHL_AI_ENABLED` | vypnuto, pokud není přesně `true` | Hlavní vypnutí instalace |
| `MHL_AI_MODEL` | `gpt-6.1-sol` | Povolen je Sol a `gpt-6-luna` |
| `MHL_AI_DAILY_LIMIT` | 20 | Maximum pokusů za UTC den; 0 vypne pokusy, horní mez 100 |

Úroveň uvažování API je `low`. Nejvýše jeden AI požadavek běží v instalaci současně; mezi začátky jsou minimálně tři sekundy. Pokus se započítá i při chybě poskytovatele. Timeout transportu je 15 sekund, klient ukončuje čekání po 20 sekundách, automatická opakování nejsou zapnuta. Limit pokusů není dolarový rozpočet; před skutečným pilotem je potřeba určit rozpočet a nastavit kontrolu jeho spotřeby.

Přijímací limit používá MySQL/MariaDB `GET_LOCK` přes WordPress databázové spojení. Pokud zámek není dostupný, volání se odmítne. Odmítnutí při zámku drženém druhým DB spojením bylo místně ověřeno. Jeho dostupnost na cílovém hostingu a zátěžový dopad čekajících PHP procesů je nutné ověřit před nasazením. Schéma externí hlasovací DB se nemění.

## API a data

- POST `/wp-json/mhl/v1/ai/suggest`: JSON `question_id` (celé číslo), `operation` (`rephrase`/`translate`), `language` (`cs`/`en`), `title` a `options` (nejvýše 8 textů). Další pole jsou odmítnuta. Limity UTF-8 vstupu: název 2000 bajtů, každá možnost 1000 bajtů; HTML není podporováno.
- Výsledek obsahuje `suggestion: {title, options}`, model, verzi promptu a dobu volání. Nevytváří WordPress příspěvek ani neukládá návrh.
- POST `/wp-json/mhl/v1/ai/preference`: JSON `enabled` typu boolean. Mění pouze nastavení přihlášeného uživatele. Instalaci vypnutou hlavním přepínačem nelze osobním nastavením zapnout.
- K poskytovateli se posílá operace, jazyk a zkontrolovaný text názvu/možností. `question_id`, správný index, výsledky a identifikátory studentů se neposílají. Osobní údaje, které učitel sám vloží do textu, je potřeba před odesláním odstranit.
- Transport směřuje pouze na `https://api.openai.com/v1/responses`, bez následování přesměrování, s `store: false`, JSON Schema a serverovou validací. Toto nastavení samo nepotvrzuje nulovou retenci u poskytovatele. V pilotu se nepoužívá web, RAG ani nástroje modelu.
- Ukládá se osobní vypnutí a souhrnný počet/čas pokusů za den; obsah návrhu se tímto endpointem neukládá. Text návrhu se uloží až běžnou akcí učitele. Těla provider chyb a klíč se nevracejí klientovi.
- Podporován je zatím pouze standardní globální API endpoint. Před požadavkem na regionální zpracování je potřeba doplnit ověřené nastavení endpointu a účtu. Současný kód není potvrzením EU zpracování.

Kontrakt vychází z [GPT‑6.1 Sol](https://developers.openai.com/api/docs/models/gpt-6.1-sol), [Structured Outputs](https://developers.openai.com/api/docs/guides/structured-outputs) a [migračního průvodce](https://developers.openai.com/api/docs/guides/latest-model), ověřených při přípravě migrace.

## Lokální testy

`tests/ai.php` nahrazuje WordPress, databázi a HTTP transport testovacími objekty. `tests/ai-browser.cjs` používá skutečný panel vykreslený PHP, skutečný JavaScript a syntetické HTTP odpovědi na loopbacku. Tím se ověřuje tok editoru a chybové stavy; nejde o plný WordPress editor ani hodnocení skutečného modelu.

`tests/ai-wordpress-integration.php` byl 4. 10. spuštěn na izolovaném WordPressu 7.1.2 a MariaDB 11.4.9: 21 kontrol prošlo. Stejné prostředí prošlo také 19 původními kontrolami hlasování. AI integrační test ověřuje skutečné REST permission callbacks, nonce, role, opt-out, nezměněný příspěvek po návrhu, běžné explicitní uložení a souběh dvou DB spojení. HTTP transport je přerušen WordPress filtrem `pre_http_request`; neproběhlo volání poskytovatele. Prohlížečový průchod celou WordPress administrací ani zátěž skupiny tím nejsou pokryty.

```text
php tests/ai.php
php tests/ai.php disabled
python tests/run.py --php C:/php84/php.exe --browser
```

Pokud není Playwright dostupný v Node, nastavit `NODE_PATH` na místní balíčky. V této pracovní stanici byl použit balíček z `C:/Users/mihu0334/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules`.

`tests/fixtures/ai-evaluation.jsonl` obsahuje 40 syntetických zadání: 10 přeformulování, 10 překladů, 10 budoucích operací a 10 hraničních vstupů. Budoucí operace musí nynější pilot odmítnout. U přijatých zadání jsou uvedena kritéria věcného hodnocení. Soubor neobsahuje naměřené výsledky; hodnocení skutečných modelů zůstává neprovedeno podle požadavku uživatele na testy bez placeného API.

## Před skutečným pilotem

1. Na stagingu dokončit prohlížečový průchod celou WordPress administrací a ověřit dostupnost GET_LOCK na cílovém hostingu. Místní REST/DB integrace je ověřena.
2. Ověřit souběžné hlasování s AI a bez AI na očekávaném počtu studentů; dokončit potřebné opravy hlasovacího jádra z roadmapy.
3. Zajistit přístup k API, rozpočet, odpovídající nastavení zpracování dat a hodnotitele českých/anglických výstupů.
4. Porovnat Sol a Lunu na stejných vstupech. Pro cenu za použitelný návrh doplnit sběr skutečné tokenové spotřeby z odpovědí poskytovatele; aktuální panel ji neměří.
5. Teprve nad výsledkem připravit kvalifikovaný release, aktualizaci zákaznických příruček, zálohu a nasazení podle DEPLOYMENT.md.

Pro vypnutí odstranit `MHL_AI_ENABLED` nebo nastavit `false`. Změna platí pro nové požadavky; již zahájené provider volání nelze tímto přepínačem vzít zpět. Hlasování není na této funkci závislé.
