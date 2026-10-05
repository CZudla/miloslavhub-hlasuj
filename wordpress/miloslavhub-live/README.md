AKTUÁLNÍ VYDÁNÍ: 0.8.7. Kořenový README.md a docs/RELEASE-0.8.7.md uvádějí aktuální rozsah a ověření.
Frontend a plugin aktualizovat společně; config zachovat; schéma se nemění.

Historie přípravy a předchozích verzí:

# Hlasuj! by MiloslavHub — 0.9.0-dev.1

Lokální vývojová verze. Schéma zůstává 0.8.5, bez nových migrací. Aktuální změny a omezení: kořenový CHANGELOG.md a docs/UPGRADE.md. Před nasazením je nutná reálná WordPress/DB integrace, záloha a ověřená obnova.

Níže je zachována dokumentace výchozí verze; automatické spuštění live/test veřejným QR již neplatí.

---

# MiloslavHub Live 0.8.5

WordPress plugin pro živé hlasování, kvízy, ankety a gamifikaci výuky. Runtime hlasovací data používají samostatnou databázi; předměty, přednášky a otázky se spravují ve WordPressu.

## Novinky 0.8.5

- **Přezdívka pro celý předmět:** účastník si ji zvolí jednou a používá ji napříč přednáškami téhož předmětu. V jiném předmětu může použít stejnou nebo jinou přezdívku.
- **Síň slávy po kvalifikaci:** pokud je student v Top N, teprve potom se může rozhodnout, zda zveřejní přezdívku nebo zůstane anonymní. Pokud nereaguje, předmět může mít výchozí režim skrytý/anonymní.
- **Projekce bez WordPressu:** každý předmět má neveřejný tokenizovaný odkaz `/project/<predmet>/<token>`, který automaticky sleduje živou otázku a výsledky. Token lze regenerovat.
- **Dlouhodobé ankety:** poll otázku lze zpřístupnit samostatně na `/poll/<prednaska>/<otazka>`, bez společného odpočtu a bez blokování živé přednášky. Lze ji ukončit ručně nebo nastavit datum ukončení.
- **Průběžné výsledky dlouhodobé ankety:** volitelné zobrazení respondentovi ihned po hlasování.

## Funkce z 0.8.2–0.8.4 zahrnuté v 0.8.5

- jedinečnost přezdívky pouze v rámci předmětu; výchozí rezervace 365 dní od poslední aktivity,
- inteligentní start odpočtu (výchozí minimum 5 s, klid 3 s, maximum 15 s),
- sdílení živého připojení z mobilu přes QR/odkaz,
- ochrana soukromí, retenční pravidla, samoobslužný výmaz pseudonymních dat a dobrovolná Síň slávy,
- trvalé QR identifikátory otázek a přednášek,
- veřejné/testovací režimy, subjektové bodování, brand šablony, testovací demo.

## Přímý upgrade z 0.8.1

Pokud 0.8.2, 0.8.3 ani 0.8.4 nebyly nasazeny, **neinstalujte je mezitím**.

1. Zálohujte externí hlasovací databázi.
2. V phpMyAdmin vyberte hlasovací databázi a importujte jediný soubor `database-migration-0.8.1-to-0.8.5.sql`.
3. Aktualizujte WordPress plugin na 0.8.5.
4. Aktualizujte frontend na 0.7.6 a ponechte stávající `config.php`.
5. Ve WordPressu zkontrolujte stav databáze, projekční odkaz předmětu, nastavení Síně slávy a případné dlouhodobé ankety.

## Databáze

Fresh install používá `database-schema.sql`. Přímý upgrade z 0.8.1 používá `database-migration-0.8.1-to-0.8.5.sql`. Tabulka `mhl_participants` drží subject-scoped rezervace přezdívek a volbu zobrazení v Síni slávy; `mhl_session_joins` slouží k inteligentnímu startu odpočtu.

## Soukromí

Nebodované anonymní ankety neukládají přezdívku a používají klíč platný pouze pro konkrétní relaci otázky. Plugin sám nepotřebuje jméno, studentské číslo ani e-mail studenta. Přezdívka je pseudonymní údaj, nikoli automaticky anonymní údaj.

## RAG / indexace

Hlasovací a REST data jsou standardně `noindex` a RAG politika otázky je ve výchozím stavu `exclude`. Dynamické výsledky se nemají vkládat do veřejného embedding indexu.
