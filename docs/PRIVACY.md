# Soukromí

## Nevydaná etapa 8. 10. 2026 — organizace a jazyky

Vývoj odděluje učitelský obsah místním vlastnictvím, členstvím a sdílením. Soukromé poznámky mohou číst oprávnění spolupracující učitelé a technický správce instalace; vlastník musí obsah zkontrolovat před sdílením. Odebrání členství odvolá přístup a nemaže obsah. Členství a ACL se nepřenášejí obsahovým exportem. Veřejné studentské/výsledkové odkazy zachovávají původní režim.

Jazyková volba používá místní cookie `mhl_ui_lang` na jeden rok, HttpOnly/SameSite=Lax a na HTTPS Secure. Obsahuje pouze `cs` nebo `en`. Překlad běží místně bez externí překladové služby. Otázky, odpovědi, poznámky a přezdívky se překladači neposílají; mění se ovládání. [Reference a současné integrační hranice](ORGANIZATIONS-I18N-REFERENCE.md).

WordPress administrace může podle nastavení instalace načítat avatar přes Gravatar. Místní browser testy tyto požadavky blokují a vykazují samostatně; jejich úspěch neprokazuje, že je produkční WordPress nevytváří. Správce musí při kvalifikaci provozu posoudit či vypnout externí avatary a ověřit také ostatní pluginy. Překladový katalog ani místní QR generátor tuto závislost nepotřebují.

## Nevydaná etapa 8. 10. 2026 — ankety

Nové anketní hlasy v live/test/async používají klíč konkrétní relace a prázdnou přezdívku také tehdy, když starší metadata obsahují body za účast. Přezdívka poslaná klientem se do hlasu neukládá. Body za anketu jsou nulové. Tato změna nemaže starší globálně propojené anketní hlasy ani jejich historické body. Vlastní odpověď staré bodované ankety může přestat být dostupná novým session-scoped vyhledáním; agregace zůstává zachovaná.

Prohlížeč nadále drží technickou identitu, obecné připojení do soutěžní přednášky může požadovat přezdívku a další tabulky či provozní logy mohou obsahovat pseudonymní údaje. Jde o omezení propojení nových anketních odpovědí, nikoli záruku úplné anonymity. Retence existujících údajů a záloh se tím nemění.

## Nevydaná etapa 7. 10. 2026

Vysvětlení kvízu se ve výchozím stavu nezveřejňuje. Při volbě „Studenti po ukončení hlasování“ se po uzavření zpřístupní ve veřejných výsledcích a na projekci; veřejný odkaz neprokazuje účast v hodině. Soukromá poznámka není ve studentských payloadech, ale je přístupná oprávněným správcům instalace a zahrnutá v učitelském obsahovém exportu JSON v2. Učitel musí vlastní texty zkontrolovat před sdílením. Poznámky s osobními údaji podléhají také retenci obsahu, záloh a sdílených souborů. Oddělení jednotlivých učitelů/organizací zůstává dalším vývojem.

Administrační QR se v této etapě vykresluje místně; hlasovací adresy se neodesílají externímu QR generátoru. Technické testy této změny samy nepotvrzují právní soulad celého provozu. Následující sekce dokumentují starší kvalifikovaný stav.

**Aktualizace 2026-10-02 pro 0.8.7:** opravný rozsah původního 0.9.0-dev.1 prošel také 19 integračními kontrolami na WordPressu 7.1.2/MariaDB 11.4.9. Oba SQL exporty byly obnoveny a tabulky zkontrolovány lokálně. Původní body níže označené „dosud neověřeno“ zachycují stav před touto kvalifikací; aktuální souhrn je v RELEASE-0.8.7.md. Nasazení prokazuje samostatná deployment zpráva.

Přezdívka a dlouhodobý participant identifikátor jsou pseudonymní údaje. Bez studentského účtu neznamená bez osobních údajů. Backend používá pro nebodovanou anketu klíč konkrétní relace; frontend stále drží technickou identitu v localStorage. Volba zveřejnění se týká Síně slávy, nikoli automaticky všech průběžných soutěžních žebříčků.

Demo uchovává dočasné relace na serveru a identitu účastníka v prohlížeči. Veřejná závěrečná tabulka obsahuje skutečnou přezdívku jen po volbě `nickname`, neutrální popis po `anonymous`, žádný záznam po `skip` nebo bez odpovědi. Vlastní účastník obdrží svoje skóre a přezdívku. Rozhodnutí jiného účastníka jeho obrazovku neukončí.

Demo expiruje po 15 minutách. Fyzické soubory odstraňuje úklid při následném požadavku; bez provozu mohou ležet déle. Produkční retence je nastavována v pluginu a vykonávána při běžných požadavcích. Ověřit retenci samostatně pro async data, logy a zálohy; časové sliby musí zahrnovat i tyto kopie.

Před pilotem doložit správce a zpracovatele, účel/právní titul, informační texty, retenční doby, způsob žádosti o přístup/výmaz, zpracování údajů nezletilých a přístup pedagogů. Tento dokument je technický popis a seznam otevřených bodů, nikoli právní potvrzení souladu.
