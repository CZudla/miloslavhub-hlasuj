# Nasazení Hlasuj! 0.8.9 · 5. 10. 2026

## Skutečný stav

Vydání **0.8.9** nasazeno na `https://hlasuj.miloslavhub.cz` a do pluginu `miloslavhub-live` na `https://miloslavhub.cz`. Nasazený aplikační commit: **2168e3eef1f371d5588d1842813637694247f992**. Veřejný doklad: `https://hlasuj.miloslavhub.cz/release.json`.

Přepnutí souborů: **12:41:17.921–12:41:18.745 UTC**, tedy **14:41 dne 5. 10. 2026 v Praze**. Schéma hlasovací DB zůstalo **0.8.5**, bez DDL migrace, přepisování historických výsledků či změny QR. Stávající konfigurace byla zachována po bytech. AI nebyla zapnuta, placená API nebyla použita.

## Záloha a návrat

Soukromá záloha: `C:\Users\mihu0334\AppData\Local\Hlasuj-private-backups\20261005-142547-089`, s omezeným přístupem pro uživatele a SYSTEM. Obsahuje frontend, plugin, konfigurace, oba aktuální SQL exporty a novější přednasazovací exporty. WordPress s médii byl převzat z předchozí uchované kopie a aktualizován FTP synchronizací. Soubory s původními přístupy a údaji nejsou v repozitáři ani distribuci.

Oba počáteční SQL exporty obnoveny na oddělené loopback MariaDB. WordPress: 130 tabulek, 125 741 řádků; voting: 5 tabulek, 52 řádků. CHECK TABLE prošel u podporovaných tabulek; jedna WordPress MEMORY tabulka tuto kontrolu nepodporuje a byla ověřena čtením/počtem. Hlasovací tabulky jsou InnoDB. Lokální obnovovací server vypnut.

Čerstvý voting export bezprostředně před přepnutím byl znovu lokálně importován: **0 aktivních live/test běhů** a transakční tabulky InnoDB. Přednasazovací soubory staženy a porovnány s uloženou zálohou. SQL se na produkci neimportovalo.

Manifest aktuální aplikace, konfigurací a počátečních SQL exportů: **65 souborů**, SHA-256 manifestu **1de80efbf2299ac5dff25b57320ec1e422117f8267d212e5b1d9dc75c2af1a51**. Záloha není atomický snapshot všech souborů a obou DB. Nový úplný hashový průchod všemi WordPress soubory nebyl proveden. Obnova SQL ani existence souborové kopie samy nedokazují kompletní obnovu celého webu.

Návrat aplikace používá uložený předchozí frontend 0.8.8 a plugin 0.8.8 se zachováním současné konfigurace a provozních DB. Starou DB neobnovovat přes nové hlasy. Importovaný obsah se ukládá do běžných WordPress posts/meta; starší plugin jej může nadále používat, ale nemá nový ověřený přenos.

## Ověření nasazení

- Připravené i znovu stažené nasazené soubory: **39 frontendových a 23 pluginových souborů**, shodné názvy i SHA-256. Frontendový počet zahrnuje instance config a release metadata.
- HTTP 200: úvod, soukromí, release.json, app.js a hlavní WordPress web.
- Release metadata uvádějí 0.8.9, uvedený commit, schéma 0.8.5 a vypnutou AI.
- Nepřihlášené spuštění živého hlasování: HTTP 403 `mhl_teacher_required`.
- Nepřihlášený přístup k nové administraci: chráněná přihlašovací cesta HTTP 401. Nepřihlášený požadavek exportu obsahu odmítnut HTTP 400; nevrátil obsah.
- Edge: úvod, stránka soukromí a demo bez JavaScriptových chyb; QR v demu viditelný. Žádné produkční hlasovací řádky nebyly vytvořeny; demo vytvořilo jednu dočasnou relaci s běžnou 15minutovou expirací.
- Úplný produkční učitelský import/export nebyl proveden v přihlášeném účtu. Jeho 50 integračních a 11 prohlížečových kontrol prošlo na skutečném izolovaném WordPressu/MariaDB se syntetickými daty. Tuto hranici nelze zaměňovat za ověření produkčního učitelského účtu, všech historických QR ani kapacity hostingu.

## Úklid a předání

Odstraněny obě serverové staging složky, obě dočasné rollback složky a režim údržby. Soukromá předchozí aplikace a SQL zálohy zůstaly zachované. Místní PHP/MariaDB a testovací prohlížeče ukončeny.

Odstranění nových místních syntetických prostředí `hlasuj-release-089-a` a `hlasuj-release-089-b` odmítla automatická kontrola („blocked by policy“). Mazání nebylo obcházeno. Složky jsou v místní cache, mimo Git a ZIPy. Historická blokace jiných místních složek zůstává popsaná v CLEANUP-STATUS.md. Úklid serveru je hotový; úplný místní úklid není hotový.

Dva aktuální ZIPy, 19stránková PDF/HTML příručka, produktový list, deset zákaznických dokumentů a marketing patří do `outputs/final-0.8.9` a `releases/0.8.9`. Kontrolní součty a `OVERENI-BALICKU.json` určují finální distribuci; samotný aplikační commit neobsahuje následně vytvořené ZIPy. Dokumentace předání a distribuční soubory mají vlastní navazující commity.
