# Technická reference vysvětlení a poznámek

Nevydaný vývoj z 7. 10. 2026 nad vydáním 0.8.9. Centrální vazby: `hlasuj:requirement:HLS-022` a `HLS-023`, revize 1; dílčí rozšíření `HLS-028/029`. Identita načtené baseline je v [REQUIREMENTS-INTEGRATION.md](REQUIREMENTS-INTEGRATION.md). Lokální implementace nemění schválení ani ověření v centrále.

## Uložení

Všechna pole jsou WordPress post meta otázky `mhl_question`. Žádná nová tabulka, DDL migrace ani převod existujících dat není potřeba. Staré otázky bez metadat zůstávají funkční.

| Meta klíč | Typ a výchozí hodnota | Hranice |
|---|---|---|
| `_mhl_correct_answer_explanation` | obyčejný text, prázdný | 4 000 bajtů UTF-8 |
| `_mhl_explanation_mode` | `teacher_only` | allowlist `teacher_only`, `show_after_close`, `hidden`; neznámá hodnota je soukromá |
| `_mhl_teacher_note` | obyčejný text, prázdný | 4 000 bajtů UTF-8; vynechán z veřejných payloadů |

`MHL_Admin::save_question` vyžaduje existující nonce `mhl_save_question`, `edit_post` a nerevizní post. Přítomná textová pole projdou `wp_unslash` a `sanitize_textarea_field`. Zkrácení na byte limit odstraní případný neúplný koncový znak UTF-8. `wp_slash` před `update_post_meta` zachovává uživatelova lomítka a apostrofy přes WordPress metadata API. Nepřítomnost nového pole ve starším/částečném editoru je nezmění.

Oprávnění vycházejí ze současných WordPress capabilities. Tato změna nepřidává ACL organizací. CPT otázky není veřejný a nemá zapnuté standardní WordPress `show_in_rest`; aplikační REST vytváří explicitní pole. Změna registrace CPT nebo nový endpoint musí ochranu soukromé poznámky samostatně zachovat.

## Zveřejnění vysvětlení

`MHL_Core::public_explanation(question_id, session)` je jediná nová serverová brána. Přímé kopírování všech post meta do API by ochranu obešlo a není přípustné.

| Stav / obsah | Výsledek veřejného pole |
|---|---|
| Chybí relace | `null` |
| `idle`, `joining`, `open`, `skipped` | `null` |
| Ankety bez platného indexu správné odpovědi | `null` |
| `teacher_only`, `hidden`, chybějící/neznámý režim | `null` |
| Uzavřený kvíz, `show_after_close`, prázdný text | `null` |
| Uzavřený kvíz, `show_after_close`, neprázdný text | text vysvětlení |

GET `/wp-json/mhl/v1/results/{lecture}/{question}` vrací aditivní pole `correct_answer_explanation`. Otázka a aktuální stav jej neposílají. Studenti i projekce používají stejnou výsledkovou politiku. Soukromá poznámka není součástí žádné z těchto odpovědí.

V `frontend/assets/app.js` pomocná funkce `explanation` navíc kontroluje uzavřený kvíz a escapuje text i nadpis. Podvržený HTML řetězec se zobrazí jako text. Klientská kontrola doplňuje serverovou kontrolu; o zveřejnění rozhoduje server. Nadpis předává PHP jako `data-explanation-label`; kompletní jazykové katalogy frontendu zůstávají další etapou.

Výsledkový odkaz je dosud veřejný podle slugů. `show_after_close` neověřuje účast, účet ani organizaci. Vysvětlení se bere z aktuální otázky, takže úprava ovlivní i starší relace. Neměnný snapshot obsahu relace patří do navazujícího návrhu domácích zadání a historických výsledků.

## Přenos obsahu

Exporter `MHL_Content_Transfer` nyní tvoří JSON v2; validator přijímá v1 a v2 s přesným seznamem polí pro každou verzi. V2 obsahuje text vysvětlení, režim a soukromou poznámku. V1 tyto klíče odmítne; při jeho importu jsou nová metadata nepřítomná a použijí se soukromé výchozí hodnoty.

Export zůstává akcí pro `manage_options` s kontrolou úprav všech obsažených záznamů. Náhled a potvrzení jsou vázané na konkrétní účet, nonce, dobu a jednorázový token. Poznámka v souboru je materiál pro kolegu, nikoli studentský obsah. UI na to výslovně upozorňuje. Přesný whitelist, limity, nové koncepty a kompenzační odstranění při chybě dokumentuje [CONTENT-FORMAT.md](CONTENT-FORMAT.md).

WordPress ukládá boolean `false` jako prázdnou meta hodnotu. `settings` nyní rozlišuje přítomné boolean metadata pomocí `metadata_exists`, aby např. vypnuté `async_show_results` při re-exportu nepřešlo na výchozí `true`. Test round trip tuto dřívější chybu odhalil na skutečné databázi.

## QR administrace

`MHL_Admin` registruje místní `assets/qrcode.min.js` jako závislost `admin.js`. Pro každý `.mhl-qr[data-qr]` vznikne QR uvnitř stránky. Knihovna může po vykreslení skrýt pracovní canvas a zobrazit PNG přes data URI; prohlížečový test proto ověřuje viditelný a dekódovaný místní obrázek. Při chybě nebo chybějící knihovně se zobrazí text adresy. Existující hlasovací URL a slugy zůstávají stejné.

Knihovna odpovídá již používané frontendové kopii. Licence a hashe jsou v `assets/qrcode-LICENSE.txt` a `assets/qrcode-provenance.json`. Změna této knihovny vyžaduje kontrolu obou použití a licence. Externí generátor `qrserver.com` již tento administrační kód nevolá.

## Opakovatelné ověření

```text
python tests/requirements_context.py
python tests/run.py --php C:/php84/php.exe --browser
python tests/local-integration.py --root C:/novy-disposable-hlasuj-test --wordpress-source C:/cache/wordpress --mariadb-dir C:/cache/mariadb-11.4.9-winx64 --php C:/php84/php.exe --browser
```

Prohlížeč potřebuje dostupný Playwright, případně jeho skutečný `NODE_PATH`. Integrační cílová cesta musí být nová a testovací WordPress/DB se tvoří pouze na loopbacku. Runner odmítne neúplnou distribuci WordPressu a existující cílový adresář. Guard v PHP testech vyžaduje syntetickou DB `integration_wp` a loopback konfiguraci. Oba servery se ukončují v `finally`.

`tests/feedback-wordpress-integration.php` ověřuje serverové politiky, anonymní REST, nonce/oprávnění, UTF-8/slashes, v1/v2 round trip i vadné vstupy. `tests/feedback-wordpress-browser.cjs` ověřuje skutečné uložení editoru, přepnutí na anketu a místní QR. Frontendová regrese ověřuje i HTML jako text, projekci a zákaz zveřejnění před uzavřením. Testy běží na syntetických datech; nevypovídají o kapacitě hostingu ani kvalitě AI modelu.

Regresní a integrační runner zachycují hash zdrojů před spuštěním a odmítnou PASS, pokud se soubory během testu změnily. Nový běh zneplatní předchozí úspěšný runtime report. Testovací evidence v `docs/test-evidence/` je druhotný doklad; centrální odeslání zatím čeká na integrační přístup.
