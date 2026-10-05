# Změny

## 0.8.8 — 2026-10-05 — souběh hlasování

- Kvalifikována transakční ochrana příjmu hlasu a změn stavu běhu/otázky; bez automatické DB migrace.
- Přidán místní test souběžných dávek 30 a 100 odpovědí s kontrolou duplicit a uzavření.
- Sjednocena metadata sestavení, vyloučeno vkládání starých releases do zdrojů nových ZIPů; aktualizovány zákaznické příručky a marketing.
- Volitelný AI pilot zůstává vypnutý, bez placených API volání. Stav nasazení uvádí DEPLOYMENT-2026-10-05.md.

## Rozpracováno — 2026-10-05 — vývojová větev

- Projektová volba GPT‑6.1 Sol při zachování úrovně high a projektové instrukce.
- Ve výchozím stavu vypnutý AI pilot pro správce: přeformulování otázky, překlad cs/en, kontrola odesílaného obsahu, náhled a potvrzení návrhu.
- Samostatné ověření oprávnění, limitů, chyb poskytovatele a ochrany novějších úprav v editoru; syntetická hodnoticí sada.
- Navíc ověřeno 19 původních WordPress/DB scénářů a 21 nových AI integračních kontrol na WordPressu 7.1.2/MariaDB 11.4.9. Provider HTTP je nahrazen syntetickými odpověďmi.
- Opraven souběh hlasu s uzavřením/expirací/opakováním otázky společnou transakcí a zámkem běhu. Pro nové DB výslovně určeno InnoDB; existující DB se automaticky nekonvertuje.
- Dne 5. 10. prošlo také 28 kontrol souběhu v nezávislých PHP procesech a 13 kontrol celého AI panelu ve skutečné WordPress administraci v Edge. Přidán opakovatelný runner izolovaného prostředí.
- Bez placených volání API a bez nasazení. Skutečná kvalita modelů a zátěž skupiny zůstávají neověřené; podrobnosti v docs/AI-PILOT.md.

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
