# Hlasuj! a centrální REQUIREMENTS

Stav 7. 10. 2026. REQUIREMENTS je autoritou požadavků, rozhodnutí, revizí a evidence. GitHub a zdejší dokumentace jsou sekundární kopie a implementační podklady. Aktuální provozní verze jiných služeb určuje samostatný Registr služeb.

## Aktualizace podle nových projektových instrukcí

Primárním integračním kontraktem je **Private API v2** a povinný postup určuje dovednost `miloslavhub-requirements`. Byly načteny její SKILL.md, INTEGRATION-GUIDE.md a CHAT-CAPTURE.md. GET `/api/v2/context/hlasuj` s úplnými selektory dne 7. 10. 2026 vrátil **HTTP 404**. Strojový credential rovněž není dostupný; živá dostupnost v2 ani aktuální generation nejsou potvrzené.

Zachycení nových zadání a evidence proto používá chráněný lokální outbox mimo Git a OneDrive. Je připraveno **26 pending operací: 3 návrhy, 6 implementačních vazeb a 17 testových záznamů (15 passed, 2 skipped)**. API receiptů je 0. Starší neúspěchy z necommitnutých variant jsou zachované zvlášť s chybějícím commit/timestamp, které se nesmějí vymýšlet. Podrobný stav a pravidla následného odeslání: [REQUIREMENTS-HANDOFF-2026-10-07.md](REQUIREMENTS-HANDOFF-2026-10-07.md).

## Načtený podklad

Z chatu **REQUIREMENTS** (`01a10c57-9b1d-7cb0-b009-191bb991fb34`) byl dohledán aktivní projekt a jeho dokumentovaný Private API v1 kontrakt. Export `registry/requirements.json` baseline **1.5.0**, datum **2026-10-07**, byl přečten bez úprav. SHA-256 jeho bajtů odpovídá položce v manifestu REQUIREMENTS:

`b2a1388394f1161eb1d0afef63480f12f4c295425a4f1d065a5a00bf6e97f351`

Pro službu `hlasuj` obsahuje 168 položek: 149 mají approval_status=unknown, 19 approved. Původní požadavky HLS-001..075 a rozhodnutí DEC-001..019 jsou zachované. Další položky zahrnují zdrojové sekce specifikace; jejich počet se nesmí vydávat za počet samostatně ověřených funkcí. Importovaná tvrzení o dřívějším dokončení nejsou aktuální testy. Zdrojové varianty nejsou automaticky schválené nové revize.

Živé anonymní GET na `/api/v1/context/HLASUJ`, `/api/v1/entries` a `/api/v1/baselines` vrátily HTTP 401. Kontrakt používá identifikátor služby `hlasuj` malými písmeny. Strojový credential nebyl poskytnut; živý autentizovaný kontext ani centrální zápis nebyly ověřeny. Export neprokazuje, že se provozní baseline od posledního nasazení nezměnila.

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

Načtený vydaný kontrakt v1 verification přijímá `passed/failed/inconclusive`. Nový dokumentovaný v2 přijímá `passed/failed/error/skipped/inconclusive` a implementační vazby. Místní rozpracovaný kód v2 není dokladem dostupnosti na produkční URL. Zdejší `requirements_context.py` zůstává čtenářem staršího v1 profilu; nepoužívá se jako živá centrální autorita pro nový workflow. V2 operace jsou připraveny přes připnutého klienta z dovednosti. ERROR a SKIP se nepřevádějí na PASS.

Do zprovoznění v2 a omezeného credentialu zůstávají operace v chráněném outboxu. Sanitizovaná evidence v GitHubu je druhotný doklad. Zpráva uvádí `central_submission=pending`, ID/revizi, přesný kód/hash, prostředí a skutečný výsledek. Žádný lokální report netvrdí automatické verified v centrále. Novější revize se musí před odesláním znovu posoudit; HTTP 412 neopravuje automatický retry.

Návrhy nové specifikace patří do Proposal Inboxu. AI nepoužívá reviewer/admin credential a přímo nepřepisuje schválené definice. Hlasuj! zde nevytváří vlastní správu hesel, licence, entitlementy ani kopii autoritativní databáze REQUIREMENTS.

## Ověření

`python tests/requirements_context.py`: syntetické testy kompletního stránkování, změny baseline, duplicit, cursorů, typů, pinningu exportu, zachování unknown, odstranění interních polí, credentialů, zákazu přesměrování, umístění a atomického ukládání. Testy nevysílají síťové požadavky. Úspěch fixtures se nevydává za živé API připojení.
