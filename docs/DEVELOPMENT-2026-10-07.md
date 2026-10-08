# Dokončení Hlasuj! podle centrálních požadavků

## Rozsah a výchozí stav

Přímé zadání vlastníka 7. 10. 2026: pokračovat v návrhu a implementaci celého Hlasuj! podle centrálních požadavků. Podklady byly dohledány v chatu REQUIREMENTS. Identita centrálního exportu a hranice přístupu jsou v [REQUIREMENTS-INTEGRATION.md](REQUIREMENTS-INTEGRATION.md). Tato zpráva je implementační plán a evidence; nemění schválení v centrále.

Výchozí kvalifikované vydání Hlasuj! je **0.8.9**, schéma **0.8.5**. Vývojová větev navazuje na `30e1b3de37f19300aa243788f014f0fab0f187ca`. Následující změny jsou **UNRELEASED**, nejsou novým stabilním vydáním ani dokladem nasazení. Dřívější ZIPy 0.8.9 a protokoly zůstávají historickými doklady.

## Implementováno v této etapě

- Načítací adaptér staršího referenčního v1 profilu: stránkování, kontrola baseline, bezpečný Bearer transport, alternativně manifestem připnutý export. Primární workflow používá živé Private API v2.
- Po přijetí nových instrukcí načtena dovednost `miloslavhub-requirements` a primární workflow v2. Po počátečním HTTP 404 uspělo autentizované čtení all/open, baseline 1.6.0, a odeslání chráněné fronty s oddělenými credentials. **27 sent, 0 pending, 0 blocked**, všechny receipty a přesná těla zpětně ověřeny. Z toho 26 původních operací a 1 výsledek skutečného živého ověření. Návrhy čekají na lidské posouzení; nebylo provedeno review/apply/publish/revoke. [Předání centrální evidence](REQUIREMENTS-HANDOFF-2026-10-07.md).
- HLS-022 rev. 1: editor vysvětlení, výchozí `teacher_only`, volby `show_after_close` a `hidden`. Veřejné výsledky mohou obsahovat vysvětlení pouze uzavřeného kvízu při explicitní volbě učitele. Student i projekce vykreslují text bezpečně.
- HLS-023 rev. 1: samostatná soukromá poznámka učitele. Chybí v public API a projekci; autorizovaný učitelský obsahový export ji přenáší.
- HLS-028/029 rev. 1, dílčí rozšíření: JSON v2 přenáší vysvětlení, pravidlo zobrazení a poznámku. V1 se stále importuje; chybějící vysvětlení má soukromý výchozí režim. Import zachovává náhled, koncepty a původní QR. Úplný ZIP s assets/výsledky tím není implementován.
- HLS-026/062 rev. 1, dílčí oprava: administrační QR se vykresluje místně. Odkazy se neposílají externímu QR generátoru. Přiložena stejná knihovna a MIT licence/provenance jako ve frontendu.

Metadata se ukládají do WordPress posts/meta. **Bez SQL migrace.** Teacher UI používá překladové funkce WordPressu. Kompletní cs/en obrazovky zůstávají samostatnou prací.

## Návrh cílové architektury a pořadí dokončení

Zachovat PHP/JS frontend, WordPress obsah a oddělenou hlasovací DB i demo. Nové scénáře oddělovat podle skutečného životního cyklu: výukový obsah, živé použití, domácí zadání, odevzdání a výsledky. Neměnit význam existujících QR ani historických hlasů.

| Etapa | Centrální ID (rev. 1) | Konkrétní práce a akceptace |
|---|---|---|
| Řízení a ochrana výsledků | HLS-005..013, 016..023, 060..062 | Jednoznačné start/close/skip/repeat/pause; společný předstart; anketa bez nových soutěžních bodů. Výslovná pravidla pro historické výsledky, veřejnou projekci a účastnické informace. Negativní REST a souběhové testy. |
| Učitelé a organizace | HLS-032..035, 040, 055; SSO dodatek | Lokální vlastnictví/ACL nad stabilní identitou AUTH, role a objektové guards. Identita nedává oprávnění; ENTITLEMENTS/Licence/Organizations mají vlastní autority. Dva učitelé a dvě organizace nesmí vidět cizí obsah. OIDC/MFA kontrakt a staging před produkčním přepnutím. |
| Domácí úkol | HLS-024/025 | Samostatné zadání jedné otázky nebo sady se snapshotem obsahu, termínem a pokusy. Teacher UI „Jak chcete otázku použít?“, „Ve výuce“, „Jako domácí úkol“, „Odevzdat do“. Student postupuje samostatně. Serverové vymáhání termínu/pokusů, explicitní feedback a privacy; bez závislosti na live flow. |
| Trvalé připojení a přenos | HLS-026..031 | Náhodný join alias a kompatibilita starých QR. Rozšíření exportu na verzovaný ZIP s whitelistem assets, limity a ochranou cest. Výsledky odděleně; anonymní/pseudonymní/identifikovaný export podle práv. Round trip a negativní testy. |
| Překlady a použitelnost | HLS-007/008, 017/021, 044..046, 050..053 | Dokončit celé obrazovky cs-CZ/en-US, jazyk UI oddělit od obsahu; klávesnice, focus, čtečka, kontrast, mobil a projekce. Omezené bezpečné šablony. Pilot s reálným učitelem je samostatná akceptace. |
| Propojení Hubu a API | HLS-035..040, 054..059, 071 | Připojit potvrzené služby pro licence/seaty/trial/referral, nikdy automatická platba či ztráta obsahu. Strojové API se scopes, verzí, limity a oddělenou dokumentací. Standalone SQLite je jiný deployment profil, ne přejmenovaný WordPress ZIP. |
| Volitelné moduly a AI | HLS-041..043, 047..049 | AI zůstává volitelná a schvalovaná učitelem. Žádné automatické odeslání studentských dat. Cards potřebuje samostatný lokální prototyp a reálnou kamerovou akceptaci bez ukládání obrazu/biometriky. Ostatní moduly nejsou hotové pouhým extension pointem. |
| Kvalifikace a předání | HLS-001..004, 014/015, 063..070, 072..075 | Synchronizovat značku, aktuální demo, licence, reference, manuály, autorský a zákaznický balíček, screenshoty a manifest. Evidence přesných testů, restore obou DB, záloha, konkrétní nasazení a smoke test. Cíl 20 aktivních učitelů / 5 platících zákazníků vyžaduje skutečný provoz; není to výsledek testovací fixture. |

Tato mapa nepřevádí položky unknown na approved a žádnou etapu neoznačuje za plně ověřenou. Úplné dokončení vyžaduje i akceptace závislých služeb, právní/licenční rozhodnutí a reálné piloty.

## Testy a neúspěchy

Závěrečný běh prošel: **192 regresních kontrol**, **230 integračních kontrol** a **15 unittest metod adaptéru REQUIREMENTS**. Regrese: 51 bezpečnostních, 93 AI kontraktů, 3 vypnuté AI, 18 demo HTTP, 15 frontendových a 12 AI prohlížečových kontrol. Integrace: 19 základních WordPress/DB, 21 AI, 50 obsahového přenosu, 80 vysvětlení/poznámek, 28 souběhu a 13 + 11 + 8 kontrol skutečné administrace v Edge. AI je simulovaná; placená volání 0. PHP/JS syntaxe prošla. Oba běhy mají shodný hash zdrojů `c4466300d540c4a885ad6290601d4031c2a3e326aae7c75df4a4df56f59abcc6`; během běhu se kontroluje neměnnost vstupů. Důkaz: [sanitizovaná evidence](test-evidence/2026-10-07-feedback.json). Podrobná reference: [FEEDBACK-REFERENCE.md](FEEDBACK-REFERENCE.md).

Předcházející neúspěchy zůstávají v evidenci: příliš široká unittest assertion, neúplná stará distribuce WordPressu, chybný typ nového fixture argumentu, skutečná chyba round trip vypnuté boolean volby a čekání prohlížečové assertion na skrytý pracovní canvas QR. Oprava boolean exportu rozlišuje uložené false od chybějícího nastavení. QR test ověřuje viditelný dekódovaný místní PNG. Závěrečné prostředí používá oficiální WordPress 7.1.2 s ověřeným součtem, MariaDB 11.4.9 a syntetická data; testovací servery byly ukončeny.

Tato etapa neopakovala produkční restore, hostingovou zátěž ani placenou AI evaluaci. Předchozí doklady vydání 0.8.9 se nevydávají za kvalifikaci nového kódu. Původní aplikační testovací zpráva uchovává tehdejší SKIP živého REQUIREMENTS a `central_submission=pending`. Následné skutečné čtení, doručení a zpětné ověření dokládá [samostatná evidence](test-evidence/2026-10-07-requirements-delivery.json). Historická zpráva se zpětně nepřepisuje na PASS.

## Publikace a úklid

Změny jsou připraveny ve větvi `feature/requirements-feedback` k revizi proti `develop`. Před publikací proběhla kontrola 39 zamýšlených textových souborů na shodu se známými produkčními credentials a obvyklé tokenové vzory; nález 0. Jde o omezený scan, nikoli záruku kompletní správy tajemství. Předem existující necommitnuté soubory `docs/requirements/SSO_REQUIREMENTS.md` a `sso-requirements.json` byly zachovány a nejsou součástí této publikace.

Testovací servery se ukončily. Automatická bezpečnostní kontrola odmítla příkaz pro odstranění šesti právě vytvořených syntetických prostředí s důvodem `blocked by policy`; odstranění se neprovedlo a nebylo obcházeno jiným nástrojem. Zůstávají `C:/mhl-feedback-20261007` a stejné názvy se suffixy `b`, `c`, `d`, `e`, `f`. Obsahují pouze tento lokální testovací WordPress, syntetické DB a lokální konfigurace. Nejsou v Git ani balíčcích. Historické distribuce, uživatelské podklady a produkční zálohy se neodstraňovaly.

Zdejší checkout používá nastavení `core.autocrlf=true`; runtime hash identifikuje přesné bajty testovaného pracovního checkoutu. Evidence proto doplňuje také přenositelný digest Git objektů zdrojů. Před novým sestavením na jiném OS je nutné znovu ověřit manifesty a testy po checkoutu; sjednocení pravidel konců řádků patří do přípravy dalšího vydání.

## Otevřené integrační podmínky

- Lidské posouzení 3 doručených návrhů a implementační/testové evidence v REQUIREMENTS; strojové připojení v2 a příslušné scoped credentials jsou nyní ověřené.
- Vhodný centrální formát pro starší neúspěchy s chybějícím přesným commit/timestamp. Záznamy zůstávají uchované; chybějící údaje se nevymýšlejí.
- Potvrzený AUTH/OIDC/MFA a ENTITLEMENTS/Organizations kontrakt, testovací klienti a izolovaný staging.
- Právní potvrzení licence frontendu, obchodních podmínek a privacy podle skutečného provozu. Návrh v dokumentaci není právní schválení.

Před nasazením změn: společná záloha WordPress DB, hlasovací DB, pluginu, frontendu, konfigurace a médií, zkouška obnovy, rollback a ověření aktivní verze. Rizikové jsou zejména migrace vlastnictví/rolí, identity cutover, změna veřejnosti výsledků, přepočet historie, nové pokusy úkolů a licenční omezení. Tato etapa produkční aplikaci nemění.
