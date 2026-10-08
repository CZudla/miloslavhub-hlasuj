# Hlasuj! — neutrální ankety, 8. 10. 2026

## Výsledek a rozsah

Navazuje na nevydanou větev `feature/requirements-feedback`, výchozí commit `a0ad3cb626f430e205eb65feee9b04e21cc9383b`. Kvalifikované metadata zůstávají 0.8.9, schéma 0.8.5. Tato etapa není release, produkční nasazení ani hotový celý systém.

Centrální kontext načten autentizovaně z REQUIREMENTS Private API v2, včetně `/me`, všech 168 položek all a 149 open, obojí ve dvou stránkách. Baseline 1.6.0, snapshot SHA-256 `aa53c6696d6c5dc83e596bc397ecba722a8c2b1b44abfa8e958e673be4ad2bcf`. Dotčený `hlasuj:requirement:HLS-018` rev. 1 má approval_status unknown; místní implementace toto schválení nemění. Pokračování v chatu navazuje na stávající zadání, nevytváří další odlišnou definici požadavku.

## Implementováno

- REST ignoruje historické body za účast: nové anketní hlasy live/test/async mají nula bodů, null correctness a prázdnou přezdívku. Ani poslaná přezdívka se do hlasu neukládá.
- Anketa nevyžaduje přezdívku při vstupu do konkrétní otázky a používá session-scoped klíč pro hlas i vlastní výsledek. Obecný vstup do soutěžní přednášky zůstává samostatný scénář.
- Editor odstranil volbu bodů za účast. Starší metadata jsou inertní a zachovaná pro obsahový round trip v1/v2.
- Student ani projekce nezvýrazňují anketní správnost, body či pořadí otázky; vlastní odpověď neukazuje soutěžní čas ani nabídku Síně slávy. UI se brání i neočekávaným starým soutěžním polím. Celkové pořadí výslovně pochází z předchozích soutěžních otázek.
- Testovací laboratoř vytváří neutrální anketní simulace. Vnitřní operace je samostatně ověřitelná, kontroluje oprávnění i příslušnost/stav/termín testovací relace a používá původní transakci se zámkem. HTTP nonce zůstává zachovaný. Kvízové bodování se nemění.
- Aktualizován frontend manifest, API/privacy dokumentace, obsahový formát a manuál učitele. Přidána [příručka anket](customer/12-ANKETY.md) a [technická reference](NEUTRAL-POLLS-REFERENCE.md).

## Ověření a zachované pokusy

Přesné výsledky, časy a hashe jsou v [testovací evidenci](test-evidence/2026-10-08-poll.json). Testy používají syntetická data, WordPress 7.1.2, MariaDB 11.4.9, PHP 8.4.25 a Edge/Playwright. Placená AI volání: 0.

První nová browser fixture čekala na neexistující `#app` místo `#mhl-app` a skončila ERROR s timeoutem. Oprava selektoru prošla. Původní chyba je uchovaná s přesným hashem necommitnutých vstupů; commit se nevymýšlí a chyba se nepřipisuje finální opravě. Kvůli povinnému commit v centrálním VerificationEvidence tento historický záznam čeká v chráněném úložišti na vhodný formát zachycení.

Mezilehlá regrese a integrace prošly, poté závěrečná kontrola našla druhou cestu starého bodování v simulaci Testovací laboratoře. Po opravě a doplnění testů obě závěrečné sady prošly: **200 regresních kontrol, 292 integračních kontrol a 15 unittest metod referenčního adaptéru**. Z toho 60 serverových/DB kontrol anket, 8 frontendových kontrol neutrálního zobrazení a 10 kontrol editoru. Oba běhy mají shodný hash `152356a12e733c9f9bf4b22f6f87fd5d1e745774b65400e5520406f29bb71257`. Mezilehlé výsledky zůstávají místně zachované a nevydávají se za test konečného kódu.

## GitHub a centrální evidence

Zdrojový commit `6e19e9725dd2bd6358d9ab5836e919475e1d5f9b` je v [draft PR #1](https://github.com/CZudla/miloslavhub-hlasuj/pull/1). Kontrola 18 zamýšlených souborů na známé produkční hodnoty a tokenové vzory měla 0 nálezů; samostatně se porovnaly i scoped credentials REQUIREMENTS. Jde o omezený scan, nikoli úplnou záruku správy tajemství.

Do REQUIREMENTS bylo doručeno a přesně zpětně ověřeno **8 nových záznamů**: implementační zpráva HLS-018 rev. 1, 5 passed důkazů HLS-018 rev. 1 a po jednom skipped pro hostingovou kapacitu HLS-062 rev. 1 a produkční nasazení HLS-065 rev. 1. Vzdálený stav je submitted a `authoritative_applied=false`. Review/apply/publish/revoke nebylo voláno. [Receipty a vazby](test-evidence/2026-10-08-poll-delivery.json).

Trvalá chráněná fronta má včetně předchozí etapy **35 sent, 0 pending, 0 blocked**. Počáteční necommitnutý fixture ERROR je zachován zvlášť s přiznaným chybějícím commit, čeká na vhodný centrální formát a není vydáván za doručený testový záznam. Pozdější dokumentační commit nemění testované zdroje.

## Kompatibilita a zbývající práce

Bez SQL migrace, přepočtu historie nebo nasazení Hlasuj!. Historické globálně propojené bodované ankety zůstávají v DB a celkových součtech. Vlastní odpověď staré ankety nemusí být dohledatelná novým klíčem relace; souhrn hlasů zůstává. Změna typu otázky během běhu stále používá aktuální metadata, bez snapshotu. Případné změny historie vyžadují samostatné rozhodnutí a zálohu.

Aktuální zadání vyřazuje domácí úkoly z této iterace. Následná místní implementace učitelů/organizací a cs/en je popsána v [referenci](ORGANIZATIONS-I18N-REFERENCE.md) a samostatné testové evidenci. Potvrzené pravidlo licencí počítá každý účet s právy k výuce jednou za celou organizaci, bez studentů a účtů pouze pro čtení. Živé AUTH/licence ještě vyžadují kvalifikaci navazujících služeb. Nové zákaznické/autorské ZIPy a produkční kvalifikace neutrálních anket tímto historickým záznamem nevznikají. Před produkcí je nutná ověřená společná záloha obou DB, souborů, konfigurace a médií, restore a rollback.

Testovací servery se ukončují ve finally. Syntetická prostředí `C:/mhl-poll-20261008` a `C:/mhl-poll-20261008b` zůstávají mimo Git a balíčky; dřívější odmítnutí automatického odstranění testovacích adresářů se neobchází. Uživatelské SSO soubory zůstávají nedotčené.
