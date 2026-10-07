# Hlasuj! a centrální REQUIREMENTS

Stav 7. 10. 2026. REQUIREMENTS je autoritou požadavků, rozhodnutí, revizí a evidence. GitHub a zdejší dokumentace jsou sekundární kopie a implementační podklady. Aktuální provozní verze jiných služeb určuje samostatný Registr služeb.

## Načtený podklad

Z chatu **REQUIREMENTS** (`01a10c57-9b1d-7cb0-b009-191bb991fb34`) byl dohledán aktivní projekt a jeho dokumentovaný Private API v1 kontrakt. Export `registry/requirements.json` baseline **1.5.0**, datum **2026-10-07**, byl přečten bez úprav. SHA-256 jeho bajtů odpovídá položce v manifestu REQUIREMENTS:

`b2a1388394f1161eb1d0afef63480f12f4c295425a4f1d065a5a00bf6e97f351`

Pro službu `hlasuj` obsahuje 168 položek: 149 mají approval_status=unknown, 19 approved. Původní požadavky HLS-001..075 a rozhodnutí DEC-001..019 jsou zachované. Další položky zahrnují zdrojové sekce specifikace; jejich počet se nesmí vydávat za počet samostatně ověřených funkcí. Importovaná tvrzení o dřívějším dokončení nejsou aktuální testy. Zdrojové varianty nejsou automaticky schválené nové revize.

Živé anonymní GET na `/api/v1/context/HLASUJ`, `/api/v1/entries` a `/api/v1/baselines` vrátily HTTP 401. Kontrakt používá identifikátor služby `hlasuj` malými písmeny. Strojový credential nebyl poskytnut; živý autentizovaný kontext ani centrální zápis nebyly ověřeny. Export neprokazuje, že se provozní baseline od posledního nasazení nezměnila.

## Implementovaný adaptér

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

Načtený vydaný kontrakt v1 verification přijímá `passed/failed/inconclusive`. Podle posledního čtení chatu REQUIREMENTS se centrální životní cyklus a evidence PASS/FAIL/ERROR/SKIP vyvíjejí jako nová major verze **Private API v2**, při zachování v1 pro dosavadní klienty. Místní rozpracovaný kód v2 není dokladem dodaného produkčního kontraktu. Tento adaptér záměrně zůstává kompatibilním čtenářem v1; přechod na v2 vyžaduje doručený kontrakt, credential a vlastní integrační test. ERROR a SKIP nesmějí být tiše převáděny na PASS ani sloučeny bez původního výsledku.

Do doručení nového kontraktu a omezeného credentialu se sanitizovaná evidence uchovává místně a jako sekundární testovací dokumentace v GitHubu. Zpráva musí uvádět `central_submission=pending`, ID a revizi, přesný kód/hash, prostředí a skutečný výsledek. Žádný lokální report nesmí tvrdit automatické verified v centrále.

Návrhy nové specifikace patří do Proposal Inboxu. AI nepoužívá reviewer/admin credential a přímo nepřepisuje schválené definice. Hlasuj! zde nevytváří vlastní správu hesel, licence, entitlementy ani kopii autoritativní databáze REQUIREMENTS.

## Ověření

`python tests/requirements_context.py`: syntetické testy kompletního stránkování, změny baseline, duplicit, cursorů, typů, pinningu exportu, zachování unknown, odstranění interních polí, credentialů, zákazu přesměrování, umístění a atomického ukládání. Testy nevysílají síťové požadavky. Úspěch fixtures se nevydává za živé API připojení.
