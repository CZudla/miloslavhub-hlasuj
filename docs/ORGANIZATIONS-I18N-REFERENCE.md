# Organizace, učitelé a jazyky — vývojová reference

Stav 8. 10. 2026, nevydaná větev `feature/requirements-feedback`. Release metadata stále označují kvalifikované vydání 0.8.9 se schématem hlasovací DB 0.8.5. Tento dokument nepotvrzuje produkční nasazení ani funkční živé SSO/licencování. Domácí úkoly jsou podle aktuálního zadání mimo rozsah.

## Místní oprávnění aplikace

`MHL_Access` používá WordPress účty, soukromý typ `mhl_workspace`, členství organizace a explicitní sdílení objektů. Učitel dostává schopnost `mhl_access`, bez `manage_options`. Správce organizace nezískává správu WordPressu. Provozovatel WordPressu s `manage_options` má technický přístup napříč instalací; ten musí mít omezený okruh osob.

| Role v organizaci | Čtení obsahu a výsledků | Úpravy a řízení výuky | Správa členství a vlastnictví |
|---|---|---|---|
| Vlastník | Celá vlastní organizace | Celá vlastní organizace | Ano; nelze odebrat posledního vlastníka |
| Správce organizace | Celá vlastní organizace | Celá vlastní organizace | Ano, kromě udělení či odebrání role vlastníka |
| Učitel | Vlastní a výslovně sdílený obsah | Vlastní a sdílený pro spolupráci; vytváření obsahu | Vlastní objekty může sdílet a převádět |
| Spolupracující učitel | Výslovně sdílený obsah | Sdílený pro spolupráci | Bez dalšího sdílení a převodu cizích objektů |
| Pouze čtení | Výslovně sdílený obsah a jeho výsledky | Ne | Ne |

Sdílení předmětu se dědí na jeho přednášky a jejich skutečně přiřazené otázky ve stejném prostoru. Sdílení samotné přednášky se dědí na její přiřazené otázky. Přímé vlastnictví otázky může existovat souběžně s přístupem získaným přes předmět. Převod vlastníka otázky proto sám neodvolá jiná platná sdílení. Zrušení členství odvolá přístup i k dříve vlastněným objektům organizace; jejich obsah zůstane zachovaný.

Výběr prostoru řídí seznamy, souhrny a přiřazení nového obsahu. Historické záznamy bez `_mhl_workspace_id` zůstávají osobní výukou původního autora. Hromadný převod starého obsahu do organizace tato etapa neprovádí. Reference externí organizace je místní vazební údaj; nenahrazuje centrální autoritu ORGANIZATIONS ani důkaz členství.

## Serverové hranice

- Oprávnění objektu se kontroluje u nativní editace, REST aktivace, řízení relace, simulace a exportu výsledků. Správa dotazů filtruje povolená ID před stránkováním; samotné skrytí tlačítek není ochrana.
- Přímá změna autora, prostoru či sdílení přes běžný editační formulář nemění vlastnictví. Vazby přednášky na cizí předmět či otázky z jiného prostoru se odmítají.
- Členství, sdílení a převod vlastníka používají transakci se zámkem záznamu. Úložiště musí podporovat InnoDB. Poslední vlastník se zachovává i při souběžných požadavcích na stejnou organizaci.
- Mazání testů používá právo ovládat výuku. Právo číst sdílené výsledky neopravňuje k jejich smazání.
- Veřejné studentské QR a namespace `mhl/v1` zůstávají zachované. Uveřejněné výsledky podle slugů zůstávají veřejné podle dosavadního kontraktu. Místní izolace administrace z nich nedělá neveřejné výsledky.
- Globální WordPress taxonomy nejsou v této etapě novou organizační autoritou. Učitelům se neuděluje obecné `manage_categories` ani obecná editace příspěvků WordPressu.

## Počítání licenčních míst

Potvrzené zadání: limit je společný celé organizaci; započítávají se všechny účty s právy k výuce. `MHL_Access::teaching_accounts()` vrací unikátní místní ID vlastníků, správců, učitelů a spolupracujících učitelů s aktivní schopností aplikace. Prohlížení a studentské hlasování místo nespotřebovávají; počet sdílených objektů výpočet nezvyšuje.

Tato funkce **nevydává licenci a nevynucuje externí kvótu**. Autoritativní limit, vazba na stabilní identitu AUTH, rezervace místa při souběhu a čerstvé ověření oprávnění vyžadují kvalifikovaný kontrakt LICENSE/ENTITLEMENTS. Technický provozovatelský přístup není důkazem licenčního nároku k výuce. Výpadek zdrojové služby se nesmí převést na neomezenou licenci. Žádná tato chyba nesmí smazat obsah.

## Čeština a angličtina

Veřejné rozhraní používá `?lang=cs` a `?lang=en` a místní cookie `mhl_ui_lang` s platností jednoho roku, HttpOnly a SameSite=Lax; na HTTPS také Secure. Administrace se řídí jazykem WordPress profilu. REST dostává `ui_lang` pro vlastní ovládací a chybové zprávy. Jazyk neurčuje identitu ani oprávnění.

Katalog `frontend/lang/en.json` se dodává shodně v pluginu. PHP a JavaScript překládají pouze výslovně označené zdrojové texty rozhraní. Dynamické názvy otázek, odpovědi, vysvětlení, poznámky a přezdívky se nepřekládají. Učitel může psát vlastní obsah v libovolném jazyce. Samostatné webové demo má výslovně připravenou českou i anglickou variantu vlastních tří ukázkových otázek; jejich ID, odpovědní kódy a bodování se jazykem nemění. Nejde o automatický překlad učitelského obsahu.

PHP vybírá nejdelší shodu nad původním textem pomocí omezených regulárních výrazů a skládá výstup jednou. Nevzniká opakovaný překlad již přeložených slov. HTML překlad escapuje nové hodnoty a zachovává značky a oddělené dynamické hodnoty. JSON katalog v HTML používá hex escaping. Čísla ve studentském rozhraní respektují jazyk.

Obsahový JSON v2 zůstává nezávislý na jazyku ovládání. Účty, členství, místní ID, ACL a licenční nároky se exportem nekopírují. Import vytváří nové koncepty do vybraného prostoru a kontroluje právo vytvářet obsah.

Export výsledků používá oprávnění k jejich čtení. Přenos zdrojového obsahu předmětu kontroluje právo editovat každý exportovaný objekt; samotné čtení výsledků neopravňuje k exportu obsahu. Anglický [učitelský manuál](customer/en/USER-GUIDE.md) popisuje obě operace samostatně.

## AUTH a licenční aktivace

Místní WordPress přihlášení není centrální AUTH. Aktuálně není doložena kvalifikovaná registrace klienta Hlasuj!, důkaz aktuálního stavu účtu a MFA ani povolený zdrojový commit pro licenční rozhodnutí. Datované referenční kopie sousedních systémů nejsou oprávněním otevřít jejich produkční brány.

Před aktivací je nutné doložit přesný callback a registraci klienta, svázání `(issuer, sub)` s místním účtem bez slučování podle e-mailu, minimální claims, aktuální stav účtu a odvolání, session-bound MFA pro privilegované operace, autoritativní licenci a souběžnou rezervaci organizačních míst. Každý chybějící či neplatný důkaz musí odmítnout chráněnou operaci. Browser, boolean, ID token či místní role samy takový důkaz netvoří.

Lokální testy oprávnění a překladů nenahrazují dvouuživatelské/dvouorganizační E2E se skutečnými poskytovateli, jejich výpadkem a revokací. Do té doby nelze celý požadovaný systém označit za hotový a integrovaný.

## Upgrade a návrat

Tato etapa nepřidává SQL DDL hlasovací databáze. Po inicializaci plugin vytvoří místní roli a verzi role v `wp_options`; další změny vznikají až při výslovné správě organizací a obsahu. Proto ani nasazení bez SQL migrace není bez změn databáze WordPressu.

Před nasazením zálohovat souběžně WordPress DB, hlasovací DB, plugin, frontend, privátní konfiguraci a média. Ověřit jejich obnovu, aktuální původní produkční soubory a plán rollbacku. Prostý návrat starého pluginu by obnovil původní model jediné administrace; před rollbackem je nutné zohlednit nové členství, sdílení a vlastnictví. Nové balíčky a marketing mají popisovat až skutečně kvalifikované vydání.
