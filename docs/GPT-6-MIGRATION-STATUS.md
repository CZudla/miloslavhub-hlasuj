# Průběh migrace GPT‑6 — aktualizace 4. 10. 2026

## Hotovo lokálně

- Přidána projektová konfigurace `gpt-6.1-sol` s `model_reasoning_effort = high` v repozitáři i ve vstupní složce HLASUJ. Dvě vstupní cesty mají stejnou volbu modelu.
- Přečtené globální nastavení před změnou: `gpt-6-astra`, `high`. Globální nastavení zůstalo zachováno; projekt přepisuje pouze model, zachovává úroveň uvažování. Tím se zpřesňuje původní návrh pilotního `medium` podle skutečně zjištěného výchozího nastavení.
- Přidány projektové instrukce AGENTS.md se zdroji pravdy, kontrakty, testy a pravidly práce s citlivými daty.
- Implementován výchozím stavem vypnutý lokální AI pilot pro přeformulování a překlad otázky, s náhledem odesílaného obsahu a potvrzením návrhu. Technické detaily jsou v AI-PILOT.md.
- Připravena sada 40 syntetických hodnoticích zadání. API se nevolalo; uživatel výslovně zvolil pouze místní testy.

Projektová konfigurace se načítá jen v důvěryhodném projektu a může ji přepsat explicitní volba klienta. Přepnutí modelu již běžícího chatu tím není prokázáno. Pro ověřitelný nový CLI běh z repozitáře použít `codex --model gpt-6.1-sol`; desktopový klient ověřit v nabídce modelů. [Oficiální konfigurace](https://learn.chatgpt.com/docs/config-file/config-advanced).

## Pilotní rozbor 1: autorizace

Ověřené cesty v aktuálním kódu:

1. `class-mhl-rest.php`: routa `/activate` používá `can_activate`; stejnou kontrolu volá i callback `activate`.
2. `can_activate` dovoluje veřejně explicitní `async`, zatímco live/test vyžaduje `manage_options`. WordPress cookie autentizace má vlastní REST nonce mechanismus.
3. `MHL_Core::activate_question` kontroluje oprávnění znovu před live/test změnou a u async ověřuje povolení konkrétní otázky.
4. Administrativní otevření a uzavření prochází `session_guard`: `manage_options` a nonce `mhl_session_{id}`.
5. Studentský dotaz přes `context` může uzavřít expirovanou relaci, ale časový průběh starého stavu joining sám neotevře live/test otázku; `maybe_start_voting` to výslovně blokuje.

Nový AI endpoint má samostatné ověření přihlášení, nonce, hlavního/osobního vypnutí a editace konkrétní otázky. Návrh nemá cestu k publikaci ani k hlasovací databázi.

## Pilotní rozbor 2: učitelské rozhraní

Implementovaná malá změna je oddělený panel AI v editoru otázky. Lokální testy ověřují: žádný provider požadavek při pouhém náhledu, explicitní použití návrhu, zachování novějších úprav, chybu poskytovatele, textové vykreslení a osobní vypnutí. Testovací stránka používá skutečný PHP renderer panelu a jeho JavaScript, WordPress okolí je syntetické.

## Pilotní rozbor 3: souběh uzavření a hlasu

Ve stávajícím `MHL_REST::vote` se načte relace přes `context`, zkontroluje `status === open` a po dalších operacích se provede samostatný INSERT. Mezitím může druhý požadavek zavolat `close_session` nebo doběhnout deadline. V této cestě není společná transakce/zámek relace ani atomické opakované ověření stavu těsně při vložení.

Jde o zjištění z kódu, nikoli o nově provedený souběžný reprodukční test. Riziko již odpovídá bodu roadmapy o atomických časových hranicích. Migrace AI tuto cestu nemění.

Navržený následující fix: na stejném externím DB spojení zavést jednotný protokol pro příjem hlasu a všechny operace uzavírající/nahrazující relaci. Před vložením hlasu zamknout a znovu ověřit relaci, aktivitu běhu a serverový deadline; INSERT a související změny potvrdit společně. Pro run/session zámky stanovit jednotné pořadí, aby nevznikaly deadlocky. Ověřit použitý engine a duplicitní unikátní klíč. Zohlednit reset, skip, close_run a automatickou expiraci, nejen jedno tlačítko.

Reprodukční test pro dvě nezávislá DB spojení:

1. První požadavek přeruší hlas před konečnou kontrolou/vložením; druhý uzavře a potvrdí relaci. Po obnovení musí být hlas odmítnut a počet hlasů nezměněný.
2. Opačné pořadí: platný hlas získá zámek, projde kontrolou a uloží se; uzavření pokračuje až poté. Uloží se právě jeden hlas.
3. Přechod deadline během čekání na zámek musí hlas odmítnout. Přidat současné duplicity, ukončení celého běhu a reset otázky.

## Co ověření znamená

Regresní runner ukládá aktuální strojový výsledek do `runtime/test-results.json`. Obsahuje syntaktické kontroly, původní regresi i nové testy AI. Před implementací prošlo 51 původních kontraktů, 18 HTTP kontrol dema a 11 prohlížečových kontrol. Při přípravě prostředí stačilo zpřístupnit již instalovaný Playwright přes NODE_PATH.

Místní WordPress 7.1.2/MariaDB 11.4.9: nově prošlo 19 kontrol původního hlasování a 21 kontrol AI REST/DB integrace. Ověřen skutečný nonce, role, osobní vypnutí, limit, dvě DB spojení soupeřící o zámek a oddělení návrhu od explicitního uložení. Poskytovatel byl nahrazen filtrem `pre_http_request`, veškerý odchozí HTTP provoz byl blokovaný. Doklad je v `runtime/ai-integration-results.json`.

Poslední místní ověření 4. 10. 2026:

| Sada | Výsledek |
|---|---|
| PHP a JavaScript syntaxe | Prošlo |
| Původní kontrakty | 51/51 |
| AI kontrakty včetně 40 vstupních evaluačních případů | 93/93 |
| Vypnutá AI | 3/3 |
| HTTP demo | 18/18 |
| Původní prohlížečové scénáře | 11/11 |
| Prohlížečové scénáře AI panelu | 12/12 |
| Skutečný WordPress/DB — hlasování | 19/19 |
| Skutečný WordPress/DB — AI | 21/21 |

Celkem 228 kontrol; zahrnují také kontroly vstupů, nikoli 228 nezávislých koncových scénářů. Regresní runner sám spouští jen první sady; položka `wordpress_database_integration: not run` v jeho výsledku znamená, že integrační testy byly provedeny samostatně a doloženy druhým souborem. TOML obou projektových konfigurací byl načten parserem a shoduje se. `git diff --check` nehlásí chyby whitespace. Žádný z výsledků neověřuje odpovědi skutečného GPT modelu.

Zůstává neověřeno: kvalita Solu proti Astře/Luně, skutečná dostupnost účtu a API, tokenová cena, celý prohlížečový průchod skutečnou WordPress administrací a zátěž se zapnutou AI. Úspěch náhradních provider testů tyto výsledky nedokládá.

## Testovací prostředí a úklid

První rozbalení WordPressu do `runtime/ai-integration` narazilo na délku cesty Windows. Použitá nová krátká cesta je `C:/Users/mihu0334/.cache/hlasuj-ai-20261004`. Obsahuje pouze rozbalené runtimy, syntetické databáze, místní testovací konfiguraci a log. Obě místní DB jsou nové, nebyly obnoveny z produkce. Pomocný skript je v ignorovaném `runtime/run-ai-integration.py`; příkaz nepovoluje opětovné použití již inicializované DB. Server byl po ověření řádně ukončen. Soubory zůstávají lokálně pro kontrolu a nejsou součástí Git změn ani distribučního balíčku.

## Návrat a další krok

Projektové modelové nastavení lze vrátit odstraněním dvou přidaných `.codex/config.toml`; tím opět platí případné nadřazené/uživatelské nastavení. Modelové nastavení samo nevrací již provedené změny kódu.

AI je nyní vypnutá; nebyl nastaven skutečný klíč, spuštěn placený požadavek, změněno produkční schéma ani provedeno nasazení. Nový kód je lokální vývojová změna, nikoli kvalifikované vydání 0.8.7. Produkční verzi stále dokládá DEPLOYMENT-2026-10-02.md.

Následuje prohlížečové ověření celé administrace, oprava a test souběhu hlasování a až po samostatném povolení skutečné API evaluace. Výsledky pilotu je třeba doplnit do tohoto dokumentu, neoznačovat předem celou migraci za dokončenou.
