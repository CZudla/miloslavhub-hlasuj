# Bezpečnost a hranice ověření

## Nevydaná etapa 8. 10. 2026 — učitelé a organizace

Aktuální vývoj nahrazuje jediný administrátorský přístup lokální schopností aplikace a oprávněním konkrétního objektu. Kontroluje organizaci, vlastníka, sdílení, spuštění/změnu relace, export a mazání testů. Dotazy administrace filtruje před stránkováním. Správce organizace nedostává `manage_options`; technický správce instalace má nadále přístup k celé instalaci. Původní QR a veřejné výsledkové cesty zachovávají dosavadní veřejný kontrakt. Podrobná [reference](ORGANIZATIONS-I18N-REFERENCE.md) uvádí role, dědění, odvolání a hranice.

Místní WordPress účty nejsou dokončená centrální AUTH integrace. Licence není udělena místní rolí ani výpočtem počtu účtů. Před cutoverem je nutné kvalifikovat skutečné identity, stav účtu, MFA, odvolání a zdrojové licenční rozhodnutí. Starší sekce níže zachycují původní kvalifikované vydání a nesmějí se číst jako nové testové či produkční potvrzení.

## Nevydaná etapa 7. 10. 2026

- QR administrace používá místní knihovnu s licencí a kontrolními součty. Neodesílá hlasovací adresy externímu QR generátoru. Tím je v této vývojové etapě vyřešen starší bod o backendových QR níže.
- Vysvětlení má bezpečný výchozí režim `teacher_only`; jeho zveřejnění hlídá server až po uzavření kvízu. Klient text escapuje. Veřejný výsledkový odkaz zpřístupní zveřejněné vysvětlení také osobě mimo výuku.
- Soukromá poznámka chybí ve studentských/projekčních payloadech. V autorizovaném JSON v2 exportu je záměrně přítomná a před sdílením vyžaduje kontrolu osobních údajů. Samostatná oprávnění více učitelů zatím nejsou implementována.
- REQUIREMENTS adaptér čte pouze pevnou HTTPS centrálu, odmítá přesměrování, má limit odpovědi a credential bere z prostředí. Jeho autentizovaný provoz zatím nebyl ověřen. Podrobná evidence a hranice jsou v [REQUIREMENTS-INTEGRATION.md](REQUIREMENTS-INTEGRATION.md).

Následující sekce zachycují starší kvalifikaci; nevydané změny nejsou potvrzením aktuálního nasazení nebo nové zálohy.

**Aktualizace 2026-10-02 pro 0.8.7:** opravný rozsah původního 0.9.0-dev.1 prošel také 19 integračními kontrolami na WordPressu 7.1.2/MariaDB 11.4.9. Oba SQL exporty byly obnoveny a tabulky zkontrolovány lokálně. Původní body níže označené „dosud neověřeno“ zachycují stav před touto kvalifikací; aktuální souhrn je v RELEASE-0.8.7.md. Nasazení prokazuje samostatná deployment zpráva.

## Změny této iterace

- `/activate` pro live/test vyžaduje `manage_options`. Kontrola je na REST routě, uvnitř callbacku i v core aktivaci. Cookie autentizaci a REST nonce zpracovává WordPress; klientská volba role nic neuděluje.
- Čtení legacy joining relace ji samo neotevře. Veřejné demo používá oddělený endpoint a úložiště.
- Neexistující demo relace nevytváří lock soubor. Současný test neprokazuje odolnost celé aplikace vůči DoS.
- Textové CSV buňky se vzorcovými prefixy včetně úvodních mezer/řídicích znaků jsou exportovány s ochranným apostrofem.
- QR generátor hlavního frontendu a dema je lokální. Backendové administrační QR a další externí závislosti vyžadují samostatnou kontrolu.
- Bez `config.php` vrací frontend HTTP 503; nevkládá do stránky implicitní produkční API.

## Nevyřešené podmínky produkce

Plné WordPress/DB integrační testy, rate limiting, autorizace objektů pro více vlastníků, oddělení veřejných/soukromých výsledků, souběh hlasů, ochrana před hlasem přijatým po uzavření, kontrola zálohy a obnovy. Aktuální `/results` má veřejný přístup podle slugů; samotný projekční token není plošnou ochranou výsledků.

Hodnoty tajemství nepatří do Git, ZIP, hlášení ani screenshotů. `config.php`, `wp-config.php`, `.env`, runtime logy a dumpy jsou vyloučeny z verzování nebo sestavení. Uložené připojení WinSCP a produkční konfiguraci chránit na hostiteli. Scan zdrojů je omezený a nenahrazuje správu tajemství.
