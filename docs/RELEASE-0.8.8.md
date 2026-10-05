# Hlasuj! by MiloslavHub 0.8.8

Opravné vydání, 5. 10. 2026. Frontend, plugin a současné příručky mají verzi 0.8.8. Externí databázové schéma zůstává 0.8.5. Konkrétní nasazení dokládá DEPLOYMENT-2026-10-05.md.

## Rozsah

- Příjem hlasu a učitelské uzavření, opakování, otevření další otázky, ukončení běhu a expirace sdílejí transakční zámek. Stav a čas se ověřují po získání zámku i po zápisu hlasu; odmítnutý hlas se vrací zpět.
- Staré učitelské ovládání nemůže měnit nahrazenou relaci. Trvalé QR, namespace mhl/v1 a historické výsledky zůstávají kompatibilní.
- Volitelný AI pilot pro přeformulování/překlad vyžaduje výslovné odeslání a použití návrhu. Výchozí stav je vypnutý; tato iterace nepovoluje placené API ani odesílání studentských výsledků.
- release-config.json sjednocuje metadata sestavení. Historické releases/ se nevkládají jako zdroj do nových distribucí. Nové ZIPy se ukládají do samostatného adresáře vydání.

## Databáze a provoz

Transakční ochrana vyžaduje InnoDB u runs, sessions, votes a session_joins. Nové instalační schéma engine určuje výslovně. Existující tabulky se automaticky nekonvertují. Na ověřené produkční databázi je InnoDB již nastavené, proto tento přechod nepotřebuje DDL migraci.

Před nasazením je nutná aktuální soukromá záloha obou databází, konfigurace a souborů, ověřená místní obnova, kontrola neaktivní výuky, připravená předchozí verze a zpětné stažení nahraných souborů. Rollback vrací frontend a plugin; běžně neobnovuje starou databázi přes novější hlasy.

## Ověření a limity

Regresní a integrační sady obsahují 269 dílčích kontrol včetně skutečné WordPress administrace a 28 řízených souběhů. Navíc tests/load.py spouští souběžné dávky 30 a 100 samostatných PHP/DB procesů. Ověřuje přesný počet uložených hlasů, odmítnutí duplicity a pozdního hlasu. Údaje jsou v docs/test-evidence/2026-10-05-release-0.8.8.json.

Testovací MariaDB má pro tuto dávku limit 500 spojení, protože každý WordPress proces používá dvě databáze. Jde o místní test REST callbacků a DB; neprokazuje kapacitu webových workerů hostingu, síťovou latenci ani dlouhodobý polling. Čas čekání na testovací startovací bariéru se nezapočítává do měření handleru.

Zůstává otevřeno: zátěž na cílovém hostingu, kvalita/cena/dostupnost skutečného AI modelu, domácí úkoly, plné i18n, obsahový import/export, samostatné role učitelů a organizací, výsledková politika API a rate limiting. Vlastní frontend/assets nadále vyžadují dokončení licenčních podmínek autorem.
