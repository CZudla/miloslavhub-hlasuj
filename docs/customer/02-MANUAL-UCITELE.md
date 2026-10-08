# Manuál učitele

Hlasuj! by MiloslavHub · 0.8.9

## 1. Přístup a příprava

Učitel ovládá systém ve WordPress administraci. V této verzi potřebuje účet s oprávněním `manage_options`, obvykle správce. Zápis jména do pole Vyučující sám oprávnění k ovládání nepřidává. Přístup přiděluje správce instalace.

V menu **Živé hlasování** najdete Přehled, Živé ovládání, Testovací laboratoř, Ukázkové demo, Dlouhodobé ankety, Archiv výsledků a Nastavení. Obsah tvoří předměty, přednášky a otázky. Předmět představuje kurz nebo semestr; přednáška obsahuje uspořádaný výběr otázek.

## 2. Vytvořte obsah

1. Vytvořte a publikujte předmět. Vyplňte název a případně krátký název, kód či skupinu. Pro běžnou výuku zvolte standardní rozvržení.
2. Vytvořte otázku. Název obsahuje zadání, jednotlivá pole texty odpovědí. Nabídka editoru má možnosti A–F; prázdná pole se při zpracování vynechávají.
3. Pro anketu ponechte **Žádná správná odpověď**. Pro kvíz označte jednu správnou možnost.
4. Nastavte **Časový limit hlasování**. Volba Automaticky podle typu používá nastavení instalace; výchozí kvíz má 30 sekund a anketa nemá limit. Pro první zkoušku lze zvolit 60 sekund.
5. Otázku publikujte. Připravte další otázky stejným způsobem.
6. Vytvořte přednášku, přiřaďte předmět a přidejte otázky do seznamu Vybrané. Pořadí měníte přetažením nebo šipkami. Přednášku publikujte.

**Příklad:** otázka „Které tvrzení nejlépe popisuje dědičnost?“ s jednou správnou odpovědí je kvíz. Otázka „Které téma si máme zopakovat?“ bez správné odpovědi je anketa.

## 3. Rozhodněte o soutěži a výsledcích

**Soutěžní režim** zapíná přezdívky, body a pořadí. Předem studentům řekněte, kde se jejich přezdívka může objevit. Souhlas se Síní slávy se vztahuje právě na Síň slávy; neřídí automaticky všechny soutěžní tabulky.

Volba **Celkové pořadí** určuje součet v předmětu, v přednášce nebo žádné celkové pořadí. **Průběžné výsledky** doporučujeme při samostatném rozhodování studentů vypnout, aby první odpovědi neovlivňovaly další.

**Nevydaný vývoj od 8. 10. 2026:** anketa soutěžní body nepřiděluje ani při starším nastavení. Editor již nenabízí **Body za účast**. Historické výsledky se nepřepočítávají. V kvalifikovaném vydání 0.8.9 toto nastavení ještě existovalo; u této verze je ponechte na nule. Podrobnosti aktuálního vývoje: [Ankety](12-ANKETY.md).

## 4. Vyzkoušejte výuku předem

V **Testovací laboratoři** spusťte TEST připravené přednášky. Otevřete studentský pohled na telefonu a projekci na druhé obrazovce. Také v testu otázku spouští učitel. Tlačítko **Simulovat 5 studentů** je dostupné u otevřené testovací otázky; používejte je pouze při zkoušce.

Testovací odkazy mají jiný režim než živé odkazy. Do ostré prezentace vložte odkazy pro živé hlasování. Samostatné veřejné demo na `/demo/` vede účastníka automaticky a neověřuje vaši vlastní přednášku.

## 5. Průběh hodiny

1. Otevřete **Živé ovládání** a u připravené přednášky zvolte **Spustit přednášku**.
2. Zobrazte QR nebo sdílejte studentský odkaz. Pro projekci můžete použít tlačítko **Projekce** u otázky. Odkaz **Student** otevře telefonní pohled.
3. Dejte studentům čas na připojení. Naskenování QR ani zadání přezdívky otázku nespouští.
4. Až budete připraveni, klikněte u otázky na **Spustit hlasování**. Od tohoto okamžiku běží společný časový limit.
5. Hlasování ukončí časový limit nebo tlačítko **Ukončit**. Pozdní připojení čas neobnovuje.
6. Proberte výsledky. U kvízu se správnost odpovědi zpřístupní po uzavření.
7. Spusťte další připravenou otázku. Na konci zvolte **Ukončit přednášku**.

Spuštění nové přednášky stejného předmětu ve stejném režimu může uzavřít předchozí aktivní běh. Při sdílení instalace se s kolegy domluvte, kdo právě ovládá daný předmět.

## 6. Opakování a trvalé QR

**Zopakovat otázku** vytvoří další hlasovací relaci pro danou otázku. Použijte je vědomě, například po vysvětlení látky; studenti mohou odpovídat v novém pokusu. Předchozí záznamy se nemažou. Opakování má vliv na historii a případné součty, proto nenahrazuje běžné obnovení stránky.

Trvalé QR adresy vycházejí z uložených identifikátorů. Přejmenování otázky a změna pořadí nemají měnit původní odkaz. Smazání obsahu nebo změna domény může dostupnost narušit. Odkazy kopírujte ze systému, neskládejte je ručně.

## 7. Dlouhodobá anketa

U anketní otázky povolte **Dlouhodobá otevřená anketa**, případně nastavte **Automaticky uzavřít** a **Po hlasování zobrazit průběžné výsledky**. Otázka musí patřit do publikované přednášky. Odkaz **Respondent** najdete v Dlouhodobých anketách.

Respondenti hlasují samostatně. Dlouhodobá anketa neběží podle společného učitelského odpočtu. Uzavřít ji lze v administraci. Tato funkce nemá nastavení počtu pokusů, vysvětlení po odevzdání ani práci se sadou domácích úkolů.

## 8. Export po výuce

Otevřete **Archiv výsledků**, najděte živou relaci a klikněte na **CSV**. Soubor obsahuje jednotlivé hlasy včetně přezdívky, odpovědi, správnosti, času a bodů. Uchovávejte jej v chráněném úložišti; neposílejte ho veřejně.

CSV se otevírá jako UTF-8 s oddělovačem středník. Text začínající znakem vzorce je chráněn apostrofem. Export výsledků není přenosem otázek ani úplnou zálohou aplikace.

## 9. Sdílení obsahu s kolegou

V **Přenést obsah** stáhnete předmět s přednáškami a jejich otázkami jako JSON. V cílové instalaci zobrazíte náhled a potvrdíte vytvoření nových konceptů. Původní obsah se zachová. Soubor obsahuje správné odpovědi a neslouží ke sdílení se studenty. Výsledky, údaje lidí, kategorie, termíny, externí odkazy a původní QR se nepřenášejí. Podrobný postup a nastavení před zveřejněním jsou v kapitole Přenos předmětu mezi instalacemi.

## Volitelná pomoc AI

Správce může samostatně povolit pomoc s přeformulováním a překladem otázky. Ve výchozím nastavení je vypnutá. Zapnutý panel nejprve ukáže text určený k odeslání, potom návrh; použití návrhu v editoru potvrzujete sami. Běžné uložení otázky je další samostatný krok. Výsledky studentů se neposílají. Tato aktualizace placeného poskytovatele nezapíná.
