# Facebook: Hlasuj! by MiloslavHub

Připraveno 5. 10. 2026 podle skutečných funkcí vydání 0.8.9.

**Stav: stránka na Facebooku nebyla vytvořena a žádný příspěvek nebyl zveřejněn.** Přístup přes browser-use i computer-use selhal ještě při inicializaci: `failed to write kernel assets: Systém nemůže nalézt uvedenou cestu. (os error 3)`. Reset prohlížečového nástroje a druhý nástroj chybu nevyřešily. Přihlášení, existenci podobné stránky, dostupnost uživatelského jména a aktuální kategorie Facebooku nebylo možné ověřit.

## Připravené nastavení

- Název: **Hlasuj! by MiloslavHub**.
- Navržené uživatelské jméno: **hlasuj.miloslavhub**. Dostupnost ověřit v rozhraní; nejde o rezervovanou adresu.
- Navržená kategorie: **Vzdělávací web**, případně **Software**, podle aktuální nabídky Facebooku.
- Bio, delší popis, web, kontaktní e-mail a texty šesti příspěvků: `STRANKA.json`.
- Profilový obrázek: existující `frontend/assets/brand/hlasuj-icon-512.png` beze změn. Přístupné logo produktu se používá i ve výchozí grafice.
- Nová úvodní grafika a tři obrázky k příspěvkům vznikají ze SVG. Neobsahují univerzitní znaky, skutečné studenty ani výsledky.

Skript `scripts/build_facebook_kit.py` vytvoří kompletní ZIP a HTML náhled pod `outputs/facebook-2026-10-05/`, včetně samostatných TXT příspěvků, SVG/PNG a kontrolních součtů. Ověří veřejné demo a kontaktní e-mail na webu produktu. Upravovat lze JSON a skript; historické distribuční ZIPy aplikace zůstávají zachované.

## Dokončení v přihlášeném Facebooku

1. Obnovit funkční nástroj ovládání prohlížeče. Případné přihlášení nebo ověření účtu provádí uživatel.
2. Ověřit, zda už existuje stránka stejného projektu, aby nevznikla nechtěná duplicita. Ověřit cílový spravující účet.
3. Vytvořit veřejnou projektovou stránku s připraveným názvem, kategorií a bio.
4. Nahrát profil a úvodní obrázek. V náhledu telefonu a počítače upravit ořez. Rozměry souborů představují export grafiky, nikoli garanci zobrazení podle aktuálního rozhraní Meta.
5. Doplnit web, ověřený veřejný kontakt a delší popis. Pokud je dostupné vhodné tlačítko, **Další informace** odkazuje na demo.
6. Zveřejnit uvítací příspěvek a připnout jej, pokud to rozhraní umožňuje. Druhý a třetí příspěvek mohou tvořit úvodní obsah. Zbývající tři jsou připravené do zásoby; bez dalšího požadavku není nastaven časový rozvrh ani automatické publikování.
7. Zkontrolovat veřejnou stránku, skutečné URL, fotografie a publikované příspěvky. Zapsat URL a skutečně provedené kroky do této zprávy.

Úvodní grafika, texty a příspěvky jsou návrh k publikaci v rámci požadavku uživatele. Žádné reklamy, placené propagace, pozvánky kontaktům, přidávání správců ani přímé zprávy nejsou součástí zadání.

## Produktová přesnost

Obsah propaguje dodané ankety/kvízy, učitelské spuštění, QR, projekci, testovací režim, volitelnou soutěž a obsahový přenos. Samostatné demo postupuje automaticky, zatímco vlastní live/test výuku zahajuje učitel. Texty neslibují hotové domácí úkoly, plné i18n, oddělené organizace, certifikaci GDPR, neomezenou kapacitu, cenu či SLA. Soubor přenosu zahrnuje správné odpovědi; export výsledků může obsahovat přezdívky. Související podklady: `docs/customer/08-MARKETINGOVE-PODKLADY.md`, `docs/customer/10-PRENOS-OBSAHU.md` a `docs/RELEASE-0.8.9.md`.

Použití existující značky je omezené na propagaci vlastního projektu podle zadání autora. Nevytváří novou licenci ani právo na další distribuci cizích značek.
