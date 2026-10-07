# Soukromí

## Nevydaná etapa 7. 10. 2026

Vysvětlení kvízu se ve výchozím stavu nezveřejňuje. Při volbě „Studenti po ukončení hlasování“ se po uzavření zpřístupní ve veřejných výsledcích a na projekci; veřejný odkaz neprokazuje účast v hodině. Soukromá poznámka není ve studentských payloadech, ale je přístupná oprávněným správcům instalace a zahrnutá v učitelském obsahovém exportu JSON v2. Učitel musí vlastní texty zkontrolovat před sdílením. Poznámky s osobními údaji podléhají také retenci obsahu, záloh a sdílených souborů. Oddělení jednotlivých učitelů/organizací zůstává dalším vývojem.

Administrační QR se v této etapě vykresluje místně; hlasovací adresy se neodesílají externímu QR generátoru. Technické testy této změny samy nepotvrzují právní soulad celého provozu. Následující sekce dokumentují starší kvalifikovaný stav.

**Aktualizace 2026-10-02 pro 0.8.7:** opravný rozsah původního 0.9.0-dev.1 prošel také 19 integračními kontrolami na WordPressu 7.1.2/MariaDB 11.4.9. Oba SQL exporty byly obnoveny a tabulky zkontrolovány lokálně. Původní body níže označené „dosud neověřeno“ zachycují stav před touto kvalifikací; aktuální souhrn je v RELEASE-0.8.7.md. Nasazení prokazuje samostatná deployment zpráva.

Přezdívka a dlouhodobý participant identifikátor jsou pseudonymní údaje. Bez studentského účtu neznamená bez osobních údajů. Backend používá pro nebodovanou anketu klíč konkrétní relace; frontend stále drží technickou identitu v localStorage. Volba zveřejnění se týká Síně slávy, nikoli automaticky všech průběžných soutěžních žebříčků.

Demo uchovává dočasné relace na serveru a identitu účastníka v prohlížeči. Veřejná závěrečná tabulka obsahuje skutečnou přezdívku jen po volbě `nickname`, neutrální popis po `anonymous`, žádný záznam po `skip` nebo bez odpovědi. Vlastní účastník obdrží svoje skóre a přezdívku. Rozhodnutí jiného účastníka jeho obrazovku neukončí.

Demo expiruje po 15 minutách. Fyzické soubory odstraňuje úklid při následném požadavku; bez provozu mohou ležet déle. Produkční retence je nastavována v pluginu a vykonávána při běžných požadavcích. Ověřit retenci samostatně pro async data, logy a zálohy; časové sliby musí zahrnovat i tyto kopie.

Před pilotem doložit správce a zpracovatele, účel/právní titul, informační texty, retenční doby, způsob žádosti o přístup/výmaz, zpracování údajů nezletilých a přístup pedagogů. Tento dokument je technický popis a seznam otevřených bodů, nikoli právní potvrzení souladu.
