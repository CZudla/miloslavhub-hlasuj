# Pokrytí dokumentace

Stav 8. 10. 2026. Vstupní bod je [INDEX](INDEX.md). Tento přehled ověřuje existenci a účel podkladů; není potvrzením úspěšného nasazení nevydané aplikace.

| Oblast | Hlavní podklady | Stav a praktická hranice |
|---|---|---|
| Učitel a student | customer/01 až 04, 09; customer/en | Postupy výuky, připojení, opakování a výsledků; anglické příručky rozlišují jazyk dokumentace a skutečný jazyk instalace. |
| Správa a obnova | customer/05, 06; DEPLOYMENT, UPGRADE | Požadavky instalace, konfigurace bez hodnot hesel, obě DB, test obnovy, rollback a diagnostika. |
| Obsah a výsledky | customer/10, CONTENT-FORMAT | JSON přenos obsahu se liší od CSV výsledků i úplné zálohy. v1 patří baseline 0.8.9; writer v2 je vývoj. |
| Nové funkce | customer/11 až 13; FEEDBACK, NEUTRAL-POLLS, ORGANIZATIONS-I18N reference | Označené jako nevydané; role, sdílení, vysvětlení, ankety a i18n. |
| Architektura a integrace | ARCHITECTURE, API, AI-PILOT | Zachované komponenty a namespace; oddělené public/private integrační API, AUTH a licence nejsou prohlášené za dokončené. |
| Domovská stránka a routing | HOMEPAGE-AND-ROUTING; deploy/apache | Kanonický host, přesná aliasová pravidla, kontrola driftu, souborová záloha a návrat. Plný sdílený serverový config zůstává privátní. |
| Bezpečnost a soukromí | SECURITY, PRIVACY, customer/07 | Veřejné výsledky, tokeny projekce, retence a exporty; technický text nenahrazuje povinnosti provozovatele. |
| Licence | licenses/NOTICE, GPL-2.0; LEGAL-CHECKLIST | GPL pluginu a MIT QR knihovny; sjednocení práv k vlastnímu frontendu, grafice a obchodních podmínek zůstává autorským rozhodnutím. |
| Marketing | customer/08; marketing/en, marketing/facebook; BRANDING, DESIGN-SYSTEM | Texty, identita a podklady; schopnosti a screenshoty musí odpovídat nabízené verzi. |
| Vývoj, důkazy a distribuce | README, CHANGELOG, LEARNING-LOG, test-evidence, releases | Commit, výsledky včetně ERROR/skipped a doklady starších distribucí. Nové finální ZIPy nejsou vydané. |

## Co před finálním integrovaným vydáním doplnit

- Kvalifikované AUTH callbacky, registraci klienta, account/session assurance, mapování identity a postup odvolání přístupu podle skutečného kontraktu.
- Autoritativní licenční zdroj, rezervace míst, expiraci, výpadky a podporu zákazníka podle kvalifikovaného kontraktu.
- Úspěšnou závěrečnou integrační evidenci; poslední běh vývoje skončil ERROR při načítání WordPress administrace.
- Konkrétní nové release/deployment doklady, společnou zálohu a obnovu, dva finální balíčky a jejich kontrolní součty.
- Autorem potvrzené obchodní a licenční podmínky vlastních materiálů; údaje správce a právní titul musí doplnit provozovatel konkrétní instalace.

Domácí úkoly se v této etapě nedokumentují jako dostupná funkce. Historické manuály a balíčky se nepřepisují na nové vydání.
