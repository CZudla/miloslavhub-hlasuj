# Vývojová větev Hlasuj! — 5. 10. 2026

Prvotní import zachytil místní commit `28b07ebd36ab11e04e072268e60357412a7fedc8`. Následující místní commit `83f545abfa330cf7a2c9a6b681433539b38efc51` doplnil skutečné ověření WordPress administrace, opakovatelný integrační runner a dokumentaci výsledků.

Větev obsahuje výchozím stavem vypnutý AI pilot a transakční řízení hlasování. Nejde o nasazenou verzi ani nové kvalifikované vydání 0.8.7.

Dne 5. 10. prošlo 269 kontrol: původní regrese a AI kontrakty, WordPress/DB integrace, 28 kontrol souběhu nezávislých PHP procesů a 13 kontrol celého AI panelu v administraci WordPressu v Edge. Navíc byl ověřen konečný uložený stav otázky, odpovědí a osobního vypnutí. [Souhrnný doklad](test-evidence/2026-10-05-local.json) obsahuje rozsah a limity ověření.

Testy používaly syntetická data, loopback a náhradní transport poskytovatele. Placené API se nevolalo. Zátěž na očekávaném počtu studentů a dostupnost, cena a kvalita skutečného modelu zůstávají neověřené. Produkční konfigurace, databáze a logy jsou mimo GitHub.

Podrobnosti: [stav migrace](GPT-6-MIGRATION-STATUS.md), [AI pilot a opakování testů](AI-PILOT.md), [pravidla GitHubu a vydání](GITHUB.md).
