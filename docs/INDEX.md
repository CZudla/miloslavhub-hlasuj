# Dokumentace Hlasuj! by MiloslavHub

Aktualizováno 8. října 2026. [Domovská stránka](https://hlasuj.miloslavhub.cz/) · [Veřejné příručky](https://hlasuj.miloslavhub.cz/docs/) · [English](https://hlasuj.miloslavhub.cz/docs/en/) · [GitHub](https://github.com/CZudla/miloslavhub-hlasuj).

## Nejdříve vyberte správnou verzi

**Kvalifikovaná baseline je 0.8.9 / schéma 0.8.5.** Její příručky a balíčky jsou zachované v [releases/0.8.9](../releases/0.8.9/README.md). Datovaný doklad instalace je [nasazení z 5. 10.](DEPLOYMENT-2026-10-05-0.8.9.md); aktuální provozní verze jednotlivých služeb patří do samostatného Service Registry.

**Pracovní větev obsahuje nevydané změny.** Organizace, rozšířená angličtina, vysvětlení a neutrální ankety mají vlastní stav a testovou evidenci. AUTH, autoritativní licence a úplná závěrečná integrace nejsou dokončené. Publikace příručky ani úprava informačního webu tyto funkce neaktivuje. [Aktuální vývojová zpráva](DEVELOPMENT-ORGANIZATIONS-I18N-2026-10-08.md).

## Pro učitele a studenty

| Úloha | Příručka |
|---|---|
| Orientace a první použití | [Začněte zde](customer/01-ZACNETE-ZDE.md) |
| Příprava a vedení výuky | [Manuál učitele](customer/02-MANUAL-UCITELE.md), [tahák před hodinou](customer/09-TAHAK-PRO-VYUKU.md) |
| Připojení, odpovědi a výsledky | [Manuál studenta](customer/03-MANUAL-STUDENTA.md) |
| Význam nastavení | [Referenční příručka](customer/04-REFERENCNI-PRIRUCKA.md) |
| Sdílení učebního obsahu souborem | [Přenos obsahu](customer/10-PRENOS-OBSAHU.md) |
| Vysvětlení a soukromé poznámky — vývoj | [Příručka vysvětlení](customer/11-VYSVETLENI-A-POZNAMKY.md) |
| Ankety — změny ve vývoji | [Příručka anket](customer/12-ANKETY.md) |
| Učitelé, sdílení a jazyky — vývoj | [Organizace a jazyky](customer/13-ORGANIZACE-A-JAZYKY.md) |
| English | [Teacher guide](customer/en/USER-GUIDE.md), [student guide](customer/en/STUDENT-GUIDE.md), [reference](customer/en/REFERENCE.md), [organisations](customer/en/TEACHERS-AND-ORGANISATIONS.md) |

## Pro správce instalace

- [Instalace, aktualizace a obnova](customer/05-PRIRUCKA-SPRAVCE.md), [English administrator guide](customer/en/ADMINISTRATOR-GUIDE.md).
- [Řešení problémů](customer/06-RESENI-PROBLEMU.md), [nasazení](DEPLOYMENT.md), [upgrade](UPGRADE.md).
- [Domovská stránka, domény a směrování](HOMEPAGE-AND-ROUTING.md).
- [Bezpečnost](SECURITY.md), [soukromí](PRIVACY.md), [soukromí a licence pro zákazníka](customer/07-SOUKROMI-A-LICENCE.md), [licenční oznámení](licenses/NOTICE.md), [právní otevřené body](LEGAL-CHECKLIST.md).
- [Úklid a uchované zálohy](CLEANUP-STATUS.md). Privátní konfigurace a provozní data se neukládají do GitHubu.

## Technická a autorská reference

- [Architektura](ARCHITECTURE.md), [API a jeho hranice](API.md), [formát přenosu obsahu](CONTENT-FORMAT.md).
- [Organizace a i18n](ORGANIZATIONS-I18N-REFERENCE.md), [ankety](NEUTRAL-POLLS-REFERENCE.md), [vysvětlení](FEEDBACK-REFERENCE.md), [volitelná AI](AI-PILOT.md).
- [README](../README.md), [změny](../CHANGELOG.md), [GitHub a distribuce](GITHUB.md), [předání autorovi](AUTHOR-HANDOVER.md).
- [Produktové principy](PRODUCT-PRINCIPLES.md), [roadmapa](ROADMAP.md), [hlavní specifikace](MASTER-SPEC.md), [learning log](LEARNING-LOG.md), [audit](AUDIT.md).
- [Centrální požadavky a evidence](REQUIREMENTS-INTEGRATION.md), [testová evidence](test-evidence/2026-10-08-english-final.json), [přehled dokumentačního pokrytí](DOCUMENTATION-COVERAGE.md).

## Marketing a distribuce

[České marketingové texty](customer/08-MARKETINGOVE-PODKLADY.md), [English product overview](marketing/en/PRODUCT-OVERVIEW.md), [zásady webového obsahu](marketing/WEBSITE.md), [branding](BRANDING.md), [design system](DESIGN-SYSTEM.md), [Facebook podklady](marketing/facebook/README.md).

[Dva balíčky, PDF příručka a produktový list 0.8.9](../releases/0.8.9/README.md) jsou historicky kvalifikovaná distribuce. Nové finální balíčky se vytvoří až pro odpovídající kvalifikované vydání. Zdrojová dokumentace zůstává upravitelná a verzovaná v GitHubu; žádný odkaz na plánovanou funkci není příslibem její dostupnosti v konkrétní instalaci.
