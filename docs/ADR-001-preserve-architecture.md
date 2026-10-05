# ADR 001: zachovat PHP/WordPress a nejprve uzavřít lokální bezpečnostní opravy

Stav: pracovní rozhodnutí pro 0.9.0-dev.1, 2026-10-02.

Zdrojové soubory a datové schéma existují a frontend má ověřenou shodu s archivem. Přepis na nový framework by nyní zhoršil možnost porovnat opravy a udržet existující QR. Proto zachováváme komponenty, URL a DDL; opravujeme konkrétní chyby a zavádíme testy.

Schéma má samostatnou verzi 0.8.5. Aplikace 0.9.0-dev.1 nepředstírá migrační změnu. Vývojový ZIP je jasně odlišen od stabilní zákaznické distribuce.

Důsledek: dědíme technický dluh, veřejné výsledkové API a admin-only ovládání. Před rozšířením o účty a domácí úkoly vytvoříme model vlastnictví, autorizace a verzování přenosného obsahu. Před produkcí potřebujeme reálnou integraci a ověřenou obnovu.
