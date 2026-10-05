# Přechod z ověřeného zdrojového základu

**Aktualizace 2026-10-02 pro 0.8.7:** opravný rozsah původního 0.9.0-dev.1 prošel také 19 integračními kontrolami na WordPressu 7.1.2/MariaDB 11.4.9. Oba SQL exporty byly obnoveny a tabulky zkontrolovány lokálně. Původní body níže označené „dosud neověřeno“ zachycují stav před touto kvalifikací; aktuální souhrn je v RELEASE-0.8.7.md. Nasazení prokazuje samostatná deployment zpráva.

Výchozí soubory: plugin 0.8.5, frontend manifest 0.7.9 + demo 0.8.6.16. Nasazené opravné vydání: 0.8.7; schéma 0.8.5.

- Není přidán SQL migrační soubor. Automaticky nespouštět žádnou z historických migrací jen proto, že jsou součástí archivu.
- Stará metadata `_mhl_auto_qr` zůstávají uložená, ale neudělují veřejnému QR právo aktivovat otázku. Učitel používá panel a „Spustit hlasování“.
- Oddělené webové demo `/demo/` zůstává automatické. Staré testovací QR/PPTX nyní vyžadují zahájení testu učitelem; jejich adresy zůstávají funkční pro připojení.
- Stávající `config.php` se zachovává mimo Git/balíček. Nová instalace ho musí výslovně připravit podle `config.example.php`; vzor obsahuje produkční domény a pro staging je nutné je všechny změnit.
- Dlouhodobá anketa `async` má dosavadní význam. Úprava ji nepřevádí na domácí úkol a nemění historické bodování.
- Nasadit frontend i backend společně až po testech a obnově zálohy podle DEPLOYMENT.md.
