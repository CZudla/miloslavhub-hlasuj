# GitHub projektu Hlasuj!

Oficiální repozitář: https://github.com/CZudla/miloslavhub-hlasuj.

## Větve a vydání

- `main`: zdrojový základ ověřeného vydání 0.8.7, dokumentace a podklady předání.
- `develop`: pracovní vývoj po 0.8.7, včetně místního AI pilotu a rozpracovaných změn souběhu hlasování. Tato větev se automaticky nenasazuje.
- `release/0.8.7`: pojmenovaný zdrojový snímek původního vydání.

Nasazený aplikační commit z původní místní historie je `40dce6213af07b01f4f9f2bf428786709979c109`; předání dokumentace bylo dokončeno v `d6bc71d`. GitHub import má vlastní commity. Původní historie je zachována v `releases/history-before-github.bundle` a v autorském ZIPu. Úplnou místní historii lze obnovit pomocí `git clone history-before-github.bundle hlasuj-history`.

Distribuce jsou v `releases/0.8.7/`: autorský ZIP, zákaznický ZIP, české PDF příručky, produktový list a kontrolní součty. Jsou to nezměněné historické soubory předání. Nová verze musí mít vlastní vydání a ověření. Jednotlivé upravitelné manuály jsou v `docs/customer/`, marketingové podklady v `docs/marketing/`.

## Práce z místního projektu

Aktivní repozitář sdílené složky je `work/hlasuj`; `origin` směřuje na GitHub výše. Před prací číst AGENTS.md a zkontrolovat `git status`. Pracovní změny vznikají ve větvi `develop` nebo ve vlastní větvi od ní. Výchozí větev `main` zachycuje kvalifikovaný základ; změna kódu pro nové vydání vyžaduje odpovídající ověření a aktualizaci dokumentace.

Prvotní přenos byl proveden přes připojení GitHub v Codexu. Čtení přes Git je dostupné veřejně. Pro běžný terminálový `git push` je potřeba vlastní přihlášení Git Credential Manager nebo SSH. Přístupový token neukládat do remote URL, projektové konfigurace ani souborů repozitáře.

## Co patří do repozitáře

Zdroje, testy se syntetickými daty, instalační SQL schéma a historické migrace, dokumentace, licenční oznámení, marketingové podklady a ověřené distribuce. Samotné uložení migračního souboru není pokyn k jeho spuštění.

Produkční config.php/wp-config.php, hesla, API klíče, cookie, SQL exporty provozních databází, obnovená data, hostingové zálohy, logy a lokální testovací runtime se nezveřejňují. Repo je veřejné. Před commitem ověřit obsah změn a ignorované cesty; `.gitignore` nenahrazuje kontrolu hodnot v běžných zdrojových souborech.

Publikace do GitHubu nemění webový server ani databázi. Nasazení a zálohy mají vlastní postup v DEPLOYMENT.md a UPGRADE.md.
