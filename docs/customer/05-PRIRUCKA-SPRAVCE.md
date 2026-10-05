# Příručka správce: instalace, aktualizace a obnova

Hlasuj! by MiloslavHub · 0.8.9

## 1. Složení a požadavky

Systém má PHP/JavaScript frontend na vlastní doméně nebo subdoméně a WordPress plugin `miloslavhub-live`. WordPress databáze obsahuje otázky a nastavení, druhá samostatná MySQL/MariaDB databáze provozní hlasy a účastníky. Samostatné demo zapisuje do dočasného adresáře PHP.

Plugin deklaruje WordPress 6.4+ a PHP 8.1+. Integrační testy tohoto vydání proběhly na WordPressu 7.1.2, PHP 8.4 a MariaDB 11.4.9. Obnova zdrojových záloh z MariaDB 10.11.18 byla ověřena na lokální 11.4.9. Nejde o potvrzení každé kombinace minimálních verzí.

Potřebujete HTTPS, PHP s MySQLi, WordPress REST API a pro frontend přepis adres odpovídající dodanému `.htaccess`. U jiného webserveru nastaví ekvivalent správce. Frontendové cesty předpokládají kořen hostitele; nasazení do podsložky nebylo kvalifikováno.

## 2. Nová instalace

1. Připravte oddělenou testovací instalaci WordPressu a prázdnou externí databázi. Ověřte její zálohování a dostupnost z hostingu.
2. Do **prázdné externí databáze** importujte `database-schema.sql` z pluginu. Staré migrační soubory nejsou seznam kroků pro novou instalaci.
3. Do `wp-config.php` před načtení WordPressu vložte konstanty `MHL_LIVE_DB_HOST`, `MHL_LIVE_DB_NAME`, `MHL_LIVE_DB_USER`, `MHL_LIVE_DB_PASSWORD` s údaji této instalace. Nevkládejte hesla do repozitáře ani do zákaznických ZIPů.
4. Nainstalujte `instalace/miloslavhub-live-0.8.9.zip` jako WordPress plugin a aktivujte ho. Ponechte název složky `miloslavhub-live`.
5. Obsah `instalace/frontend/` nahrajte do kořene frontendového hostitele. Z příkladu připravte vlastní `config.php`. API URL musí směřovat na váš WordPress s `/wp-json/mhl/v1`; frontendové adresy, odkazy a kontakt nahraďte vlastními. Příklad obsahuje adresy původního projektu.
6. V **Živé hlasování → Nastavení** nastavte Adresu hlasování a Povolený frontend (CORS) na svůj HTTPS frontend. Zkontrolujte zprávu o připojení a schématu.
7. Vyplňte provozovatele, kontakt pro soukromí, přiměřené retenční doby a zamýšlené soutěžní funkce.
8. Otestujte učitele, dva studentské prohlížeče, projekci, správnost po uzavření, CSV a demo. Teprve poté připojte skutečnou výuku.

Chybějící frontendový `config.php` vrací HTTP 503 se zprávou pro správce. Je to ochrana před nechtěným připojením nové instalace k původní produkci.

## 3. Aktualizace existujícího systému

Pro přechod z ověřeného schématu 0.8.5 na vydání 0.8.9 **není potřeba databázová migrace**. Verze aplikace a verze schématu jsou vedeny odděleně. Existující dodatečné indexy nemusí znamenat chybu; před změnou je porovnejte s reálnou strukturou.

Před aktualizací ověřte výchozí verze a neprobíhající výuku. Zálohujte obě databáze, celý WordPress s médii, plugin, frontend a obě konfigurace. Proveďte zkušební obnovu. Frontend a plugin přepněte koordinovaně na stejné vydání. Zachovejte konfiguraci, názvy pluginových složek, doménu a trvalé identifikátory obsahu.

Při starším nebo neznámém schématu nejprve porovnejte skutečné tabulky a sloupce. Historické SQL migrace mohou být kumulativní a nemusí být bezpečné při opakovaném importu. Nespouštějte je všechny ani je neimportujte do WordPress DB.

Po aktualizaci ověřte nové assety a odstraňte pouze relevantní cache. Nepoužívejte veřejné cache pro REST, hlasování a osobní výsledky. Zaznamenejte čas, commit, balíček a kontrolní součty.

## 4. Zálohování a obnova

Úplná záloha zahrnuje WordPress DB, hlasovací DB, obsah WordPressu včetně médií, frontend, plugin a konfigurace. Export CSV výsledků ji nenahrazuje. Zálohy mohou obsahovat hesla i osobní údaje; uchovávejte je mimo veřejný web s omezeným přístupem a vlastní retenční lhůtou.

Obnovu nejprve proveďte do izolovaného prostředí. Zkontrolujte import obou DB, tabulky, počty záznamů, vazby otázek a hlasů, konfiguraci na místní adresy a blokaci odchozích e-mailů/externích požadavků. Zaznamenejte výsledek.

Při návratu opravného vydání bez změny schématu obvykle stačí obnovit předchozí frontend a plugin. Starou DB neobnovujte přes nové hlasy bez rozhodnutí, jak zachovat data vzniklá od zálohy. Souborový návrat zároveň obnoví dřívější chování a chyby.

## 5. Provoz

Kontrolujte dostupnost frontendu, REST API, stav externí databáze, místo na disku a běh retenčního úklidu. Demo expiruje za 15 minut, fyzické soubory se mažou při následných požadavcích. Retence pluginu se také spouští za běžného provozu; přesný čas výmazu bez provozu není garantován.

Projekční token chraňte před nechtěným sdílením. CORS a `noindex` nejsou přístupové oprávnění. Veřejná dostupnost některých výsledkových endpointů zůstává známým omezením 0.8.9. Před použitím pro citlivé hodnocení je nutné upravit přístupový model.

Souběh hlasu s uzavřením a opakováním je chráněn transakcí a ověřen 28 řízenými scénáři. Tabulky runs, sessions, votes a session_joins musí používat InnoDB; automatická konverze se neprovádí. Pro tuto aktualizaci ověřené produkce nebyla potřeba migrace.

Multi-teacher izolace a rate limiting vyžadují další vývoj. Místní test ověřil dávky 30 a 100 souběžných hlasů, ale kapacitu webových workerů a polling ověřte na cílovém hostingu. Toto vydání nemá zátěžovou certifikaci.

AI pomoc s přeformulováním a překladem je volitelná a ve výchozím stavu vypnutá. Zapnutí poskytovatele a placené API vyžaduje samostatné rozhodnutí provozovatele. AI návrh se použije pouze po potvrzení učitelem; studentské výsledky se neodesílají. Technické nastavení a limity jsou v AI-PILOT.md autorských zdrojů.
