# Hlasuj! by MiloslavHub 0.8.7

Opravné vydání, 2026-10-02. Vychází z lokálně ověřeného pracovního 0.9.0-dev.1; číslo 0.8.7 vyjadřuje omezený rozsah oprav před širším Pilot Foundation. Frontend/backend/docs: 0.8.7. Externí databázové schéma: beze změny 0.8.5.

## Dodané změny

Řízení live/test aktivace pouze oprávněným učitelem v administraci, zákaz automatického otevření při studentském připojení, opravená projekční cesta, funkční tlačítka Síně slávy, samostatná volba zveřejnění každého účastníka dema a serverové filtrování skrytých přezdívek. CSV export chrání textové vzorce. QR v hlavním frontendu a demu se vykresluje lokálně; konfigurace se nevkládá automaticky z produkčního příkladu.

Historické QR adresy zůstávají. Samostatné demo je automatické. Staré testovací QR/PPTX vyžadují spuštění testu učitelem. Učitelské akce jsou označené „Spustit hlasování“ a „Zopakovat otázku“. Tato verze nezavádí nové role učitelů — ovládání stále používá WordPress manage_options.

## Ověření před nasazením

- PHP/JS syntaxe, 51 kontraktových kontrol, 18 HTTP kontrol dema, 11 kontrol v prohlížeči Edge. Dvě obrazovky zachyceny a vizuálně zkontrolovány.
- 19 integračních kontrol se skutečným WordPressem 7.1.2 a MariaDB 11.4.9. Syntetická data, externí HTTP a e-mail vypnuty. Kontroly zahrnují učitele/studenta, legacy joining, pozdní připojení bez restartu časovače, hlas, duplicitu, deadline, zveřejnění správnosti až po uzavření, async anketu a stabilní URL.
- Databázové exporty: WordPress 130 tabulek a hlasování 5 tabulek. Obě databáze byly lokálně obnoveny a CHECK TABLE proběhl bez chyby. Zdrojová MariaDB 10.11.18; testovací restore 11.4.9. Nejde o test obnovy přímo na hostingu.
- Ze zálohy WordPress DB bylo potvrzeno, že plugin miloslavhub-live je aktivní a zapsaná verze externího schématu je 0.8.5.
- Souborová záloha a její konečné ověření musí být doloženy deployment zprávou před přepsáním produkce. ZIP bez takového dokladu sám nasazení neopravňuje.

## Omezení a zbývající práce

Neproběhl zátěžový/souběhový test ani formální bezpečnostní certifikace. Stávající veřejná dostupnost výsledků, rate limiting, oddělení organizací, atomický souběh uzavření/hlasu, všechny historické varianty bodování a kompletní soukromí exportů vyžadují další práci. U nebodované ankety zůstává starší volitelné bodování v konfiguraci; přechod na striktně neutrální model musí respektovat historii.

Domácí úkoly, vysvětlení/teacher_note, plné cs/en UI, obsahový import/export, nové náhodné join aliasy, multi-teacher role, sjednocený design a úplná aktualizace marketingových screenshotů nejsou hotovými funkcemi tohoto vydání. Existující marketingová galerie je označena jako předchozí verze. Překladová architektura zůstává otevřeným dluhem.

GPL deklarace pluginu zůstává; lokální QRCode.js má MIT text a provenienci. Úplné vypořádání práv vlastních assets a licenční model zůstávají k potvrzení. Privacy dokumentace popisuje reálné chování; neobsahuje právní záruku.

## Nasazení a obnova

Uživatel dne 2026-10-02 výslovně povolil nasazení na webový server a následný úklid. Není nutné opakovat žádost o schválení stejné operace. Platí podmínka ověřené zálohy a obnovy z hlavního zadání.

Před přepnutím znovu ověřit, že neprobíhá aktivní live/test výuka a zdroje se od zálohy nezměnily. Aktualizovat frontend a plugin koordinovaně; zachovat skutečný config, DB a URL. Nové soubory nahrát připravené, provést krátké přepnutí a smoke test. Rollback obnoví předchozí soubory z chráněné zálohy; neobnovuje automaticky DB přes nové hlasy.

Doklad nasazení musí uvádět commit, čas, kontrolované URL, HTTP stav/verze, výsledek smoke testu, hash zálohy a úklid. Dokud tento doklad nepotvrdí úspěch, nasazení se nepovažuje za dokončené.
