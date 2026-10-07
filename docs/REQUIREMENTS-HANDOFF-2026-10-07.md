# Předání centrálního workflow Hlasuj!

Stav 7. 10. 2026 po načtení nových projektových instrukcí a dovednosti `miloslavhub-requirements`. Autoritou je REQUIREMENTS Private API v2; místní specifikace a GitHub jsou sekundární. Níže uvedené fronty nemění schválení, revizi ani ověření v centrále.

## Kontext a dostupnost

- Referenční export: baseline **1.5.0**, 7. 10. 2026, SHA-256 `b2a1388394f1161eb1d0afef63480f12f4c295425a4f1d065a5a00bf6e97f351`. Neprokazuje aktuální centrální generation.
- GET `/api/v2/context/hlasuj?mode=all&include=requirements,decisions,global,relationships,conflicts,baselines&limit=100` v 15:58:45 UTC vrátil **HTTP 404**. Odpověď nebyla uložena jako kontext. Strojový credential také chybí; absence credentialu sama nevysvětluje HTTP 404.
- Poslední úspěšné načtení živého v2 kontextu: **žádné**. Aktuální generation: **neznámá**. Získané API receipty: **0**. Neproběhlo review/apply/publish/revoke.
- Čtečka `scripts/requirements_context.py` zachovává starší v1 profil. Nepovažovat její v1 či offline výstup za živou v2 autoritu.

## Chráněná fronta

Outbox je v lokálním profilu aplikace mimo Git a OneDrive, logicky `%LOCALAPPDATA%/MiloslavHub/requirements/hlasuj/outbox.sqlite3`. Na tomto počítači může Windows přeložit AppData do fyzické cesty Codex LocalCache. Skutečná cesta je uchována pouze v ignorovaném `runtime/requirements/outbox-location.json`. ACL trvalé DB a provenance byl ověřen: pouze aktuální uživatel a SYSTEM. Databáze neobsahuje tokeny.

Použit je klient z nainstalované dovednosti, zkontrolovaný a místně připnutý podle SHA-256:

`b468dab72e123a52135f228b738a8046496edfb0222c0ac952601667cbc310c8`

Přesné bajty klienta jsou uložené vedle fronty pod adresářem podle tohoto hashe; aktualizace dovednosti je nepřepíše. Klient nemá operace pro lidské review, apply ani publication. Queue používá SQLite, stabilní idempotency key, digest těla a kontrolu receipt. Opakované místní zachycení stejných záznamů nezvýšilo jejich počet.

| Druh | Počet | Stav |
|---|---:|---|
| Návrhy změn s chat provenance | 3 | pending, bez proposal receipt |
| Implementační vazby | 6 | pending, bez receipt |
| Výsledky testů | 17 | pending: 15 passed, 2 skipped |
| Celkem | **26** | 0 sent, 0 blocked |

### Návrhy k lidskému posouzení

Všechny se vztahují ke skutečným existujícím klíčům z datované kopie, revize **1**:

- `hlasuj:requirement:HLS-067`: centrální workflow podle nové projektové instrukce, provenance, chráněný outbox a skutečná evidence.
- `hlasuj:requirement:HLS-025`: pedagogický jazyk domácího zadání, samostatný postup bez live řízení, pokročilé volby v „Další nastavení“.
- `hlasuj:requirement:HLS-073`: autorský a zákaznický balíček s dokumentací/manuály/referencí/marketingem, manifesty a vyloučením citlivých podkladů.

Provenance obsahuje channel `codex`, referenci tohoto chatu, skutečný scoped excerpt a jeho SHA-256. Protože platformový identifikátor původní zprávy není dostupný, `message_ref` je výslovně obsahová reference `content-sha256:...`; nevymýšlí se zprávové ID. Úplný chat se nekopíruje. Lidské zadání se nepovyšuje na centrální schválení.

### Implementace a úspěšné testy

Implementační commit: `46449d0390ae8b9e46704ecc0853ccce3a5f5745`; [draft PR #1](https://github.com/CZudla/miloslavhub-hlasuj/pull/1). Dokumentační následné commity nemění testovaný aplikační kód.

Vazby jsou pro `HLS-022`, `HLS-023`, `HLS-026`, `HLS-028`, `HLS-029`, `HLS-062`, vždy rev. 1. Lokální rozsah je implemented u vysvětlení/poznámky a partial u ostatních čtyř. Jde o předkládané nároky; autoritativní status zůstává beze změny. Release a deployment reference jsou null, protože tato etapa je nevydaná.

Patnáct záznamů passed rozděluje skutečné serverové, administrační, frontendové, obsahové, bezpečnostní a souběhové testy podle dotčených klíčů. Dva skipped zachycují neprovedenou hostingovou kapacitu (`HLS-062`) a produkční nasazení této etapy (`HLS-065`). Reference je bezpečná relativní cesta `docs/test-evidence/2026-10-07-feedback.json`. Passed se nevydává za úplnou akceptaci širokého dílčího požadavku.

### Starší selhání a chybějící metadata

Neúspěšné testy z dřívějších necommitnutých variant zůstávají v chráněném `historical-test-observations.json` i v sanitizované testovací zprávě. Nejsou připsány finálnímu opravenému commitu. Chybějící přesný commit/timestamp se nevymýšlí; tyto záznamy čekají na doplnění identifikace nebo vhodný podporovaný formát centrální evidence.

Samostatné ověření připnutého klienta mělo nejprve ERROR v nové testovací fixture: druhé SQLite připojení nebylo explicitně uzavřeno a Windows odmítl úklid dočasného souboru. Po opravě prošlo **6 syntetických unittest metod**: trvalé znovuotevření fronty, stejný idempotency key/tělo při retry, chybný receipt, porušený digest, zákaz admin/secrets/synchronizovaných cest a přesměrování. Nejde o test živého API. Tato první chyba je zachována zvlášť; její fixture není součástí aplikačního commitu.

## Následné doručení

1. Ověřit dostupnost v2 a získat credential omezený na `hlasuj` s příslušným read/propose/verify. Scopes nejsou dědičné; read token nesmí být použit k zápisu. Žádný lidský admin credential.
2. Načíst všechny stránky kontextu, otevřené položky, generation a revize. Posoudit soulad pending těla s aktuální revizí; 412 není důvod pro slepé přepisování či retry.
3. Odeslat odpovídající operace pomocí připnutého klienta; zachovat existující idempotency key a přesné tělo. Při oddělených propose/verify credentials rozdělit odesílání tak, aby žádný token neposílal operaci mimo svůj scope.
4. Ověřit a uchovat serverové receipt a stavy. Receipt s pending/submitted není schválení, aplikace ani verified. Obyčejná AI nevolá review/apply/publish/revoke.
5. Aktualizovat sanitizované předání o skutečné doručení a zbývající frontu. Selhání zachovat; frontu nemažte při úklidu ani ji nepřidávejte do zákaznických balíčků.

Tento workflow pokrývá tento zapojený chat a klienty. Netvrdí univerzální pozorování všech externích konverzací ani provozní verze jiných subsystémů; ty určuje samostatný Registr služeb.
