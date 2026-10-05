# Nasazení Hlasuj! 0.8.8 — 5. 10. 2026

## Výsledek

Nasazeno na https://hlasuj.miloslavhub.cz s WordPress backendem https://miloslavhub.cz. Aplikační commit GitHubu: `213e999ca314be9a85178a1eace88ba203a9e24a`. Frontend a plugin mají verzi 0.8.8; databázové schéma 0.8.5. Přepnutí: 2026-10-05T11:50:06.356376+00:00 až 2026-10-05T11:50:07.045666+00:00 (UTC), během krátkého WordPress režimu údržby. Nebyla provedena DDL migrace. AI zůstala vypnutá; klíč a produkční konfigurace nebyly přidány ani změněny.

## Záloha a obnova

Soukromé úložiště: `C:\Users\mihu0334\AppData\Local\Hlasuj-private-backups\20261005-132500`. Mimo OneDrive, s oprávněním pouze místního uživatele a SYSTEM. Obsahuje aktuální frontend, plugin, oba configy, souborový WordPress s médii a SQL exporty obou databází. WordPress soubory vznikly z předchozího soukromého snímku doplněného aktuálními FTP změnami; starší smazané soubory zůstávají jako zálohová evidence. Cache a ostatní subdomény byly vynechány. Nejde o jeden atomický snímek všech souborů a obou databází.

Oba nové SQL exporty byly obnoveny na izolované místní MariaDB. WordPress: 130 tabulek, 125 778 řádků; hlasování: 5 tabulek, 52 řádků. CHECK TABLE prošel u podporovaných tabulek; jedna WordPress tabulka MEMORY tuto kontrolu nepodporuje a byla ověřena načtením a počtem řádků. Všechny hlasovací tabulky používají InnoDB.

Bezprostředně před přepnutím byl pořízen další export obou DB a poslední hlasovací export znovu obnoven. Ověřeno 0 aktivních live/test běhů. Frontend a plugin se před přepnutím přesně shodovaly se zálohou.

## Ověření vydání

Prošlo 269 dílčích kontrol regresních a integračních sad. Navíc prošly místní souběžné dávky 30 a 100 samostatných PHP/DB procesů, přesné počty hlasů, odmítnutí duplicity a pozdního hlasu. Testovací limit DB byl 500 spojení; test neprokazuje kapacitu hostingu ani dlouhodobý polling. Podrobnosti: [RELEASE-0.8.8.md](RELEASE-0.8.8.md) a [strojový doklad](test-evidence/2026-10-05-release-0.8.8.json).

## Ověření produkce

Připravené soubory byly zpětně staženy před přepnutím i po něm. SHA-256 a seznamy všech 39 frontendových a 22 pluginových souborů odpovídají připravenému obsahu. Konfigurace frontendu je zachována beze změny; WordPress config nebyl přepisován.

HTTP 200: homepage, privacy, release.json, app.js a hlavní WordPress. Anonymní live aktivace byla odmítnuta 403/mhl_teacher_required. Namespace REST obsahuje nové pluginové routy. Veřejný doklad verze: https://hlasuj.miloslavhub.cz/release.json.

Edge otevřel úvod, soukromí a demo bez chyby JavaScriptu. Demo vykreslilo viditelný QR obrázek; knihovna po vykreslení může skrýt pomocný canvas. Nevznikly žádné produkční studentské hlasy. Kontroly dema vytvořily pouze dočasné ukázkové relace s expirací 15 minut. Skutečné produkční přihlášení učitele, hlasování celé skupiny a všechny historické QR nebyly tímto nasazením ověřeny; učitelský průchod byl ověřen v izolovaném WordPressu.

## Úklid a návrat

Dočasné serverové složky a serverové kopie předchozí verze byly odstraněny po ověření souborů a HTTP. Režim údržby byl odstraněn. Obnovovací kopie zůstaly v chráněné soukromé záloze. Místní integrační a obnovovací servery byly ukončené; testovací složky zůstávají lokálně.

Rollback vrátí společně zálohovaný server/hlasuj a server/miloslavhub-live, se zachováním aktuálních provozních databází a konfigurace. Starou DB neobnovovat přes nové hlasy. Před obnovením souborového WordPressu vyhodnotit starší soubory ponechané v inkrementální záloze.

## Kontrolní součet obnovovacích podkladů

SHA-256 application-backup-manifest.json: `2c95a97028d0d631ccf48d8d5741193b302e7a05cb752adf577445368dfd482f`. Manifest pokrývá aktuální aplikační soubory, konfigurace a oba páry SQL exportů; souborová záloha celého WordPressu je uložená samostatně. Soukromé hodnoty a SQL data se nezveřejňují.
