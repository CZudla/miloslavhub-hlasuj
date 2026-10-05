# Hlasuj! by MiloslavHub

## GitHub a distribuce

Projekt používá [CZudla/miloslavhub-hlasuj](https://github.com/CZudla/miloslavhub-hlasuj). Větev `main` uchovává aktuální kvalifikované vydání, `develop` následný pracovní vývoj. Dva balíčky a PDF jsou v `releases/0.8.9/`. Postup práce a obnova původní historie jsou v [docs/GITHUB.md](docs/GITHUB.md).

**0.8.9 — přenos předmětu s náhledem a importem nových konceptů.**

Vývojová migrace GPT‑6 a vypnutý AI pilot: [stav migrace](docs/GPT-6-MIGRATION-STATUS.md), [popis pilotu a testů](docs/AI-PILOT.md). AI je standardně vypnutá. Rozsah vydání dokládá RELEASE-0.8.9.md; konkrétní nasazení bude doloženo samostatnou zprávou DEPLOYMENT-2026-10-05-0.8.9.md.

Rozsah, provedená ověření a zbývající omezení jsou v docs/RELEASE-0.8.9.md. Stav konkrétního nasazení dokládá samostatná deployment zpráva; sestavení ZIPu samo nasazení nepotvrzuje.

Tato složka je nový zdrojový Git repozitář vytvořený z ověřených podkladů. Frontend z archivu 25. 9. 2026 se shoduje s 34 staženými frontendovými soubory serveru z 28. 9. Plugin pochází ze stejného serverového snímku a měl verzi 0.8.5. Produkční konfigurace a data nebyly kopírovány.

- `frontend/`: PHP stránky, JavaScript, lokální QR renderer, oddělené demo.
- `wordpress/miloslavhub-live/`: WordPress plugin, externí databázové schéma a historické migrace.
- `tests/`: regresní testy bez produkčních dat.
- `scripts/`: sestavení kontrolovatelného vývojového balíčku.
- `docs/`: audit, hlavní specifikace, plán a provozní dokumentace.

## Ověření

Potřeba: PHP 8.1+, Python 3, Node.js. Pro test prohlížeče také Playwright a Edge.

```text
python tests/run.py --php C:/php84/php.exe
python tests/run.py --php C:/php84/php.exe --browser
```

Node musí mít dostupný balíček `playwright` (případně přes `NODE_PATH`). Testy založí dočasný server pouze na `127.0.0.1`, používají syntetická data a po dokončení jej ukončí. Výsledky a screenshoty zůstávají v ignorované složce `runtime/`. Testy WordPress kontraktů používají testovací objekty; dodatečný tests/wordpress-integration.php ověřuje 19 scénářů na skutečném WordPressu a MariaDB v izolovaném prostředí. Plné místní WordPress/DB ověření, 28 kontrol souběhu a 13 kontrol skutečné administrace v Edge spouští `tests/local-integration.py`; postup je v [AI-PILOT.md](docs/AI-PILOT.md). [Souhrn výsledků z 5. 10.](docs/test-evidence/2026-10-05-local.json) neobsahuje konfigurace ani provozní logy. Místní souběžné dávky 30 a 100 hlasů spustíte přepínačem --load u tests/local-integration.py. Nejde o ověření kapacity hostingu.

## Dokumentace

Začněte [auditem](docs/AUDIT.md), [plánem iterace](docs/ITERATION-PLAN.md), [architekturou](docs/ARCHITECTURE.md) a [změnami](CHANGELOG.md). Před provozem čtěte [SECURITY](docs/SECURITY.md), [PRIVACY](docs/PRIVACY.md), [DEPLOYMENT](docs/DEPLOYMENT.md) a [UPGRADE](docs/UPGRADE.md).

Historické changelogy a README uvnitř komponent dokumentují předchozí verze. Aktuální stav určují tento README, kořenový CHANGELOG a dokumenty v `docs/`. Hlavní požadavky jsou v `docs/MASTER-SPEC.md`; uvedené budoucí funkce nejsou automaticky implementované.

## Sestavení opravného vydání

Po změnách aktualizovat frontend manifest, spustit testy a commitnout zdroje. Poté sestavit:

```text
python scripts/refresh_manifest.py
python tests/run.py --browser
git add .
git commit -m "Describe the tested change"
python scripts/build_release.py --output ../../outputs/0.8.9
```

Sestavení odmítne nečistý checkout, citlivé názvy souborů, zastaralý frontend manifest a kód změněný od úspěšných testů. ZIP obsahuje přesný seznam souborů s hashi, commit a důkazy testů. Neobsahuje produkční config ani DB. Sestavení aktuální verze vyžaduje také úspěšný integrační test a doklad obnovení obou databází.

## Přenos obsahu

Menu **Živé hlasování → Přenést obsah** exportuje předmět, přednášky a jejich otázky jako otevřený JSON v1. Import nejprve ukáže náhled a po potvrzení vytvoří nové koncepty. Stávající obsah a QR zachovává. [Postup učitele](docs/customer/10-PRENOS-OBSAHU.md), [technický formát a hranice](docs/CONTENT-FORMAT.md). Přenos vyžaduje oprávnění správce, platné nonces a potvrzení náhledu; výsledky, údaje lidí, kategorie, soubory a externí URL nejsou součástí.

Přenos navíc ověřuje 	ests/content-wordpress-integration.php (50 kontrol na skutečném WordPressu/MariaDB) a 	ests/content-wordpress-browser.cjs (11 kontrol přihlášené administrace v Edge). Souhrn: [testy přenosu a celé integrace](docs/test-evidence/2026-10-05-content-transfer.json).
