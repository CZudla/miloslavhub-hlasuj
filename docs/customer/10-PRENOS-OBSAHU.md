# Přenos předmětu mezi instalacemi

Hlasuj! by MiloslavHub · 0.8.9

## Sdílení s kolegou

1. Otevřete **Živé hlasování → Přenést obsah**.
2. Vyberte předmět a klikněte na **Stáhnout obsah**.
3. Uložte soubor `predmet.hlasuj.json`. Obsahuje přednášky předmětu a jejich otázky, včetně správných odpovědí. Sdílejte jej pouze s určenými vyučujícími.
4. Před odesláním zkontrolujte vlastní texty. Mohou obsahovat osobní údaje nebo informace, které nemají opustit vaši instalaci.

Export nepřenáší výsledky studentů. Pro výsledky použijte samostatný CSV export v Archivu výsledků. JSON není úplná záloha aplikace.

## Převzetí obsahu

1. V cílové instalaci otevřete **Přenést obsah** a vyberte JSON soubor do 2 MiB.
2. Klikněte na **Zobrazit náhled**. Zkontrolujte název, přednášky a počet otázek. Náhled ještě nevytváří výukový obsah.
3. Klikněte na **Vytvořit nové koncepty**. Náhled platí 15 minut a může být potvrzen pouze jednou. Nový náhled nahrazuje předchozí.
4. Zkontrolujte otázky, správné odpovědi, časové limity a bodování. Sdílená otázka zůstává jednou otázkou přiřazenou k více přednáškám.
5. Doplňte vyučující, kategorie, materiály, případné vlastní logo a odkazy. Pokud potřebujete dlouhodobou anketu, znovu ji vědomě povolte a nastavte termín.
6. Zveřejněte nejprve otázky a předmět, potom přednášky. Před použitím spusťte TEST a zkontrolujte projekci i studentský pohled.
7. Použijte nové QR odkazy cílové instalace. Původní QR nadále odkazují na původní obsah; jejich adresy import nemění.

V této verzi pracuje s přenosem správce s oprávněním `manage_options`. Jméno v seznamu vyučujících oprávnění nepřidává.

## Co se přenáší

| Obsah | Rozsah |
|---|---|
| Předmět | Název, krátký název, kód, období, texty a podporovaná nastavení značky a Síně slávy |
| Přednášky | Názvy, vazba na předmět, pořadí otázek, soutěžní režim, rozsah pořadí, průběžné výsledky |
| Otázky | Zadání, odpovědi, správná možnost, násobitel, rychlostní okno, body ankety, čas, RAG politika a volba zobrazení anketních výsledků |
| Sdílené otázky | Jedna kopie otázky se zachovanými vazbami a pořadím |

**Nepřenáší se:** účty ani údaje vyučujících, přezdívky, hlasy, výsledky, projekční tokeny, původní ID a QR adresy, kategorie, přiložené soubory, externí URL, termíny, povolení dlouhodobé ankety ani globální nastavení instalace. Samostatné otázky, které nejsou přiřazené k přednášce vybraného předmětu, nejsou součástí exportu. Dosud neimplementovaná vysvětlení a domácí úkoly tento formát neobsahuje.

Automatický čas používá nastavení cílové instalace. Základní body a rychlostní bonus jsou rovněž nastavením cílové instalace. Přenesený násobitel proto sám nezaručuje totožný počet bodů na dvou serverech.

## Když přenos neprojde

Nepodporovaná verze, neplatné typy, neznámá pole, duplicitní ID a chybné vazby jsou odmítnuty před vytvořením obsahu. Jedna dávka podporuje nejvýše 100 přednášek, 500 různých otázek a 2–26 odpovědí u otázky. Rozsáhlejší předmět rozdělte; případné přesahy projednejte se správcem.

Při zachycené chybě ukládání aplikace odstraní právě vytvořené koncepty. Pokud jejich odstranění selže, zobrazí potřebu kontroly správcem. Přerušení serveru nebo nedostatek paměti může zanechat část konceptů s adresou začínající `import-`; správce je musí zkontrolovat před novým pokusem. Přenos nemá přepisovat existující obsah.

Pokud hlášení „Přenos již probíhá“ přetrvává i po skončení serverového požadavku, správce ověří, že neběží další import. Až poté může odstranit konkrétní pomocnou WordPress option `mhl_content_lock_{ID_účtu}`. Zámek se automaticky nepřebírá, aby dvě operace nevytvářely obsah současně.

Formát je otevřený a verzovaný: `format = hlasuj-content`, `format_version = 1`. Technická smlouva a příklad jsou v autorském balíčku v `docs/CONTENT-FORMAT.md`. Soubory se nerozbalují jako ZIP a aplikace při importu nic nestahuje z externích adres.
