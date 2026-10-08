# Neutrální ankety — technická reference

Nevydaný vývoj 8. 10. 2026. Centrální požadavek `hlasuj:requirement:HLS-018`, revize 1; živě načtená baseline 1.6.0. Approval status zůstává unknown. Místní implementace ani odeslání důkazů jej nemění.

## Serverový kontrakt

Anketa je otázka bez platné správné možnosti. Pro nové hlasy v režimech live, test a async REST `/vote` ukládá `points=0`, `is_correct=null` a prázdnou přezdívku. `_mhl_poll_points` již neovlivňuje body, povinnou přezdívku ani výběr identifikačního klíče. Explicitně dodaná přezdívka se zahodí. Ochrana duplicit, otevřené relace a serverového termínu zůstává účinná.

Identifikátor hlasu se odvozuje z identity zařízení a konkrétního session ID pomocí HMAC se saltem nonce. Stejné zařízení má v různých anketních relacích různé klíče. Výsledky vlastní odpovědi používají stejný výpočet. Jinému zařízení se tato odpověď neposkytne. Dlouhodobá identita zůstává v prohlížeči a jiné subsystémy mohou evidovat připojení; nejde o tvrzení úplné anonymity či právního souladu.

Question payload vrací pro anketu `nickname_required=false` i `join_nickname_required=false`. Příznak gamification může zůstat enabled kvůli celkovému pořadí přednášky. Obecný vstup do soutěžní přednášky nadále může vyžadovat přezdívku pro následující kvízy.

Nové anketní hlasy se díky prázdné přezdívce neúčastní soutěžních žebříčků. Výsledky ankety mají `correct_index=null` a prázdný question leaderboard. Celkový žebříček obsahuje dřívější soutěžní záznamy. Kvízový výpočet bodů se nemění.

Také Testovací laboratoř ukládá simulované anketní hlasy s nulovými body, null correctness a prázdnou přezdívkou. HTTP akce zachovává oprávnění `manage_options` a původní nonce. Vnitřní metoda `MHL_Admin::simulate_session_votes` navíc kontroluje oprávnění, testovací režim, aktivní běh, příslušnost otevřené relace a termín. Pět vložení probíhá v existující transakci se zámkem běhu. U kvízu zůstávají simulované přezdívky a výpočet bodů.

## UI a kompatibilita

Editor místo bodů za účast vysvětluje, že anketa nemá správnou odpověď a nepřidává soutěžní body. Podvržené pole `mhl_poll_points` se při uložení ignoruje. Stávající metadata zůstávají zachovaná pro obsahový round trip v1/v2; formát se nemění.

Frontend navíc ověřuje typ otázky při zvýraznění správné možnosti, soutěžního pořadí, vlastních bodů, času a nabídky Síně slávy. Ani neočekávaná stará soutěžní pole v poll payloadu tak nezpůsobí soutěžní zobrazení ankety. U celkového pořadí student i projekce vidí vysvětlení jeho původu v předchozích soutěžních otázkách.

Bez DDL migrace a bez přepočtu starých hlasů. Starší anketní hlasy uložené pod globálním klíčem se osobním poll výsledkem pod novým session klíčem nenajdou. Agregace a historické soutěžní body zůstávají; případná oprava historie potřebuje zvláštní rozhodnutí, zálohu a audit. Změna typu otázky v průběhu běhu není řešena snapshotem v této etapě.

## Ověření

`tests/poll-wordpress-integration.php` ověřuje live/test/async na skutečném WordPressu a hlasovací MariaDB: stará nenulová metadata, volitelná i explicitně dodaná přezdívka, nulové body a null correctness, rozdílné klíče, duplicity, vlastní/cizí výsledky, nezměněný dřívější kvízový záznam a součet, editor a obsahový round trip.

Stejná sada volá také skutečnou vnitřní operaci Testovací laboratoře: anonymní zákaz bez zápisu, pět neutrálních anketních hlasů, zákaz po uzavření, zachování kvízového bodování a odmítnutí živého běhu/cizí relace.

`tests/browser.cjs` ověřuje mobilní i projekční výsledky včetně obrany proti neočekávaným soutěžním polím. `tests/feedback-wordpress-browser.cjs` ověřuje skutečný editor. Výsledky a zachycené neúspěchy jsou v samostatné datované testovací evidenci; seznam scénářů sám není tvrzením PASS.
