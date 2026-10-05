# Řešení problémů

Hlasuj! by MiloslavHub · 0.8.9

| Projev | Co ověřit | Další krok |
|---|---|---|
| Aplikace čeká na nastavení / 503 | Existenci vlastního frontendového config.php | Správce nastaví adresy instalace; nekopíruje cizí produkční přístupy |
| Učitel nevidí Živé hlasování | Oprávnění WordPress účtu | Správce ověří manage_options |
| Student čeká a čas neběží | Je spuštěná správná přednáška a otázka? | Učitel klikne na Spustit hlasování |
| QR fungoval, nyní otázku nenajde | Publikaci, přiřazení k přednášce, doménu, režim URL | Obnovit obsah/konfiguraci, nepřepisovat trvalé identifikátory |
| Student nemůže spustit hlasování / 403 activate | Zda jde o živý/testovací režim | Očekávané: spouští učitel, student pouze odpovídá |
| Učitel přes integraci dostává 403 | Autentizaci, oprávnění, REST nonce | Použít ověřenou administraci; nevypínat kontrolu oprávnění |
| Data se nenačítají | HTTPS, API URL, CORS, externí DB, cache | Správce kontroluje HTTP status a serverové logy bez zveřejnění hesel |
| Čas je kratší, než student čekal | Čas společného startu | Pozdní připojení čas neobnovuje |
| Odpověď je duplicitní | Zda už relace odpověď přijala | Nevytvářet obcházející identitu; pokračovat v další otázce |
| Přezdívka je obsazená | Původní zařízení/prohlížeč a rezervaci v předmětu | Pokračovat v původním prohlížeči nebo zvolit jinou |
| Projekce nefunguje | Správný odkaz, platný token, existující předmět | Znovu převzít odkaz z administrace |
| CSV má špatnou diakritiku/sloupce | Import UTF-8 a oddělovač středník | Otevřít pomocí importu dat tabulkového editoru |
| Demo skončilo | Patnáctiminutovou životnost | Spustit novou ukázku |
| V galerii je starší vzhled | Označení historických obrázků | Pro aktuální chování použít dodané screenshoty a manuál 0.8.9 |

## Co poslat správci

Verzi, čas a časové pásmo, režim live/test/anketa/demo, popis posledního kroku, prohlížeč a zařízení. Uveďte HTTP status, pokud ho znáte. Přiložte anonymizovaný snímek. Hesla, konfigurační soubory, cookie, zálohy a projekční token neposílejte do veřejné komunikace.

## Co nedělat při běžném problému

Nespouštějte opakovaně SQL migrace, nemažte data v databázi a nemažte celou cache jiných webů. Neprovádějte nové nasazení uprostřed výuky. Pokud nová verze nefunguje, použijte doložený postup návratu s konkrétní zálohou.
