# Hlasuj! by MiloslavHub

## GitHub a distribuce

Projekt používá [CZudla/miloslavhub-hlasuj](https://github.com/CZudla/miloslavhub-hlasuj). Větev `main` uchovává ověřený základ 0.8.7, `develop` následný pracovní vývoj. Dva balíčky a PDF jsou v `releases/0.8.7/`. Postup práce a obnova původní historie jsou v [docs/GITHUB.md](docs/GITHUB.md).

**0.8.7 — opravné vydání před širším Pilot Foundation.**

Lokální rozpracovaná migrace GPT‑6 a vypnutý AI pilot: [stav migrace](docs/GPT-6-MIGRATION-STATUS.md), [popis pilotu a testů](docs/AI-PILOT.md). Tyto změny zatím nejsou součástí nasazeného vydání 0.8.7.

Rozsah, provedená ověření a zbývající omezení jsou v docs/RELEASE-0.8.7.md. Stav konkrétního nasazení dokládá samostatná deployment zpráva; sestavení ZIPu samo nasazení nepotvrzuje.

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

Node musí mít dostupný balíček `playwright` (případně přes `NODE_PATH`). Testy založí dočasný server pouze na `127.0.0.1`, používají syntetická data a po dokončení jej ukončí. Výsledky a screenshoty zůstávají v ignorované složce `runtime/`. Testy WordPress kontraktů používají testovací objekty; dodatečný tests/wordpress-integration.php ověřuje 19 scénářů na skutečném WordPressu a MariaDB v izolovaném prostředí. Zátěžový test zatím není součástí ověření.

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
python scripts/build_release.py --output ../../outputs/0.8.7
```

Sestavení odmítne nečistý checkout, citlivé názvy souborů, zastaralý frontend manifest a kód změněný od úspěšných testů. ZIP obsahuje přesný seznam souborů s hashi, commit a důkazy testů. Neobsahuje produkční config ani DB. Sestavení 0.8.7 vyžaduje také úspěšný integrační test a doklad obnovení obou databází.
