# Učitelé, organizace a čeština/angličtina — 8. 10. 2026

## Stav dodávky

Nevydaná implementace ve větvi `feature/requirements-feedback`, navazující na 0.8.9. Produkce Hlasuj! v této etapě nebyla změněna. Release metadata 0.8.9 / schéma 0.8.5 stále označují dosavadní vydání; nejsou označením nového vydání těchto funkcí. Domácí úkoly jsou mimo aktuální zadání.

Místně jsou implementované účty učitelů, organizace, vlastnictví, explicitní sdílení, přenos vlastnictví a cs/en ovládání. AUTH a autoritativní licence nejsou dokončené. Do jejich kvalifikace se celý systém nesmí označit za finální integrované vydání.

## Implementované chování

- Vlastník, správce organizace, učitel, spolupracující učitel a účet pouze pro čtení mají explicitní oprávnění. Správce organizace není správcem WordPressu. Technický provozovatel instalace má nadále globální přístup.
- Výběr prostoru omezuje seznamy i souhrny a určuje přiřazení nového obsahu. Kontroly objektu chrání přímé editační adresy, REST aktivaci, řízení relací, export a mazání testů. Skrytí tlačítka není jedinou ochranou.
- Sdílení předmětu a přednášky se dědí na přiřazený obsah stejného prostoru. Odebrání členství odvolává přístup; obsah se nemaže. Převod vlastníka zachovává trvalý slug a QR.
- Historický obsah zůstává původnímu autorovi v osobním prostoru. Hromadná migrace vlastnictví se neprovádí. Správa pracuje s existujícími WordPress účty; pozvánky přes centrální AUTH nejsou hotové.
- Katalog 998 textů a PHP/JS překladové moduly jsou shodné v obou dodávaných komponentách. Kontrola pokrytí zahrnuje 805 označených PHP textů; samostatný audit označených JS textů nenašel zbývající české výrazy s diakritikou. Jazyk ovládání nemění autorské otázky, odpovědi, vysvětlení a přezdívky. Webové demo má výslovně připravenou anglickou banku vlastních tří otázek. Čísla v odpočtu a výsledcích respektují jazyk. Audit diakritiky sám neprokazuje úplnost všech možných obrazovek; skutečné scénáře ověřují také browser testy.
- Potvrzené pravidlo míst počítá odlišný účet s právy k výuce jednou za organizaci, bez studentů a prohlížení. Místní počet není vynucením autoritativní kvóty.

[Technická reference](ORGANIZATIONS-I18N-REFERENCE.md), [český manuál](customer/13-ORGANIZACE-A-JAZYKY.md), [anglický manuál](customer/en/USER-GUIDE.md), [anglický přehled organizací](customer/en/TEACHERS-AND-ORGANISATIONS.md), [anglické marketingové podklady](marketing/en/PRODUCT-OVERVIEW.md).

## Ověření

Původní kvalifikační běhy prošly: **258 regresních kontrol, 450 integračních kontrol a 15 unittest metod referenčního adaptéru**. Obě sady mají shodný hash testovaných zdrojů `46c0eb56d460b8c5a7f531ea1f70c8a2ff4242c325dd04b578c9666c563b345c`. Z toho 130 serverových/DB kontrol organizací a 26 kontrol jejich skutečné administrace. [Úplná sanitizovaná evidence](test-evidence/2026-10-08-organizations-i18n.json).

Testy používají syntetická data, skutečný místní WordPress/MariaDB a Edge na loopbacku. Dávky 30 a 100 hlasů probíhají v samostatných PHP procesech; neověřují produkční HTTP workery, síť ani dlouhodobý polling. Provider AI je fixture, bez placených volání. WordPress avatarové požadavky se blokují a vykazují zvlášť.

Historické neúspěšné pokusy jsou zachované v chráněném úložišti a sanitizované evidenci. Patří mezi ně chyby nových fixture i skutečné opravené chyby: stínění JS helperu, velikost PHP regulárního výrazu, chybné escapování atributu editoru, chyba syntaxe jednoho placeholderu a pořadí registrace WordPress menu. Vizuální kontrola odhalila také nepřeložený ASCII nápis Demo skupina, který nyní ověřuje přímá browser kontrola. Necommitnutým variantám se nevymýšlí commit ani chybějící čas. Kvůli povinnému commit v centrálním VerificationEvidence zůstávají tyto historické pozorované chyby oddělené od důkazů konečného commitu.

Dodatečné cílené opakování zachytilo stejnou chybu při třetím průchodu: `user-profile.js` WordPressu 7.1.2 volal v beforeunload `$form.serialize()` před dokončením deferred jQuery ready inicializace profilu. Test po přihlášení okamžitě navigoval dál. [Původní ERROR s commitem a zásobníkem](test-evidence/2026-10-08-admin-repeat.json) zůstává zachovaný. Fixture nyní výslovně čeká na dokončení ready callbacků; chyby se nepotlačují a nepřidává se náhodný časový odklad.

Po opravě prošla znovu úplná regrese (258), integrace (450), 15 unittest metod a tři samostatná cílená opakování administrace (3 × 26). Hash tohoto běhu aplikace a testů je `b4c163ce5bdb3253ae026fd0018679126570f7be2839233678f1ccf13f9a1251`. [Závěrečná evidence inicializace a obou sad](test-evidence/2026-10-08-admin-readiness.json). První kvalifikace výše zůstává historickým dokladem před doplněním synchronizace testu; aplikační funkce se touto opravou nemění. Toto ověření nepokrývá všechny možné navigace WordPressu na produkci.

Zdrojové texty aplikace a testů používají LF; `.gitattributes` sjednocuje checkout. Před publikací se hash testovaných zdrojů porovnává také s přesnými blob daty připraveného Git indexu. Manifest je označený `unreleased-development`, bez přeznačení historické verze na nové vydání.

### Závěrečné ověření anglického dema

Audit vložených HTML řetězců zachytil také ASCII značku „vy“ u vlastní pozice. Překlad se nyní výslovně aplikuje i uvnitř podmíněných fragmentů; autorské přezdívky se nemění. Nulový odpočet obou demo obrazovek respektuje jazyk. Katalog má 998 textů. Browser ověřuje i správnou ukázkovou odpověď a anglické označení vlastní pozice.

Následná úplná regrese **259** prošla, navíc 15 unittest metod. Integrační běh stejných zdrojů skončil **ERROR** po 403 dokončených kontrolách: 360 serverových, 28 souběhu a 15 skutečné AI administrace; prošly také dávky 30 a 100 hlasů. Při přihlášení obsahového browser testu nedokončilo načtení `wp-admin` limit 30 sekund. Obsahový browser test nedoběhl a následující browser testy vysvětlení a organizací se nespustily. Příčina tohoto druhého timeoutu není prokázaná. Nejde o úplnou integrační kvalifikaci.

Hash obou běhů je `12c358cc181a98d25d131235955344abf1cf1aca6c4d62575d51dc15f941dd7e`. [Závěrečná jazyková evidence včetně chyb](test-evidence/2026-10-08-english-final.json). Dřívější úspěšná úplná integrace 450 kontrol a tři opakování synchronizace profilu jsou navázané na svůj commit `c6db3e9` a zachované samostatně; nejsou vydávané za úplný průchod poslední úpravy. Produkce a živé AUTH/licence zůstávají mimo místní ověření.

Předchozí souběžný místní integrační běh stejných zdrojů rovněž skončil ERROR: WordPress při bootstrapu překročil PHP limit 30 sekund, přihlášení vrátilo HTTP 500 a browser navigace timeout. Záznam je v téže evidenci. Opakování zachovalo původní PHP/browser limity a běželo samostatně bez souběžné regrese. Oba běhy ukončily vlastní servery. Produkční vydání zůstává zablokované také nedokončenou závěrečnou integrační kvalifikací.

## REQUIREMENTS

Čtení Private API v2 potvrdilo baseline 1.6.0, SHA-256 `aa53c6696d6c5dc83e596bc397ecba722a8c2b1b44abfa8e958e673be4ad2bcf`, 168 položek all a 149 open po dvou stránkách. `central_generation` není vrácené. Požadavky se tím automaticky neschvalují.

Nový rozsah bez domácích úkolů je zachycen návrhem rozhodnutí s receiptem `6405859c087e82a574fc4c617b62911f`. Organizační pravidlo míst navazuje na HLS-035 rev. 1, receipt `0742bff249a76d69304b8ff130741819`. Oba návrhy mají stav pending a `authoritative_applied=false`; tento stav znamená čekání na lidské zpracování v centrále, nikoli nedoručený lokální požadavek.

Implementační vazby se týkají HLS-032, 033, 034, 035, 045 a 046, vše rev. 1. HLS-032 a HLS-035 jsou částečné kvůli živému přihlášení, pozvánkám a licencování. Předchozí historické reporty zůstávají zachované. První zdrojový commit [`098ea28`](https://github.com/CZudla/miloslavhub-hlasuj/commit/098ea2836c085ca49a902a648a309f51321556d6) byl publikován a zpětně ověřen proti Git indexu i hashi 98 testovaných souborů. [Evidence publikace a fronty](test-evidence/2026-10-08-organizations-i18n-delivery.json) zachovává skutečný stav doručení.

Po obnovení zápisu do centrály bylo přijato a přesně zpětně ověřeno **24 implementačních/testových operací**: 9 implementačních zpráv a 15 testových záznamů (7 passed, 5 skipped, 3 error). Patří mezi ně doplnění konečné angličtiny, dílčí dokumentace HLS-067 a přípravy balíčků HLS-073, reprodukovaný profilový ERROR i oba poslední integrační timeouty. Nejde o automatické schválení nebo aplikaci požadavků; všechny odpovědi mají `authoritative_applied=false`.

Celková chráněná fronta má **61 sent, 0 pending a 13 historických blocked**. Aktuální efektivní sada má **24 sent / 0 pending / 0 blocked**, všech 24 těl bylo přečteno zpět a souhlasí. Poslední regrese prošla 259 kontrolami; poslední úplná integrace skončila ERROR po 403 kontrolách a finální vydání není kvalifikované. Původních 13 HTTP 400 řádků zůstává zachovaných: deset kvůli absolutnímu `artifact_ref` a tři kvůli `environment` delšímu než 200 UTF-8 bajtů. Opravené náhrady mají vlastní ID a receipty; výsledky a časy testů se nemění. Předchozí HTTP 503 `snapshot_unavailable` zůstává historií skutečného dočasného výpadku zápisu. Starší necommitnuté chybové varianty zůstávají chráněnými pozorováními a sanitizovanou evidencí; nevymýšlí se jim commit.

Závěrečný vývojový zdrojový commit je [`4a0e390`](https://github.com/CZudla/miloslavhub-hlasuj/commit/4a0e390105e93c4172caf9441f5f66d46e197143). Jeho 98 aplikačních/testových blobů přesně souhlasí s hashem konečných testů. Receipty, vazby na předchozí commity i skutečné výsledky obsahuje [evidence doručení](test-evidence/2026-10-08-organizations-i18n-delivery.json).

## Podmínky dokončení a nasazení

1. Kvalifikovaná registrace klienta Hlasuj! v AUTH, přesný callback, stabilní vazba issuer/sub, čerstvý stav účtu a session-bound assurance pro privilegované operace.
2. Autoritativní LICENSE/ENTITLEMENTS kontrakt pro konkrétní organizaci a akci, souběžná rezervace míst a skutečné ověření revokace/výpadku. Chybějící důkaz nesmí povolit chráněnou operaci.
3. E2E se dvěma skutečnými učiteli a organizacemi napříč těmito službami. Dostupný health endpoint nenahrazuje funkční autorizaci.
4. Nové vydání s konzistentními verzemi, společná čerstvá záloha WordPress DB, hlasovací DB, pluginu, frontendu, konfigurace a médií; ověřená obnova a rollback. Inicializace rolí mění WordPress DB, přestože tato etapa nepřidává hlasovací SQL DDL.
5. Teprve po kvalifikovaném nasazení dva finální balíčky a odpovídající marketing. Sestavení nyní zahrnuje i anglické podadresáře dokumentace a vyžaduje nové testy organizací a jazyků. Starší kvalifikace se nepoužívá pro novou aplikaci.

Testovací servery se ukončují ve finally. Syntetické testovací adresáře zůstávají mimo Git a distribuce; dřívější automatické odmítnutí jejich odstranění se neobchází. Uživatelské interní SSO dokumenty zůstávají nedotčené a vyloučené z veřejné publikace.
