AKTUÁLNÍ VYDÁNÍ: 0.8.9. Kořenový README.md a docs/RELEASE-0.8.9.md uvádějí aktuální rozsah a ověření.
Frontend a plugin aktualizovat společně; config zachovat; schéma se nemění.

Historie přípravy a předchozích verzí:

POZOR: Následující návod je historický, platil pro frontend 0.7.9.
Pro 0.9.0-dev.1 NEPLATÍ samostatná aktualizace frontendu bez pluginu.
Jde o lokální vývojovou verzi; aktuální postup a podmínky jsou v docs/DEPLOYMENT.md a docs/UPGRADE.md.

HISTORICKÝ NÁVOD:
Hlasuj by MiloslavHub – aktualizace frontendu 0.7.9
Datum: 2026-09-25

1. Zazálohujte současný obsah subdomény.
2. Nahrajte obsah instalačního ZIPu a přepište existující soubory.
3. Produkční config.php zachovejte.
4. SQL ani WordPress plugin tímto balíčkem neměňte.
5. Proveďte Ctrl+F5.
6. Otestujte:
   - homepage,
   - klikací ukázky identit,
   - /privacy,
   - soutěžní připojení s přezdívkou,
   - anonymní anketu,
   - Síň slávy,
   - projekci,
   - /demo/.

Právní poznámka:
Frontend nyní obsahuje podstatně širší informační vrstvu přímo v aplikaci.
Úplný právní soulad však vždy závisí na konkrétním správci, právním titulu,
hostingu, externích službách, smlouvách se zpracovateli a dalších vlastnostech nasazení.
