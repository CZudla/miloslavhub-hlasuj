# Vazba požadavků na tuto iteraci

**Aktualizace 2026-10-02 pro 0.8.7:** opravný rozsah původního 0.9.0-dev.1 prošel také 19 integračními kontrolami na WordPressu 7.1.2/MariaDB 11.4.9. Oba SQL exporty byly obnoveny a tabulky zkontrolovány lokálně. Původní body níže označené „dosud neověřeno“ zachycují stav před touto kvalifikací; aktuální souhrn je v RELEASE-0.8.7.md. Nasazení prokazuje samostatná deployment zpráva.

Zdroj: `Požadavky na systém/Centralni-registr-Hlasuj-pozadavky-a-rozhodnuti-2026-09-29.xlsx`, listy Požadavky, Rozhodnutí a Roadmapa. Registr obsahuje 75 požadavků a 19 rozhodnutí. Přečten bez úprav; tento dokument nedeklaruje aktualizaci XLSX ani schválení produkce.

| ID | Změna / důkaz | Test | Stav této iterace |
|---|---|---|---|
| HLS-001 | Textová značka frontend/plugin | Screenshoty + kontrola zdrojů | Částečně; bitmapové logo a starší texty zbývají |
| HLS-007, HLS-012 | Spustit hlasování / Zopakovat otázku | Zdrojová kontrola | Částečně; celý teacher flow zbývá |
| HLS-011 | REST a core kontrola aktivace; žádný auto start z joining | security.php + browser.cjs | Lokálně ověřena zákazová pravidla; WP integrace zbývá |
| HLS-014, HLS-015 | Oddělené automatické demo, URL této instalace | run.py + browser.cjs | Lokální HTTP/prohlížeč; všechny mobilní scénáře zbývají |
| HLS-020 | Předčasný/pozdní/duplicitní hlas v demu | run.py | Demo ověřeno; produkční DB souběh neověřen |
| HLS-026, HLS-027 | Zachované staré cesty, lokální QR renderer | browser.cjs | Kompatibilní základ; náhodný join alias ještě není |
| HLS-030, HLS-031 | CSV vzorce v textových buňkách | security.php | Dílčí ochrana stávajícího exportu; nové formáty/scope nejsou |
| HLS-060, HLS-061 | Demo opt-in, anonymita, skip, rejoin a individuální dokončení | security.php + run.py | Lokálně ověřeno; širší privacy audit zůstává |
| HLS-062 | Více kontrol aktivace, config 503, lock DoS dílčí oprava | security.php + run.py | Částečně; není dokončená security certifikace |
| HLS-063 | MIT text QR, licence pluginu zachována | provenance.json | Částečně; vlastní licence/assets vyžadují potvrzení |
| HLS-066–068 | Manifest, dokumentace, Learning Log | sestavení a ověření ZIP | Lokální vývojový balíček |
| HLS-074 | Reprodukovatelné dva lokální screenshoty | browser.cjs | Částečně; úplná marketingová galerie zbývá |
| HLS-075 | Serverový snímek a SHA-256 porovnání | source inventory | Verze souborů doloženy; aktivní provozní stav nikoli |
| HLS-064, HLS-065, HLS-073 | Dokumentovaný postup zálohy/deploy/rollback | Neprovedeno | Stabilní zákaznický release a produkce zbývají |

HLS-018 (striktně nebodovaná anketa), HLS-022/023 (vysvětlení/poznámka), HLS-024/025 (domácí úkol), HLS-028/029 (import/export obsahu), HLS-032/033 (účty/role), HLS-045/046 (i18n) a ostatní roadmapové požadavky nejsou touto iterací prohlášeny za dokončené. Rozhodnutí DEC-004, DEC-008, DEC-017 a DEC-019 určují další směr.
