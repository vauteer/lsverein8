---
paths:
  - 'tests/**'
---

# Tests

## Der public-Datenträger ist global gefakt — nicht pro Testdatei
`Storage::fake('public')` steht im `beforeEach` von `tests/Pest.php` und gilt für die gesamte Feature-Suite. Nicht in einzelne Testdateien zurückverlagern.

Grund: `ProfileController` und `ClubController` räumen bei **jedem** Speichern verwaiste Dateien weg (`User::removeOrphanProfileImages()`, `Club::removeOrphanLogos()`) — nicht nur beim Hochladen, sondern auch bei einer reinen Namensänderung. Die Testdatenbank ist leer, also gilt dem Sweep jede echte Datei in `storage/app/public` als verwaist und wird gelöscht.

Das ist nicht theoretisch: am 2026-08-25 hat ein Testlauf beide echten Vereinslogos von der Entwicklerplatte gelöscht. Der Fake lag damals pro Testdatei, und eine neu hinzugekommene Datei hatte ihn nicht. Genau diese Konstruktion ist der Fehler — sie verlangt, dass jemand bei jeder neuen Datei daran denkt.

`tests/Unit/StorageIsolationTest.php` prüft, dass der Fake in Pest.php bleibt. Verifiziert wurde die Wirkung mit Köderdateien (auch einer bewusst unreferenzierten) über die volle Suite.

`storage/downloads` ist davon ausgenommen und bewusst ungeschützt: `Subscription::generateSepa()` und `Club::buildBlsvStatistic()` schreiben dort mit `file_put_contents(storage_path(...))` am Storage-Facade vorbei, wo `Storage::fake` nicht greift. Der Inhalt ist temporär und wird vom Programm neu erzeugt — am 2026-08-25 als unkritisch eingestuft, nicht absichern.
