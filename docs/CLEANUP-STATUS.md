# Stav úklidu po nasazení

## Aktualizace 0.8.9 · 5. 10. 2026

Serverové staging a rollback složky i režim údržby odstraněny po ověření nasazení. Nová chráněná záloha je C:\Users\mihu0334\AppData\Local\Hlasuj-private-backups\20261005-142547-089. Lokální servery vypnuté. Automatická kontrola odmítla také odstranění ověřených syntetických prostředí hlasuj-release-089-a a hlasuj-release-089-b s důvodem blocked by policy. Mazání nebylo obcházeno; zůstávají v místní cache a nejsou součástí Git ani balíčků. Serverový úklid je dokončený, úplný místní úklid není. Finální balíčky jsou v outputs/final-0.8.9.


## Dokončeno

- Na produkci odstraněny čtyři dočasné složky: připravený frontend, připravený plugin a obě dočasné kopie předchozí verze.
- Odstraněn režim údržby WordPressu; ověřena dostupnost hlavního webu.
- Ukončen lokální testovací MariaDB server, kontrola procesu potvrdila jeho nepřítomnost. Testovací PHP servery a prohlížeče se ukončily po testech.
- Původní podklady, zdrojový repozitář a soukromé obnovovací zálohy zachovány.

## Blokace místního mazání

Automatická bezpečnostní kontrola nástroje odmítla rekurzivní odstranění testovacích složek. Odmítla i zúžený příkaz pro jedinou ověřenou složku `integration`. Vrácený důvod byl pouze „blocked by policy“, bez dalšího vysvětlení. Uživatel již úklid povolil; nejde o chybějící souhlas uživatele. Tato kontrola nebyla obcházena alternativním mazacím nástrojem.

Proto nelze celý místní úklid označit za dokončený. Následující seznam rozlišuje soubory potřebné pro obnovu a pomocné kopie, které mohou být odstraněny později mimo tuto blokaci.

## Soukromá záloha — ponechat

Základ: `%LOCALAPPDATA%\Hlasuj-private-backups\20261002-083806` (desktopová aplikace může cestu přesměrovat do své LocalCache).

- `server/`, `wordpress-files/`: původní soubory a konfigurace.
- `wordpress.sql`, `voting.sql`, `voting-predeploy.sql`, `voting-immediate-predeploy.sql`: ověřené zálohy databází a přednasazovací exporty.
- `backup-manifest.json`, `restore-verification.json`, kontroly tabulek, počty řádků a doklady nasazení: evidence obnovy a integrity.

Celý tento adresář má omezený přístup a nesmí být publikován ani přibalen zákazníkovi.

## Soukromé dočasné položky — odstranění blokováno

Ve stejném adresáři: `integration/`, `restore-data/`, `runtime/`, `predeploy-remote/`, `deployment-stage/`, `stage-readback/`, `deployed-readback/`, `__pycache__/`. Obsahují testovací prostředí nebo duplicitní pracovní kopie. Zvláštní citlivost mají `restore-data/` s místně obnovenými daty a `local-client.ini` s heslem již ukončeného lokálního serveru.

Dočasné exportní HTML, `*-cookies.txt`, `*-export.response`, `*.winscp.txt`, `operations.py` a `maintenance.php` jsou pracovní pomůcky. Jsou také uvnitř chráněného adresáře a nebyly přibaleny do ZIPů. Z uložených HTML nebyl bezpečně určen odhlašovací odkaz phpMyAdmin; tyto relace nebyly prohlášeny za explicitně odhlášené.

## Dočasné položky v projektu

`work/local-runtime`, `work/production-source-20260928`, `work/source-snapshot`, `work/download-sources.winscp.txt` a ignorované pomocné soubory v `work/hlasuj/runtime/` zůstaly lokálně. `outputs/0.9.0-dev.1` a `outputs/0.8.7` jsou mezivýsledky před finálními dvěma balíčky; aktuální předání je v `outputs/final-0.8.7`.

Samostatné demo vytvořilo při produkční kontrole jednu relaci bez připojeného studenta. Má standardní patnáctiminutovou expiraci; fyzické odstranění souboru probíhá při následném vytváření dema. Nebyly vytvořeny testovací hlasovací řádky v produkční DB.
