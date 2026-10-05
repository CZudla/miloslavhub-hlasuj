# Pilot Foundation: první bezpečnostní iterace

**Aktualizace 2026-10-02 pro 0.8.7:** opravný rozsah původního 0.9.0-dev.1 prošel také 19 integračními kontrolami na WordPressu 7.1.2/MariaDB 11.4.9. Oba SQL exporty byly obnoveny a tabulky zkontrolovány lokálně. Původní body níže označené „dosud neověřeno“ zachycují stav před touto kvalifikací; aktuální souhrn je v RELEASE-0.8.7.md. Nasazení prokazuje samostatná deployment zpráva.

Datum: 2026-10-01. Autoritativní směr: MASTER-SPEC.md.

## NOW — 0.9.0-dev.1, lokální vývojová verze

- Uchovat ověřený zdrojový základ: frontend odpovídá archivu z 25. 9. i souborům serveru z 28. 9.; plugin na serveru má hlavičku 0.8.5.
- Zablokovat veřejné spuštění živé/testovací otázky. Připojení pouze sleduje učitelův stav; učitel spouští otázku v chráněné administraci. Samostatné veřejné demo má oddělené úložiště a automatický průběh.
- Opravit zveřejňování přezdívek v demu a nezávislé rozhodnutí každého účastníka.
- Opravit projekční URL, navázání tlačítek Síně slávy a ochranu textových buněk CSV před spuštěním vzorců.
- Zachovat staré QR adresy. Generovat QR lokálně, bez předání odkazu externímu generátoru.
- Odstranit automatický produkční cíl při chybějící lokální konfiguraci.
- Regresní testy, dokumentace, kontrolovatelný zdrojový balíček s kontrolními součty.

Tato iterace nemění databázové schéma. Není označena za hotový produkční release. Plný WordPress/DB integrační test, provozní záloha a obnova jsou podmínkou nasazení.

## NEXT

1. Reálné izolované WordPress + externí DB testovací prostředí; ověřit migrace, souběh hlasů, oprávnění a obnovu. Změřit polling při očekávaném počtu studentů.
2. Dokončit učitelský panel: spustit, ukončit, přeskočit, zopakovat; společný předstart 2–1–START se serverovým časem a atomické odmítání pozdních hlasů.
3. „Jak chcete otázku použít?“ → „Ve výuce“ / „Jako domácí úkol“. Úkol se samostatným postupem, termínem „Odevzdat do“, pokusy a řízeným vysvětlením; sada otázek ve stejném návrhu. Stávající dlouhodobá anketa zatím není domácí úkol.
4. Překladové klíče cs-CZ/en-US, zvlášť jazyk UI a obsahu; zavádět po kompletních obrazovkách.
5. Oprávnění a vlastnictví obsahu pro samostatné účty učitelů/organizací. Public/private API a serverová autorizace každého objektu.
6. Verzionovaný export/import obsahu s validací archivu a odděleným exportem výsledků; začít skutečným round-trip testem.
7. Jednotný release manifest, aktuální screenshoty, licence assets a marketing odpovídající ověřeným funkcím. Teprve potom zákaznický instalační release.

## LATER

Licenční a fakturační systém, referral/trial/sponsored přístupy, Cards, volitelná AI, samostatná SQLite distribuce, pokročilé statistiky a další exporty. Architekturu připravovat podle skutečných potřeb; nyní nepřepisovat fungující PHP/WordPress systém.

## Brána produkce

Ověřit aktivní verze a schéma přímo v autorizované administraci; soubory samy nedokazují aktivaci. Zálohovat WordPress DB, externí hlasovací DB, frontend, plugin, konfiguraci a média do chráněného úložiště; úspěšně obnovit mimo produkci. Připravit konkrétní balíček, postup a rollback. Schválení produkční verze získat nad tímto výsledkem.
