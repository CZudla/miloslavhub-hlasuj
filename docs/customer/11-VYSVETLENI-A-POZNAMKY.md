# Vysvětlení odpovědi a moje poznámky

Hlasuj! by MiloslavHub · nevydaný vývoj z 7. 10. 2026. Funkce ještě není součástí doloženého produkčního vydání 0.8.9.

## Připravit vysvětlení ke kvízu

1. V administraci otevřete otázku. Vyplňte odpovědi a označte správnou možnost.
2. Rozbalte **Vysvětlení správné odpovědi** a napište řešení nebo komentář pro následnou diskusi. Pole přijímá obyčejný text; odstavce a řádky se zachovají.
3. V **Kdo uvidí vysvětlení?** vyberte jednu z možností níže.
4. Uložte otázku. Před výukou ověřte TEST, studentský pohled a projekci.

| Volba | Co uvidí studenti a projekce |
|---|---|
| Pouze já | Vysvětlení se neposílá do veřejných výsledků. To je výchozí nastavení. |
| Studenti po ukončení hlasování | Po uzavření kvízu se vysvětlení objeví ve výsledcích studenta i na projekci. Během hlasování se nezobrazí. |
| Nezobrazovat studentům | Vysvětlení zůstává uložené pro učitele a veřejné výsledky je neobsahují. |

Volba zveřejnění zpřístupní vysvětlení každému, kdo má přístup k danému veřejnému odkazu výsledků. Neomezuje čtení pouze na účastníky výuky. Do zveřejňovaného vysvětlení nepatří osobní údaje studentů. Ankety bez správné odpovědi vysvětlení kvízu nezveřejňují.

## Moje poznámka k výuce

Rozbalte **Moje poznámka k výuce**. Do pole **Soukromá poznámka** můžete napsat metodický postup, otázku do diskuse nebo připomínku pro další hodinu. Poznámka není ve studentském API ani na projekci. Je dostupná také u ankety.

Soukromí zde znamená oddělení od studentského a veřejného pohledu. Obsah mohou spravovat oprávnění správci dané WordPress instalace; oddělená oprávnění jednotlivých učitelů a organizací zůstávají dalším krokem vývoje. Učitelé uvedení v popisu předmětu tímto zápisem žádné oprávnění nezískávají.

## Délka, změny a přenos

- Vysvětlení a poznámka mají každé limit 4 000 bajtů UTF-8; u českých znaků jde o méně než 4 000 znaků. Příliš dlouhý text editor zkrátí na platné UTF-8. HTML značky se při uložení odstraní.
- Vysvětlení je aktuálním obsahem otázky. Změna textu nebo pravidla zobrazení se projeví také při opětovném otevření výsledků starší relace. Samostatný neměnný snímek vysvětlení pro každé hlasování zatím není zaveden.
- Obsahový **JSON v2 přenáší také soukromou poznámku**, vysvětlení a zvolené pravidlo. Soubor zkontrolujte a předávejte pouze určenému kolegovi. Nezveřejňujte jej jako studentský materiál.
- Starší JSON v1 lze importovat. Nemá vysvětlení ani poznámku a použije soukromé výchozí nastavení. Import vytváří nové koncepty a ponechá původní QR beze změny.
- Tato funkce je určena pro zpětnou vazbu po živém/testovacím kvízu. Samostatné domácí zadání s termínem a pokusy vyžaduje vlastní dokončení a ověření.

Technické podrobnosti: [reference přenosu](../CONTENT-FORMAT.md), [stav vývoje a testy](../DEVELOPMENT-2026-10-07.md).
