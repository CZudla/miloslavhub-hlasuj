AKTUÁLNÍ VYDÁNÍ: 0.8.7. Kořenový README.md a docs/RELEASE-0.8.7.md uvádějí aktuální rozsah a ověření.
Frontend a plugin aktualizovat společně; config zachovat; schéma se nemění.

Historie přípravy a předchozích verzí:

# Aktuální stav 0.9.0-dev.1

Doplněna individuální volba zveřejnění, serverové filtrování přezdívek, lokální QR renderer a ochrana před lock soubory pro neexistující relace. Kompletní popis omezení: docs/SECURITY.md a docs/PRIVACY.md v kořenovém zdrojovém balíčku.

Následuje historický základ popisu dema:

# Bezpečnost prezentačního dema 0.8.6

Demo je záměrně oddělené od produkční databáze MiloslavHub Live.

- Identifikátor relace: 32 hex znaků z `random_bytes(16)`.
- Presenter token: 64 hex znaků z `random_bytes(32)`.
- Presenter token se neposílá do QR kódu ani do mobilního odkazu.
- Mobilní participant ID vzniká lokálně v prohlížeči a není odvozeno z IP adresy.
- Přezdívka je omezena na 40 znaků a při renderování se escapuje.
- Jedno zařízení může v jedné otázce uložit pouze jednu odpověď.
- Relace expiruje 15 minut po vytvoření.
- Staré relace se průběžně mažou při dalších požadavcích.
- JSON zápis používá `LOCK_EX`; aktualizace probíhá pod file-lockem.
- API vrací `Cache-Control: no-store` a stránky dema `noindex`.
