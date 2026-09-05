---
paths:
  - resources/views/sepaxml.blade.php
---

# Views

## SEPA-Lastschrift laeuft auf pain.008.001.08 — nicht auf .09
`resources/views/sepaxml.blade.php` erzeugt seit 2026-09-05 pain.008.001.08 (DK Anlage 3 / GBIC_4). pain.008.001.02 wird am 14.11.2026 abgeschaltet.

Die Zielfassung fuer **Lastschriften** ist .08, nicht .09 — pain.001.001.09 ist die Version der **Ueberweisung**. Die beiden werden staendig verwechselt; eine .09-Lastschriftdatei weist die Bank ab.

Am Aufbau aendert sich gegenueber .02 nur zweierlei: der Namensraum (inkl. schemaLocation) und `<BIC>` heisst in `FinInstnId` jetzt `<BICFI>` (zweimal: CdtrAgt und DbtrAgt). Reihenfolge, ChrgBr=SLEV, BtchBookg, EndToEndId=NOTPROVIDED und MndtRltdInf bleiben unveraendert.

Strukturierte Adressen sind in .08 Pflicht, sobald `PstlAdr` gesendet wird — die Datei sendet keine, deshalb hier kein Thema. Ebenso unkritisch: alle 517 Mitglieder mit IBAN haben einen BIC (2026-09-05 an der Produktionsdatenbank geprueft), ein IBAN-only-Zweig mit `Othr/Id=NOTPROVIDED` wird also nicht gebraucht.

`tests/Feature/SepaGenerationTest.php` pinnt Namensraum und BICFI.
