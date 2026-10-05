# Změny

## 0.8.7 — 2026-10-02 — opravné vydání

Přebírá níže uvedené opravy z pracovního 0.9.0-dev.1. Doplněno 19 integračních kontrol na WordPressu 7.1.2 / MariaDB 11.4.9, export a lokální obnova obou produkčních databází (zdroj MariaDB 10.11.18). Schéma zůstává 0.8.5, produkční migrace se neprovádějí. Verze 0.8.7 označuje omezený opravný rozsah; širší Pilot Foundation 0.9 zůstává rozpracovaný. Stav nasazení je v deployment zprávě.

## 0.9.0-dev.1 — 2026-10-02 — lokální vývojová verze

- Živou a testovací otázku smí spustit administrátor WordPressu. Studentské načtení QR a čekání žádnou otázku neotevírají. Dlouhodobé ankety `async` a oddělené prezentační demo mají zachované samostatné chování.
- Učitelský panel označuje hlavní akci „Spustit hlasování“ a opakování „Zopakovat otázku“. Staré joining relace lze otevřít ručně.
- Opravena projekční cesta a zapojení voleb Síně slávy v mobilních výsledcích.
- Demo respektuje jednotlivé volby zveřejnění, při návratu je zachovává a první volba neukončí ostatní účastníky. Veřejné výsledky neobsahují odmítnuté/neodsouhlasené přezdívky.
- CSV export chrání textové buňky před interpretací jako vzorec.
- QR frontendu a dema se vykresluje lokálně s přiloženou MIT licencí. Demo odkazy vedou na aktuální instalaci.
- Chybějící frontend config vrací HTTP 503. Neexistující demo relace nezanechává lock soubor.
- Opraveny texty o řízení výuky, automatickém demu a době úklidu. Historická marketingová galerie je viditelně označena.
- Schéma zůstává 0.8.5; žádné migrace. Verze schématu je oddělena od verze pluginu.
- Přidán Git základ, audit, dokumentace, regresní testy a sestavení vývojového zdrojového archivu.

Neobsahuje plnohodnotné domácí úkoly, nový import obsahu, multi-teacher autorizaci ani kompletní cs/en UI. Nejde o dokončený stabilní Pilot Foundation ani o schválené produkční vydání.
