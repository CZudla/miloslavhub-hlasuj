# Learning Log

**Aktualizace 2026-10-02 pro 0.8.7:** opravný rozsah původního 0.9.0-dev.1 prošel také 19 integračními kontrolami na WordPressu 7.1.2/MariaDB 11.4.9. Oba SQL exporty byly obnoveny a tabulky zkontrolovány lokálně. Původní body níže označené „dosud neověřeno“ zachycují stav před touto kvalifikací; aktuální souhrn je v RELEASE-0.8.7.md. Nasazení prokazuje samostatná deployment zpráva.

## 0.9.0-dev.1 — 2026-10-02

**Cíl:** odstranit prokazatelné chyby v řízení živého hlasování a zveřejňování výsledků dema bez změny databázového schématu.

**Zjištění:** FTP poskytlo chybějící pluginové zdroje; frontend z archivu odpovídal serverovým souborům. Původní veřejná aktivace a automatické joining chování byly v rozporu s požadavkem na řízení učitelem. Souhlas v demu byl jen uložená volba bez správného filtrování veřejné odpovědi. Projekce měla nesoulad v cestě API.

**Změny:** oprávnění aktivace, čekání na učitele, filtrování veřejných přezdívek, individuální dokončení dema, CSV ochrana, lokální QR, bezpečný stav bez konfigurace, dokumentace a reprodukovatelné lokální testy.

**Ověření:** konkrétní počty/výsledky jsou v přiloženém test-results.json. Screenshoty byly vizuálně prohlédnuty; odhalily ještě starý popis ručně řízeného dema, který byl opraven. Testy používají skutečné PHP demo a testovací objekty/HTTP data pro WordPress část.

**Registr:** přečten centrální XLSX z 29. 9., požadavky a rozhodnutí propojeny s implementací v TRACEABILITY.md. Stav „implementováno“ v registru sám neprokazuje splnění akceptačního kritéria; například značka, privacy a verze byly jen částečně sjednocené.

**Nasazení:** neprovedeno. Plná DB integrace, obnova zálohy a produkční smoke test neprovedeny.

**Další hypotéza:** jednoduché „Spustit hlasování“ a stabilní studentské připojení umožní výuku bez přepínání mezi technickými režimy. Ověřit s učitelem až po integračním testu; současné administrační rozhraní stále obsahuje další technický dluh.
