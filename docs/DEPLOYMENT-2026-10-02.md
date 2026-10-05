# Zpráva o nasazení 0.8.7

## Výsledek

Hlasuj! by MiloslavHub 0.8.7 bylo nasazeno 2. října 2026 na https://hlasuj.miloslavhub.cz s WordPress backendem na https://miloslavhub.cz. Přepnutí proběhlo 17:36:06–17:36:10 UTC, tedy 19:36:06–19:36:10 Europe/Prague. Součástí postupu byl krátký režim údržby WordPressu.

Nasazený aplikační commit: `40dce6213af07b01f4f9f2bf428786709979c109`. Frontend a plugin: 0.8.7. Externí schéma: 0.8.5. Nebyla spuštěna databázová migrace. Následné commity dokumentace a distribučních skriptů tento aplikační kód nemění.

Veřejný doklad verze: https://hlasuj.miloslavhub.cz/release.json. Soubor uvádí verzi a aplikační commit, bez přístupových údajů. Původní konfigurace byla zachována; následně byly upraveny pouze dvě veřejné položky názvu značky na Hlasuj! a Hlasuj! by MiloslavHub. Zbytek konfiguračních bajtů zůstal beze změny.

## Záloha a výchozí stav

- Souborový snímek zahrnuje WordPress včetně pluginů a médií, konfiguraci a celý frontend. Vynechány byly ostatní subdomény a regenerovatelná WordPress cache.
- Samostatně byly exportovány WordPress DB a hlasovací DB. Bezprostředně před přepnutím byl hlasovací export obnoven ještě jednou lokálně a ověřeno **0 aktivních live/test běhů**.
- Obě původní DB byly obnoveny v izolovaném lokálním prostředí. WordPress: 130 tabulek, 128 345 řádků. Hlasování: 5 tabulek, 53 řádků v původním snímku. CHECK TABLE proběhl bez chyby.
- Zdrojová MariaDB 10.11.18, lokální obnova 11.4.9. Nejde o provedenou obnovu přímo na hostingu.
- Sloupce, typy, NULL a výchozí hodnoty hlasovací DB odpovídaly testovacímu schématu. Produkce obsahovala kompatibilní dodatečný index `mhl_session_joins.first_seen_at`; zůstal zachovaný.
- Stažený frontend a plugin se před přepnutím shodovaly s výchozí zálohou. Celý WordPress nebyl během pořizování souborové zálohy zmrazen a SQL exporty obou DB nejsou jeden atomický snapshot. Obnova byla ověřena, nikoli garantována časová atomicita celého webu.

Soukromý zálohový adresář: `%LOCALAPPDATA%\Hlasuj-private-backups\20261002-083806`. Je mimo OneDrive a má omezené oprávnění na místního uživatele a SYSTEM. V desktopové aplikaci může být cesta fyzicky přesměrována do její LocalCache. Záloha ani produkční konfigurace nejsou součástí distribučních ZIPů.

Manifest zálohy má 31 032 záznamů. SHA-256 `backup-manifest.json`:

`8dd7f75a323b54e5d302612f590e9434a673907cf84413cf3b8b53f157607365`

## Ověření kódu před nasazením

- PHP a JavaScript syntaxe: prošla.
- 51 kontraktových kontrol oprávnění, soukromí a exportu: prošlo.
- 18 HTTP kontrol samostatného dema: prošlo.
- 11 prohlížečových kontrol s reálným frontendem a syntetickým API: prošlo; dva screenshoty, žádné externí požadavky této testovací sady.
- 19 integračních kontrol skutečného WordPressu 7.1.2 a MariaDB: prošlo. Použita pouze syntetická data, vypnut odchozí HTTP a e-mail. Zahrnuto učitelské oprávnění, čekání po připojení, společný čas, hlas, duplicita, deadline, správnost po uzavření, async anketa a stálý identifikátor.

## Ověření produkce

Připravené soubory byly nejprve nahrány do dočasných složek a zpětně staženy. Po přepnutí byl znovu stažen frontend a plugin. Kontrolní součty všech **39 frontendových a 19 pluginových souborů** odpovídaly připravenému obsahu. Následná změna dvou značkových položek konfigurace byla ověřena zvláštním stažením.

HTTP 200: homepage, privacy, release.json, app.js, lokální QR knihovna i hlavní web WordPressu. Anonymní POST live `/activate` s neexistujícím obsahem vrátil očekávané **403 / mhl_teacher_required** před prací s obsahem.

Edge otevřel homepage, stránku soukromí a demo, bez chyby JavaScriptu. Demo vykreslilo QR do canvasu. Na produkci nebyly vytvořeny testovací hlasovací záznamy. Kontrola dema vytvořila jednu dočasnou relaci bez připojeného studenta; standardně expiruje za 15 minut, fyzický úklid proběhne při dalším vytvoření dema.

Produkční přihlášení učitele, skutečné hlasování celé skupiny, zátěž ani všechny historické QR nebyly tímto smoke testem prověřeny. Oprávnění a stavový průběh byly ověřeny integračně v izolovaném prostředí.

## Úklid a návrat

Dočasné serverové složky s připravenou a předchozí verzí byly po ověření odstraněny. `.maintenance` byl odstraněn a hlavní WordPress zůstal dostupný. Obnovovací kopie jsou zachované v chráněné místní záloze.

Lokální databázový server byl ukončen. Rekurzivní smazání místního testovacího prostředí zablokovala automatická bezpečnostní kontrola nástroje. Místní úklid proto zůstává částečný; přesný seznam zachovaných záloh a dočasných položek je v CLEANUP-STATUS.md.

Při návratu obnovit společně původní `server/hlasuj` a `server/miloslavhub-live` do odpovídajících cest, mimo výuku a s krátkým koordinovaným přepnutím. Zachovat nové provozní hlasy. Databázový rollback není součástí běžného návratu tohoto vydání bez změny schématu. Vrácení starého kódu vrátí také jeho původní chování aktivace a soukromí.

## Co tímto vydáním není uzavřeno

Domácí úkoly, plné i18n, obsahový export/import, role učitelů a organizací, úplná public/private politika API, rate limiting, souběh uzavírání s hlasováním, licenční režim vlastního frontendu a nová marketingová galerie patří do dalších kroků. Rozsah a rizika jsou podrobně uvedeny v RELEASE-0.8.7.md a zákaznické příručce.
