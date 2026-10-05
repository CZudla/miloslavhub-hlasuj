# Průběh migrace GPT‑6 — aktualizace 5. 10. 2026

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

AI panel má náhled odesílaného textu, samostatné odeslání, návrh a výslovné použití v editoru. Syntetická stránka ověřuje také ochranu novějších úprav, chyby poskytovatele a textové vykreslení.

Dne 5. 10. byl navíc ověřen celý průchod skutečnou WordPress administrací v Edge: přihlášení, načtení panelu, náhled bez odeslání, REST návrh bez změny editoru, výslovné použití, návrat bez uložení, překlad, běžné uložení otázky a osobní vypnutí přetrvávající po novém načtení. Výsledek byl ověřen i přímým čtením uloženého příspěvku, možností a uživatelského nastavení. HTTP poskytovatele nahrazuje WordPress filtr; nebylo voláno API OpenAI.

## Pilotní rozbor 3: souběh uzavření a hlasu

Opravena mezera mezi předběžným načtením relace a samostatným INSERT hlasu. `MHL_DB::with_run_lock` zahájí transakci a zamkne řádek běhu. Příjem hlasu potom zamkne a znovu načte nejnovější relaci; ověří její totožnost, stav, aktivitu běhu a čas. Hlas se potvrzuje až po opakované kontrole časového limitu po INSERT; vypršelý čas způsobí rollback.

Stejné pořadí běh → relace používá otevření, ruční uzavření, opakování otázky, uzavření celého běhu, automatická expirace, simulace a odstranění testovacího běhu. Zastaralé ovládací tlačítko nemůže měnit již nahrazenou relaci. Selhání změny se v administraci zobrazí jako chyba.

Transakce vyžaduje InnoDB u tabulek runs, sessions, votes a session_joins; nekompatibilní instalace změnu odmítne. Schéma pro novou databázi nyní výslovně určuje InnoDB také pro první tři tabulky. Existující databáze se automaticky nekonvertuje. Před případným nasazením je nutné ověřit engine cílových tabulek a připravit zálohu podle DEPLOYMENT.md.

`tests/concurrency.py` a `tests/concurrency-worker.php` prošly 28 kontrolami v nezávislých PHP procesech se samostatnými DB spojeními. Bariéry používají testovací WordPress filtr SQL dotazů, bez pomocných přepínačů v aplikačním kódu. Ověřeno:

- uzavření, opakování otázky, otevření další otázky, ukončení běhu, expirace běhu a smazání testu před získáním zámku hlasem → HTTP 409 a žádný uložený hlas;
- hlas získá zámek první → uzavření čeká, uloží se jeden hlas a pak se relace uzavře;
- dvě současné odpovědi stejného účastníka → jedna 201, druhá 409, právě jeden hlas;
- vypršení času před získáním zámku i během INSERT → odmítnutí a rollback;
- nové opakování je waiting, zastaralé učitelské ovládání je odmítnuto, testovací úklid odmítá živý běh.

Zámek serializuje zápisy v jednom běhu. Tyto testy neprokazují výkon celé třídy ani odolnost vůči všem souběžným operacím, například zakládání více nových běhů zároveň. Zátěžový test na očekávaném počtu studentů zůstává dalším krokem.

## Co ověření znamená

Regresní runner ukládá aktuální strojový výsledek do `runtime/test-results.json`. Obsahuje syntaktické kontroly, původní regresi i nové testy AI. Před implementací prošlo 51 původních kontraktů, 18 HTTP kontrol dema a 11 prohlížečových kontrol. Při přípravě prostředí stačilo zpřístupnit již instalovaný Playwright přes NODE_PATH.

Místní WordPress 7.1.2/MariaDB 11.4.9: nově prošlo 19 kontrol původního hlasování a 21 kontrol AI REST/DB integrace. Ověřen skutečný nonce, role, osobní vypnutí, limit, dvě DB spojení soupeřící o zámek a oddělení návrhu od explicitního uložení. Poskytovatel byl nahrazen filtrem `pre_http_request`, veškerý odchozí HTTP provoz byl blokovaný. Doklad je v `runtime/ai-integration-results.json`.

Poslední místní ověření 5. 10. 2026:

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
| Souběh nezávislých PHP/DB procesů | 28/28 |
| Celý AI průchod WordPress administrací v Edge | 13/13 |

Celkem 269 kontrol v uvedených sadách; zahrnují dílčí kontroly vstupů a stavu, nejde o 269 nezávislých koncových scénářů. Navíc ověřen konečný uložený stav otázky, odpovědí, osobního vypnutí a počet dvou syntetických provider požadavků. Regresní runner sám spouští první sady; WordPress/DB, souběh a skutečnou administraci spouští `tests/local-integration.py` samostatně. Souhrnný doklad bez konfigurací a logů je v [test-evidence/2026-10-05-local.json](test-evidence/2026-10-05-local.json). Podrobné místní výsledky jsou v ignorovaných `runtime/test-results.json` a `runtime/local-integration-results.json`.

Žádný výsledek neověřuje odpovědi skutečného GPT modelu. Zůstává neověřeno: kvalita Solu proti Astře/Luně, skutečná dostupnost účtu a API, tokenová cena a zátěž se zapnutou AI.

## Testovací prostředí a úklid

WordPress 7.1.2 a MariaDB 11.4.9 byly znovu připraveny z místně rozbalených runtime souborů. `tests/local-integration.py` vyžaduje dosud neexistující cílovou složku, vytvoří dvě nové syntetické databáze, vygeneruje pouze místní přístupy a blokuje externí HTTP i e-mail. WordPress a DB poslouchají jen na 127.0.0.1. Prohlížeč navíc blokuje jiné originy, včetně případných odkazů WordPressu na Gravatar.

Na Windows byla použita krátká cílová cesta `C:/Users/mihu0334/.cache/hlasuj-integration-20261005-c`. Předchozí dvě pokusná prostředí zůstávají lokálně: první běh narazil na očekávání kódu oznámení WordPressu po první publikaci; druhý na nevhodné použití Playwright `isDisabled` přímo na fieldsetu. Test nyní kontroluje dostupnost tlačítka a serverové nastavení panelu. Aplikační kód kvůli těmto dvěma podmínkám nebylo nutné měnit. Po každém běhu byly oba servery ukončeny. Lokální konfigurace, syntetické databáze a logy nejsou součástí GitHubu ani distribucí.

## Návrat a další krok

Projektové modelové nastavení lze vrátit odstraněním dvou přidaných `.codex/config.toml`; tím opět platí případné nadřazené/uživatelské nastavení. Modelové nastavení samo nevrací již provedené změny kódu.

AI je nyní vypnutá; nebyl nastaven skutečný klíč, spuštěn placený požadavek, změněno produkční schéma ani provedeno nasazení. Nový kód patří do vývojové větve GitHubu, není kvalifikovaným vydáním 0.8.7. Produkční verzi stále dokládá DEPLOYMENT-2026-10-02.md.

Následuje zátěžové ověření na očekávaném počtu studentů, kontrola cílového stagingu a až po samostatném povolení skutečná API evaluace. Volba Solu pro vývoj je nastavena; placené porovnání modelů zůstává podle přání uživatele neprovedené.
