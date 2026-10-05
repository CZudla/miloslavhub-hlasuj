# API 0.8.7

## Lokální rozpracované rozšíření AI

Nové routy POST `/ai/suggest` a `/ai/preference` jsou lokální vývojové rozšíření, ve výchozím stavu vypnuté a dosud nenasazené. Vyžadují přihlášeného správce, REST nonce a u návrhu oprávnění k příslušné otázce. Přesný kontrakt a testy jsou v [AI-PILOT.md](AI-PILOT.md). Následující popis nadále dokumentuje vydání 0.8.7.

**Aktualizace 2026-10-02 pro 0.8.7:** opravný rozsah původního 0.9.0-dev.1 prošel také 19 integračními kontrolami na WordPressu 7.1.2/MariaDB 11.4.9. Oba SQL exporty byly obnoveny a tabulky zkontrolovány lokálně. Původní body níže označené „dosud neověřeno“ zachycují stav před touto kvalifikací; aktuální souhrn je v RELEASE-0.8.7.md. Nasazení prokazuje samostatná deployment zpráva.

Namespace zůstává `/wp-json/mhl/v1`. Technické názvy `mhl` a původní URL se nemění.

| Operace | Přístup / změna |
|---|---|
| POST `/activate`, mode live/test | Nově `manage_options`; anonymní požadavek HTTP 403, `mhl_teacher_required` |
| POST `/activate`, mode async | Zachován veřejný vstup; core ověřuje povolení dlouhodobé ankety |
| POST `/join` | Veřejné připojení; live/test zůstává pod řízením učitele |
| GET `/projection/{subject}/{token}` | Ověřovaný projekční odkaz; frontend opraven na existující cestu bez `/current` |
| GET `/question/...`, `/results/...`, `/subject/.../current` | Stávající API; public/private politika není dokončena |
| POST `/vote` | Stávající hlasovací kontrakt, bez změny schématu |
| POST `/privacy/hall-opt-in` | Stávající kontrakt; opravena obsluha tlačítek frontendu |

Žádný klientský údaj o roli není oprávnění. WordPress cookie autentizace potřebuje REST nonce; chráněná administrace používá existující admin nonce. Automatické integrace musí řešit WordPress autentizaci a přidělená oprávnění. Role `teacher` ještě není implementována, pouhá metadata vyučujícího nedávají právo řídit relaci.

Demo `/demo/api.php`: samostatné souborové API. `state` vrací pro účastníka fázi 8 až po jeho volbě Síně slávy; ostatním zůstává fáze 7. Veřejný leaderboard filtruje volby `skip` a nevyplněné, `anonymous` maskuje na serveru. Soukromá hodnota `my_rank` je pořadí mezi všemi účastníky; veřejné pořadí je souvislé mezi zveřejněnými řádky a může se lišit.

Oddělené public/private API, OpenAPI a rate limiting patří do další iterace. Současné API se nemá vydávat za hotové integrační rozhraní pro třetí strany.
