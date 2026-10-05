# Předání autorovi

## Co je hotové v této iteraci

Audit se strukturou A–M, zdrojový Git repozitář, opravná implementace 0.8.7, testy, ověřená obnova záloh, produkční nasazení, devět českých dokumentů pro zákazníka, společná PDF/HTML příručka, produktový list, marketingové texty a upravitelný banner. Dva výsledné ZIPy mají vlastní seznam souborů a kontrolní součty.

Za aktuální stav vydání považujte RELEASE-0.8.7.md a DEPLOYMENT-2026-10-02.md. AUDIT.md zachycuje výchozí audit a historické doklady. MASTER-SPEC.md a centrální registr jsou zadání směru; jejich přítomnost neznamená implementaci každého požadavku.

## Autorský balíček

Obsahuje zdrojové soubory, Git bundle s historií, testy, technickou dokumentaci, plán, audit, doklady ověření, zákaznické příručky a marketingové zdroje. Git bundle lze obnovit příkazem `git clone hlasuj-history.bundle hlasuj`. Konfiguraci a hesla doplňte odděleně, nikdy z balíčku.

## Zákaznický balíček

Obsahuje frontend, instalační ZIP WordPress pluginu, dokumentaci pro provoz, české manuály, referenční příručku, FAQ, informace o soukromí/licencích, produktový list, texty a aktuální testovací screenshoty. Neobsahuje vaše přístupy, databáze, Git historii ani interní hlavní zadání.

Zákaznický instalační ZIP je připraven pro technické předání. Neuzavírá za autora chybějící obchodní smlouvu, licenci vlastního frontendu, cenu, SLA nebo pravidla podpory. Tyto body jsou v balíčku výslovně uvedené.

## NOW / NEXT / LATER

### NOW — další bezpečná iterace

Krátký pilot s jedním učitelem a testovací skupinou, sepsání skutečných problémů ovládání, revize textů a zákaznických instalačních kroků na oddělené instalaci. Doplnit schválenou licenci vlastních částí a podmínky předání. Založit vzdálený Git repozitář, až autor určí jeho umístění a přístupy. Nepřepisovat historické výsledky ani QR.

### NEXT

Navrhnout oprávnění a vlastnictví obsahu před více učiteli, omezit přístup k výsledkům podle jasné politiky, ověřit souběžné hlasování/uzavření a limity hostingu. Připravit návrh domácího úkolu s pedagogickým UI: Ve výuce / Jako domácí úkol, Odevzdat do, pokusy, správná odpověď a vysvětlení po odevzdání. Samostatné řešení studentem musí mít vlastní průběh. Návrh má pokrýt jednu otázku i sadu, výsledky, bodování, soukromí a export/import.

### LATER

Implementace domácích úkolů po schválení modelu, plné cs/en rozhraní, obsahový přenos mezi instalacemi, oddělené organizace, stabilní veřejné integrační API a sjednocení celé grafické galerie. Po každé etapě zopakovat potřebné testy a sladit verze webu, balíčků, dokumentace a marketingu.

## Co je potřeba od autora před další implementací

Priorita následující iterace, pravidla přístupu učitelů a organizací, rozhodnutí o zobrazování a uchovávání výsledků, licenční a obchodní podmínky vlastních částí. Pro domácí úkoly zejména identita studenta, počet pokusů, termín, chování po termínu a okamžik zpětné vazby. Technické rutinní opravy lze připravovat průběžně; změnu těchto produktových pravidel nelze spolehlivě odhadnout.

## Zálohy a rizikové zásahy

Před další produkční aktualizací znovu zálohovat obě DB, WordPress s médii, frontend, plugin a konfigurace; ověřit obnovu. Rizikové jsou změny schématu, přepis identifikátorů/QR, mazání historických dat, změny bodování do minulosti, migrace oprávnění a domény, hromadný import a obnova staré DB přes nové hlasy. Před takovým krokem je potřeba konkrétní plán zachování dat a návratu.
