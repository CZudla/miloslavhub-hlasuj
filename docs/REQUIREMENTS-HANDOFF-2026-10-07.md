# Předání centrálního workflow Hlasuj!

Stav 7. 10. 2026, zpětně ověřeno v **17:00:41 UTC**. Autoritou je REQUIREMENTS Private API v2; místní specifikace a GitHub jsou sekundární. Všech **27 záznamů bylo doručeno a ověřeno zpětným čtením**, bez lidského schválení či aplikace změn. Sanitizované receipty a vazby jsou v [evidenci doručení](test-evidence/2026-10-07-requirements-delivery.json).

## Kontext a dostupnost

- Autentizované Private API v2 a jeho OpenAPI byly úspěšně načteny. Kontext HLASUJ má baseline **1.6.0**, SHA-256 `aa53c6696d6c5dc83e596bc397ecba722a8c2b1b44abfa8e958e673be4ad2bcf`: **168 položek ve 2 stránkách**, z toho 149 unknown a 19 approved. Režim open byl také načten kompletně: **149 položek ve 2 stránkách**.
- Všech 168 položek bylo porovnáno s referenční baseline 1.5.0: text, akceptace, revize a approval status jsou shodné. Revize připravených operací zůstaly platné. Konkrétní `central_generation` kontext nevrací; její hodnota zůstává neznámá.
- Dřívější pokus v 15:58:45 UTC vrátil **HTTP 404**, tehdy bez strojového credentialu. Tato historie zůstává zachována; pozdější autentizované čtení a zápis uspěly.
- Získáno **27 API receiptů**, jejich těla ověřena přesnou shodou se vzdálenými záznamy. Stav 3 návrhů je pending a 24 implementačních/testových zpráv submitted. Všechny mají `authoritative_applied=false`. Neproběhlo review/apply/publish/revoke.
- Čtečka `scripts/requirements_context.py` zachovává starší v1 profil. Nepovažovat její v1 či offline výstup za živou v2 autoritu.

## Chráněná fronta

Outbox je v lokálním profilu aplikace mimo Git a OneDrive, logicky `%LOCALAPPDATA%/MiloslavHub/requirements/hlasuj/outbox.sqlite3`. Na tomto počítači může Windows přeložit AppData do fyzické cesty Codex LocalCache. Skutečná cesta je uchována pouze v ignorovaném `runtime/requirements/outbox-location.json`. ACL trvalé DB a provenance byl ověřen: pouze aktuální uživatel a SYSTEM. Databáze neobsahuje tokeny.

Použit je klient z nainstalované dovednosti, zkontrolovaný a místně připnutý podle SHA-256:

`b468dab72e123a52135f228b738a8046496edfb0222c0ac952601667cbc310c8`

Přesné bajty klienta jsou uložené vedle fronty pod adresářem podle tohoto hashe; aktualizace dovednosti je nepřepíše. Klient nemá operace pro lidské review, apply ani publication. Queue používá SQLite, stabilní idempotency key, digest těla a kontrolu receipt. Opakované místní zachycení stejných záznamů nezvýšilo jejich počet.

| Druh | Počet | Stav |
|---|---:|---|
| Návrhy změn s chat provenance | 3 | sent; vzdáleně pending |
| Implementační vazby | 6 | sent; vzdáleně submitted |
| Výsledky testů | 18 | sent: 16 passed, 2 skipped; vzdáleně submitted |
| Celkem | **27** | **27 sent, 0 pending, 0 blocked** v lokální frontě |

Původní fronta obsahovala 26 operací. Osmnáctý testový záznam navíc zachycuje skutečně úspěšné živé ověření v2 pro `HLS-067` rev. 1. Oddělené read/propose a read/verify credentials byly použity pouze přes `--credential-file`. Odesílací fronty zachovaly původní ID, přesná těla a digesty; jejich stavy a receipty byly zapsány zpět do hlavní trvalé fronty. Před doručením vznikla chráněná SQLite záloha. Hodnoty credentials nejsou v Git, dokumentaci ani balíčcích.

### Návrhy k lidskému posouzení

Všechny se vztahují ke skutečným existujícím klíčům, ověřeným v živém kontextu, revize **1**:

- `hlasuj:requirement:HLS-067`: centrální workflow podle nové projektové instrukce, provenance, chráněný outbox a skutečná evidence.
- `hlasuj:requirement:HLS-025`: pedagogický jazyk domácího zadání, samostatný postup bez live řízení, pokročilé volby v „Další nastavení“.
- `hlasuj:requirement:HLS-073`: autorský a zákaznický balíček s dokumentací/manuály/referencí/marketingem, manifesty a vyloučením citlivých podkladů.

| Klíč | Proposal receipt | Vzdálený stav |
|---|---|---|
| HLS-067 rev. 1 | `77a731b5341d6c887f7b2b0b0f78432a` | pending |
| HLS-025 rev. 1 | `f38683918f8f6f9218ba5046eb112620` | pending |
| HLS-073 rev. 1 | `08a35e441883216093d4608003e301ea` | pending |

Provenance obsahuje channel `codex`, referenci tohoto chatu, skutečný scoped excerpt a jeho SHA-256. Protože platformový identifikátor původní zprávy není dostupný, `message_ref` je výslovně obsahová reference `content-sha256:...`; nevymýšlí se zprávové ID. Úplný chat se nekopíruje. Lidské zadání se nepovyšuje na centrální schválení.

### Implementace a úspěšné testy

Implementační commit: `46449d0390ae8b9e46704ecc0853ccce3a5f5745`; [draft PR #1](https://github.com/CZudla/miloslavhub-hlasuj/pull/1). Dokumentační následné commity nemění testovaný aplikační kód.

Vazby jsou pro `HLS-022`, `HLS-023`, `HLS-026`, `HLS-028`, `HLS-029`, `HLS-062`, vždy rev. 1. Lokální rozsah je implemented u vysvětlení/poznámky a partial u ostatních čtyř. Jde o předkládané nároky; autoritativní status zůstává beze změny. Release a deployment reference jsou null, protože tato etapa je nevydaná.

Patnáct záznamů passed rozděluje skutečné serverové, administrační, frontendové, obsahové, bezpečnostní a souběhové testy podle dotčených klíčů. Dva skipped zachycují neprovedenou hostingovou kapacitu (`HLS-062`) a produkční nasazení této etapy (`HLS-065`). Reference je bezpečná relativní cesta `docs/test-evidence/2026-10-07-feedback.json`. Passed se nevydává za úplnou akceptaci širokého dílčího požadavku.

### Starší selhání a chybějící metadata

Neúspěšné testy z dřívějších necommitnutých variant zůstávají v chráněném `historical-test-observations.json` i v sanitizované testovací zprávě. Nejsou připsány finálnímu opravenému commitu. Chybějící přesný commit/timestamp se nevymýšlí; tyto záznamy čekají na doplnění identifikace nebo vhodný podporovaný formát centrální evidence.

Samostatné ověření připnutého klienta mělo nejprve ERROR v nové testovací fixture: druhé SQLite připojení nebylo explicitně uzavřeno a Windows odmítl úklid dočasného souboru. Po opravě prošlo **6 syntetických unittest metod**: trvalé znovuotevření fronty, stejný idempotency key/tělo při retry, chybný receipt, porušený digest, zákaz admin/secrets/synchronizovaných cest a přesměrování. Nejde o test živého API. Tato první chyba je zachována zvlášť; její fixture není součástí aplikačního commitu.

## Provedené doručení a další práce

1. Dokončeno: autentizované v2 OpenAPI, kompletní kontext all/open, kontrola revizí, schématu a digestů připravených těl.
2. Dokončeno: doručení připnutým klientem s oddělenými credentials a původními idempotency keys; 27 sent, 0 pending/blocked. Nedocházelo k automatickému přepisování revizí.
3. Dokončeno: zpětné čtení všech 27 záznamů a přesná shoda těl, uchování receiptů a sanitizované evidence. Receipt s pending/submitted není schválení, aplikace ani verified.
4. Zbývá lidské posouzení návrhů a evidence v REQUIREMENTS. Obyčejná AI nevolá review/apply/publish/revoke.
5. Zbývá vhodné centrální zachycení starších selhání s chybějícími metadaty. Místní záznamy a fronty uchovat mimo zákaznické balíčky. Před další implementací znovu načíst aktuální kontext; provozní verzi Hlasuj! toto doručení nemění.

Tento workflow pokrývá tento zapojený chat a klienty. Netvrdí univerzální pozorování všech externích konverzací ani provozní verze jiných subsystémů; ty určuje samostatný Registr služeb.
