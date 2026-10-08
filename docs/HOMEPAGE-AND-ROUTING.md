# Domovská stránka a směrování Hlasuj!

## Veřejné adresy

| Adresa | Účel |
|---|---|
| https://hlasuj.miloslavhub.cz/ | Kanonická produktová domovská stránka |
| https://hlasuj.miloslavhub.cz/docs/ | Český rozcestník příruček |
| https://hlasuj.miloslavhub.cz/docs/en/ | Anglický rozcestník příruček |
| https://miloslavhub.cz/hlasuj/ | Krátký alias na kanonickou domovskou stránku |
| https://github.com/CZudla/miloslavhub-hlasuj | Zdroje, dokumentace, testy a doložené distribuce |

Před touto úpravou vracel 8. 10. 2026 kanonický host HTTP 200, krátký alias HTTP 404. Aktuální výsledek změny musí doložit samostatná zpráva a HTTP kontrola; samotný tento návod není důkazem nasazení.

## Soubory a reprodukce

`frontend/index.php` obsahuje produktovou stránku. `frontend/docs/` obsahuje statický česko-anglický rozcestník s místním CSS, bez analytiky a externích fontů. Odkazuje na veřejné verzované příručky v GitHubu a na zachované PDF/balíčky 0.8.9. Úplný obsahový index je [INDEX.md](INDEX.md).

```text
python scripts/build_documentation_site.py
python scripts/refresh_manifest.py --status unreleased-development
```

Řízený katalog sestavení vyžaduje existenci všech zdrojových příruček. `frontend/docs/site-manifest.json` eviduje hashe zdrojových dokumentů a vygenerovaných webových souborů. Dokumenty se zveřejňují ve stejném commitu jako rozcestník. Při další úpravě katalogu nebo zdrojových příruček rozcestník znovu sestavte.

## Apache a .htaccess

Frontendový `.htaccess` již posílá neexistující cesty do `index.php` a zachovává skutečné soubory/adresáře. Pro hlavní stránku jej není potřeba přepisovat. Podadresář příruček výslovně používá `DirectoryIndex index.html`, zakazuje listing a přidává omezenou CSP pro statický obsah.

Krátký alias patří do sdíleného kořenového `/www/.htaccess`, po prvním `RewriteEngine On` a **mimo dynamický blok WordPressu**. Přesný bezpečný fragment je [deploy/apache/hlasuj-alias.conf](../deploy/apache/hlasuj-alias.conf). Plný sdílený soubor obsahuje konfiguraci dalších služeb a není součástí veřejného repozitáře.

Pravidlo zachytí pouze `/hlasuj` a `/hlasuj/` na `miloslavhub.cz` nebo `www.miloslavhub.cz`. Vrací dočasný HTTP 302 na kanonický HTTPS host; query string zůstává zachovaný. Dočasný redirect umožňuje návrat bez dlouhodobě uloženého 301 v klientských cache. Nezachytává `/hlasuj/jina-cesta`, jiné subsystémy ani studentské `/q`, `/r`, `/test`, `/poll` a projekční adresy.

`scripts/patch_home_routing.py` vloží fragment do kopie existujícího souboru, zachová původní bajty a odmítne duplicitní či nečekaný vstup. Nevytváří náhradu celé konfigurace hostingu. Stejný skript může doplnit odkazy na příručky do ověřené kopie produkční domovské stránky bez převzetí ostatního nevydaného frontendu.

## Izolované nasazení informačního webu

1. Stáhnout aktuální kořenový `.htaccess`, produkční `index.php`, frontend manifest a `release.json` do chráněné souborové zálohy. Zaznamenat jejich SHA-256.
2. Připravit kopie souborů. Zkontrolovat, že odstranění vloženého aliasu či odkazů vrací přesně původní bajty. Ověřit PHP syntaxi, český/anglický rozcestník, lokální odkazy, šířku mobilu a skutečný rozsah změn.
3. Publikovat bezpečné zdroje, dokumentaci a testovou evidenci do GitHubu. Bezprostředně před přepnutím znovu stáhnout dotčené soubory a porovnat s chráněnou zálohou. Při driftu znovu připravit patch; nepřepsat cizí úpravy.
4. Nahrát nejprve nové statické příručky, pak přesný alias a odkazy domovské stránky. Aktualizovat pouze související souborový manifest a samostatný doklad webové revize. Zachovat aplikační `release.json`, plugin, konfigurace, hlasování a obě databáze.
5. Ověřit HTTP 200 domovské stránky i obou jazyků příruček; 302 a jediný přechod krátkého aliasu, query string, absenci smyčky a nezměněné odpovědi hlavního WordPressu, privacy, ukázkové QR stránky a hlavních assetů. Stáhnout nasazené soubory a porovnat jejich hashe. Žádné produkční hlasování se při tomto ověření nespouští.

Tato úprava nevyžaduje SQL export/import, protože mění jen veřejné informační soubory a směrování. Plné vydání aplikace má nadále vlastní požadavek společné zálohy obou DB a souborů, obnovy a funkční kvalifikace.

## Návrat a rizika

Při HTTP 500, smyčce nebo dopadu na jinou službu vrátit původní `.htaccess` a dotčený veřejný soubor z čerstvé souborové zálohy. Před obnovou ověřit, že server stále obsahuje právě nasazenou variantu; při mezitím vzniklé cizí změně neobnovovat starý celý soubor naslepo. Nový statický podadresář může zůstat, pokud je funkční a bezpečný; jeho existence není změnou databáze.

Největší riziko je sdílený kořenový `.htaccess`: chyba syntaxe nebo příliš široká podmínka může ovlivnit více služeb. Proto se publikuje pouze úzký fragment, změna se hlídá proti driftu a výsledek se testuje na skutečném Apache. Místní PHP server pravidla Apache neověřuje.

Po této webové úpravě aplikační verze nadále označuje kvalifikovanou baseline. Samostatný webový doklad určuje změněné soubory a commit. Nevydaný vývoj organizací/AUTH/licencí se při tom neinstaluje.
