# Referenční příručka

Hlasuj! by MiloslavHub · 0.8.8 · referenční stav zdrojového kódu

## Obsah a režimy

| Pojem | Význam |
|---|---|
| Předmět | Kurz/semestr, značka, přezdívky a případné celkové pořadí |
| Přednáška | Uspořádaná sada otázek přiřazená k předmětu |
| Otázka | Zadání a odpovědi, případně správná možnost, čas a bodování |
| Běh přednášky | Konkrétní spuštění přednášky v živém, testovacím nebo anketním režimu |
| Hlasovací relace | Konkrétní pokus jedné otázky; opakování vytvoří další relaci |
| Live | Živá výuka řízená učitelem |
| Test | Zkouška výuky s odděleným režimem dat; start řídí učitel |
| Async | Dlouhodobá anketa, samostatné odpovědi bez společného odpočtu |
| Demo | Oddělená automatická ukázka s dočasnými daty |

## Stavy živé otázky

| Stav | Co znamená / co udělat |
|---|---|
| Neaktivní (`idle`) | Není spuštěný odpovídající běh; učitel připraví přednášku |
| Připravená (`waiting`) | Čeká na Spustit hlasování |
| Připojování (`joining`) | Historický stav; v 0.8.8 čeká na učitele stejně jako připravená otázka |
| Probíhá (`open`) | Přijímá odpovědi do časového limitu nebo ručního ukončení |
| Ukončená (`closed`) | Další hlasy nepřijímá; lze probrat výsledky nebo vědomě zopakovat |
| Přeskočená (`skipped`) | Dokončený stav; otevření studentského odkazu jej neobnovuje |

## Nastavení otázky a přednášky

| Volba | Chování |
|---|---|
| Žádná správná odpověď | Určí anketu |
| Označená správná odpověď | Určí kvíz s jednou správnou možností |
| Automatický čas | Použije výchozí dobu z Nastavení instalace |
| Bez časového limitu | Učitel musí živé hlasování ukončit ručně; platí i limit celého běhu |
| Násobitel bodů | Nabídka ×1, ×1,5, ×2 |
| Rychlostní okno | Doba poklesu rychlostního bonusu u správné odpovědi |
| Body za účast | Starší volitelné body živé ankety, běžně ponechat 0 |
| Soutěžní režim | Přezdívky, body, pořadí; rozsah součtu určuje Celkové pořadí |
| Průběžné výsledky | Rozložení odpovědí již během hlasování; neznamená předčasné odhalení správnosti |
| Materiály / AI asistent | Volitelné odkazy; systém sám nevytváří AI asistenta |
| AI/RAG politika | Informace pro externí indexer; sama obsah do indexeru neposílá |

Výchozí kvízové bodování je 800 za správnost a až 200 za rychlost, s násobitelem otázky. Správce může základ i bonus změnit. Nesprávná odpověď má 0 bodů. Pro výklad výsledků vždy zkontrolujte nastavení konkrétní instalace a soutěžního režimu; výchozí příklad není zárukou stejného bodování všude.

## Adresy frontendu

| Cesta za doménou | Účel |
|---|---|
| `/q/{prednaska}/{otazka}` | Živé hlasování |
| `/r/{prednaska}/{otazka}` | Výsledky/projekce otázky |
| `/test/{prednaska}/{otazka}` | Testovací hlasování |
| `/test-results/{prednaska}/{otazka}` | Testovací výsledky |
| `/poll/{prednaska}/{otazka}` | Dlouhodobá anketa |
| `/poll-results/{prednaska}/{otazka}` | Výsledky dlouhodobé ankety |
| `/project/{predmet}/{token}` | Projekce předmětu s projekčním tokenem |
| `/hall-of-fame/{predmet}` | Síň slávy předmětu podle konfigurace |
| `/privacy` | Informace o soukromí |
| `/demo/` | Samostatná ukázka |

Zástupné názvy v závorkách nenahrazujte odhadem. Použijte adresy vytvořené administrací. Token projekce umožňuje přístup bez WordPress účtu; sdílejte ho pouze s určenou obsluhou.

## CSV export výsledků

UTF-8 s BOM, oddělovač `;`. Sloupce: `run_id`, `session_id`, `question`, `nickname`, `option`, `is_correct`, `response_ms`, `points`, `created_at`. Možnost je označena písmenem, správnost hodnotou 1/0, u ankety může být prázdná. Čas odpovědi je v milisekundách. Databázové časové údaje jsou ukládány v UTC; administrace je může zobrazovat v místním čase WordPressu.

CSV obsahuje jednotlivé hlasy, nikoli celé nastavení předmětu. Před sdílením vyberte nezbytné sloupce a zvažte odstranění přezdívek. Export otázek a následný import nejsou v 0.8.8 hotovou funkcí.

## API pro technického správce

Namespace je `/wp-json/mhl/v1`. GET otázky, výsledků a aktuálního stavu, POST připojení a hlasu používá webový klient. POST `/activate` v live/test vyžaduje oprávnění `manage_options`; WordPress cookie autentizace navíc vyžaduje platný REST nonce. Async aktivace je veřejná pouze pro povolený anketní obsah.

Projekční REST cesta je `/projection/{subject}/{token}`. Není totožná s frontendovou cestou `/project/...`. Další cesty obsluhují přezdívky, účastníka, soukromí a Síň slávy. Jde o existující aplikační API; oddělení veřejného a soukromého API a garantovaná kompatibilita integrací třetích stran zůstávají dalším vývojem. Technické detaily jsou v autorském balíčku v `docs/API.md`.
