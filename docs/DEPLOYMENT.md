# Postup nasazení a návratu

**Aktualizace 2026-10-02 pro 0.8.7:** opravný rozsah původního 0.9.0-dev.1 prošel také 19 integračními kontrolami na WordPressu 7.1.2/MariaDB 11.4.9. Oba SQL exporty byly obnoveny a tabulky zkontrolovány lokálně. Původní body níže označené „dosud neověřeno“ zachycují stav před touto kvalifikací; aktuální souhrn je v RELEASE-0.8.7.md. Nasazení prokazuje samostatná deployment zpráva.

Verze 0.8.7 byla nasazena 2. 10. 2026. Doklad a skutečné výsledky kontrol jsou v DEPLOYMENT-2026-10-02.md. Následující body slouží jako provozní postup pro další aktualizace; ne všechny uvedené scénáře byly provedeny na produkčních datech.

## Před schválením konkrétní verze

1. Připravit oddělený WordPress a externí MySQL se syntetickými daty. Ověřit install/upgrade ze skutečné výchozí verze, oprávnění a reálný učitelský panel. Nezapojovat toto prostředí na produkční API.
2. Ověřit dva studenty, učitele, projektor: připojení nezahájí otázku; start všem otevře tutéž session; uzavření blokuje další hlas; opakování ponechá historii. Ověřit souběžné, duplicitní a pozdní požadavky i oprávnění ke čtení výsledků.
3. Sestavit release z čistého commitu, ověřit manifest a jeho přesný obsah, licence, konfiguraci, screenshoty a dokumentaci.
4. Určit servisní okno mimo výuku a schválit konkrétní stabilní verzi.

## Záloha

Zálohovat WordPress DB, externí hlasovací DB, frontend, plugin, média, WordPress i frontend config a metadata instalace. Zapsat čas, aktivní verze, kontrolní součty, počet tabulek a obnovovací postup. Přístupové údaje a osobní data chránit; neukládat do repozitáře nebo zákaznického ZIPu.

Samotný FTP snímek neobsahuje databázi a není úplná záloha. Zálohu ověřit obnovením do izolovaného prostředí a kontrolou vazeb obsah → session → hlasy. Konzistenci obou DB zajistit servisním oknem či koordinovaným snapshotem.

## Instalace schválené verze

Frontend a backend aktualizovat koordinovaně. Starý frontend očekává veřejné `/activate`; kombinace starého klienta a nového backendu může skončit chybou 403. Zachovat skutečnou konfiguraci, trvalé slugy, názvy pluginové složky a staré URL. Vyčistit jen příslušnou cache a ověřit načtené asset verze. Tato vývojová iterace nemá DB migraci.

## Smoke test a rollback

Po nasazení ověřit verzi/commit, úvod, nové i staré QR, mobil, projekci, učitelské spuštění/ukončení, hlas, výsledky, export, opt-in, anonymitu a demo. Zapsat výsledek a reálný stav nasazení.

Rollback bez změn schématu znamená vrátit koordinovaně předchozí frontend a plugin, zachovat config a provozní data. Neobnovovat bez rozmyslu starou databázi přes nové hlasy. Pokud změna dat vyžaduje DB obnovu, zastavit příjem hlasů a schválit způsob zachování nových záznamů. Návrat ke starému pluginu také vrací opravené chyby soukromí/aktivace; při bezpečnostním incidentu upřednostnit dočasné odstavení dotčené funkce před nekontrolovaným návratem.
