# Hlasuj! a centrální REQUIREMENTS

## Aktualizace 8. 10. 2026 — organizace a jazyky

Aktuální vývojový stav a publikaci zdrojů popisuje [zpráva organizací a jazyků](DEVELOPMENT-ORGANIZATIONS-I18N-2026-10-08.md). Private API v2 potvrdilo baseline 1.6.0 se stejným snapshotem a 168 položkami all ve dvou stránkách. Dva nové návrhy rozsahu a pravidla míst mají receipty, čekají na lidské zpracování a `authoritative_applied=false`.

Bylo přijato a přesně zpětně ověřeno **24 implementačních/testových operací**: 9 implementačních zpráv a 15 testových záznamů (7 passed / 5 skipped / 3 error). Celkový outbox: **61 sent / 0 pending / 13 historických blocked**; aktuální efektivní sada **24 sent / 0 pending / 0 blocked**. [Sanitizovaná evidence](test-evidence/2026-10-08-organizations-i18n-delivery.json) obsahuje ID, receipty, revize, commity a potvrzení přesné shody těl.

Zpočátku zápisy vracely HTTP 503 `snapshot_unavailable`, testové zápisy také HTTP 400 kvůli absolutnímu `artifact_ref`. Tři pozdější záznamy byly odmítnuty také kvůli environment delšímu než 200 UTF-8 bajtů. Opravené náhrady používají bezpečnou relativní cestu a stručné environment; plná diagnóza zůstává v artefaktu. Původní odmítnuté řádky zůstávají nezměněné. Po obnovení zápisu byly přijaty všechny aktuální položky. Doručení nemění schválení ani autoritativní stav požadavků.

Vazby zahrnují HLS-032, 033, 034, 035, 045, 046, 067 a 073 rev. 1; doplňující testy také HLS-062 a 065. AUTH, licence, hostingová kvalifikace, produkce a finální balíčky mají skutečný stav skipped s důvodem. Historické chybové varianty bez zachovaného commitu zůstávají chráněnými pozorováními; nevymýšlí se commit ani centrální receipt. Následující text zachovává historický stav ze 7. 10. 2026.

Stav 7. 10. 2026. REQUIREMENTS je autoritou požadavků, rozhodnutí, revizí a evidence. GitHub a zdejší dokumentace jsou sekundární kopie a implementační podklady. Aktuální provozní verze jiných služeb určuje samostatný Registr služeb.

## Aktualizace podle nových projektových instrukcí

Primárním integračním kontraktem je **Private API v2** a povinný postup určuje dovednost `miloslavhub-requirements`. Byly načteny její SKILL.md, INTEGRATION-GUIDE.md a CHAT-CAPTURE.md. Pozdější autentizované čtení dne 7. 10. 2026 potvrdilo živé OpenAPI 2.0.0 a kontext baseline **1.6.0**, SHA-256 `aa53c6696d6c5dc83e596bc397ecba722a8c2b1b44abfa8e958e673be4ad2bcf`. Načteny všechny stránky all (168 položek / 2 stránky) i open (149 / 2). Pole `central_generation` odpověď nevrací; jeho hodnota zůstává neznámá. Předchozí HTTP 404 zůstává historickým pokusem.

Zachycení nových zadání a evidence používá chráněný lokální outbox mimo Git a OneDrive. Doručeno a zpětně ověřeno je **27 operací: 3 návrhy, 6 implementačních vazeb a 18 testových záznamů (16 passed, 2 skipped)**. Původních 26 položek doplnil skutečný výsledek živého integračního ověření. Lokální fronta: **27 sent, 0 pending, 0 blocked**. Vzdáleně čekají návrhy na posouzení a zprávy mají stav submitted; nic nebylo automaticky schváleno ani aplikováno. Starší neúspěchy z necommitnutých variant zůstávají zachované zvlášť s chybějícím commit/timestamp, které se nesmějí vymýšlet. Podrobnosti a receipty: [REQUIREMENTS-HANDOFF-2026-10-07.md](REQUIREMENTS-HANDOFF-2026-10-07.md).

## Původní referenční podklad a historie

Z chatu **REQUIREMENTS** (`01a10c57-9b1d-7cb0-b009-191bb991fb34`) byl dohledán aktivní projekt a jeho dokumentovaný Private API v1 kontrakt. Export `registry/requirements.json` baseline **1.5.0**, datum **2026-10-07**, byl přečten bez úprav. SHA-256 jeho bajtů odpovídá položce v manifestu REQUIREMENTS:

`b2a1388394f1161eb1d0afef63480f12f4c295425a4f1d065a5a00bf6e97f351`

Pro službu `hlasuj` obsahuje 168 položek: 149 mají approval_status=unknown, 19 approved. Původní požadavky HLS-001..075 a rozhodnutí DEC-001..019 jsou zachované. Další položky zahrnují zdrojové sekce specifikace; jejich počet se nesmí vydávat za počet samostatně ověřených funkcí. Importovaná tvrzení o dřívějším dokončení nejsou aktuální testy. Zdrojové varianty nejsou automaticky schválené nové revize.

Původní anonymní GET na `/api/v1/context/HLASUJ`, `/api/v1/entries` a `/api/v1/baselines` vrátily HTTP 401. Kontrakt používá identifikátor služby `hlasuj` malými písmeny. Při tomto prvním čtení nebyl credential dostupný. Následný živý v2 kontext uvedený výše tento referenční podklad aktualizoval: revize, text, akceptace a approval status všech 168 položek jsou shodné. Export sám živou dostupnost neprokazuje.

## Implementovaný v1 adaptér pro starší referenční profil

`scripts/requirements_context.py` čte `GET /api/v1/context/hlasuj?mode=all&include=requirements,decisions,baselines&limit=100`. Používá pouze HTTPS pevně určené centrály, Bearer z proměnné **MHL_REQUIREMENTS_READ_TOKEN**, systémové ověřování TLS, timeout a omezení velikosti. Přesměrování odmítá, aby credential nemohl opustit cíl. Vypisuje jen bezpečný souhrn. Všechny stránky musí mít stejnou verzi, digest a celkový počet; duplicitní klíče, opakované cursory či neúplné čtení se odmítnou.

```powershell
python scripts/requirements_context.py
```

Přístupový token nastavuje provozovatel v privátním prostředí. Nepatří do příkazové řádky, chatu, Git ani balíčku. Stroj nesmí používat lidský Basic/admin přístup.

Při nedostupnosti credentialu lze výslovně načíst existující export s digestem z jeho manifestu:

```powershell
python scripts/requirements_context.py --export C:/private/requirements/registry/requirements.json --sha256 SHA256_Z_MANIFESTU
```

Výstup je pouze v ignorovaném `runtime/requirements/context.json`. Zůstávají centrální key/ID/revision a nezměněné stavy. Obsahuje provenance, čas čtení a rozlišení `private-api-v1` / `pinned-release-export`. Lokální kopie není novou autoritou. Chyba nepřepíše předchozí soubor; starý soubor se tím nestává čerstvým kontextem. Adaptér neodhaduje globální applicability a nenačítá úplnou historii/varianty. Jeho omezení jsou ve výstupu výslovně uvedena.

## Evidence a zápisy

Načtený vydaný kontrakt v1 verification přijímá `passed/failed/inconclusive`. Živě ověřené v2 OpenAPI přijímá `passed/failed/error/skipped/inconclusive` a implementační vazby. Zdejší `requirements_context.py` zůstává čtenářem staršího v1 profilu; nepoužívá se jako živá centrální autorita pro nový workflow. V2 operace byly doručeny připnutým klientem z dovednosti. ERROR a SKIP se nepřevádějí na PASS.

Odesílání používá oddělené service-scoped read/propose a read/verify credentials z chráněných souborů přes `--credential-file`. Zachovává ID i přesná těla, kontroluje revizi, schéma, digest a serverový receipt. Zpětné čtení potvrdilo shodu všech 27 těl a `authoritative_applied=false`. Doručené řádky zůstávají v chráněné frontě jako evidence. Sanitizovaná evidence v GitHubu je druhotný doklad. Původní aplikační testovací zpráva zachovává svůj tehdejší `central_submission=pending`; aktuální doručení dokládá [samostatná evidence](test-evidence/2026-10-07-requirements-delivery.json). Žádný lokální report netvrdí automatické verified v centrále. Novější revize se musí před odesláním znovu posoudit; HTTP 412 neopravuje automatický retry.

Návrhy nové specifikace patří do Proposal Inboxu. AI nepoužívá reviewer/admin credential a přímo nepřepisuje schválené definice. Hlasuj! zde nevytváří vlastní správu hesel, licence, entitlementy ani kopii autoritativní databáze REQUIREMENTS.

## Ověření

`python tests/requirements_context.py`: syntetické testy kompletního stránkování, změny baseline, duplicit, cursorů, typů, pinningu exportu, zachování unknown, odstranění interních polí, credentialů, zákazu přesměrování, umístění a atomického ukládání. Testy nevysílají síťové požadavky. Úspěch fixtures se nevydává za živé API připojení.
