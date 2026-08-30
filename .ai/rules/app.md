---
paths:
  - app/Backup.php
  - app/ClubExport.php
  - app/AssignedMemberCount.php
---

# App

## Backup: root-only, and tests must never run the real dump
Ported from lscraft5. `App\Backup` shells out to mysqldump/mysql against `config('database.connections.mariadb')` — which on this machine is the live lsverein8 database with 585 real members. A `Backup::restore()` executed by mistake replaces it wholesale.

Every test therefore overrides `database.connections.mariadb.database` in `beforeEach` (to `testdb`, or to a name that certainly does not exist so the pipeline fails fast) and points `backup.directory` at a temp dir. The shell pipelines are asserted as strings through `ReflectionMethod` on `buildDumpCommand`/`buildRestoreCommand`; nothing in the suite executes a real dump. Keep it that way — do not add a test that calls `Backup::create()` expecting success.

Authorization is the `manageBackups` gate (AppServiceProvider), root-only via `users.admin`, applied as `Route::middleware('can:manageBackups')` in routes/web.php. This is the one place where `hasAdminRights()` is deliberately NOT the check: a dump spans every club, so a club admin must not reach it. `auth.canManageBackups` mirrors the gate for the sidebar.

`isDirty()` consults `updated_at` on all 16 data tables including the pivots — `tracings` is excluded because it has no `updated_at`. A fresh test database is never quiet: `insert_roles_defaults` and `insert_events_defaults` stamp their rows at migration time, so use the shared `settleTrackedTables()` helper in tests/Pest.php before asserting "nothing has changed".

`storage/backups/.gitignore` (`*` plus `!.gitignore`) is what keeps dumps of real member data out of the repository. Do not delete it.

## Vereinsexport: TRUNCATE im Skript, und geteilte Zeilen müssen mit
`App\ClubExport` erzeugt den vereinsspezifischen Teil der Datenbank als SQL-Skript (16 Tabellen). Portiert aus lsverein7s `ExportController` + `SqlConverter`, mit vier bewussten Abweichungen.

**Das Skript enthält `TRUNCATE` vor jedem `INSERT`.** Es ist damit **nur für eine leere Datenbank** geeignet: eingespielt in eine Installation mit weiteren Vereinen löscht es deren Daten. So aus lsverein7 übernommen, weil es die Wiederherstellung sauber macht — aber die Warnung steht jetzt sowohl im Kopf der erzeugten Datei als auch auf der Vereinsseite. Nicht stillschweigend entfernen; wer nur `INSERT`s will, ändert `tableSql()` und muss dann die Doppel-Import-Frage beantworten.

**Rohdaten statt Model-Attribute.** lsverein7 las Eloquent-Attribute und machte die Casts von Hand rückgängig (`->cast('gender', 'gender')`, `->cast('from', 'date')`, mit `dd($type)` im default-Zweig). Hier läuft alles über `DB::table()`: was die Spalte hält, geht raus, nichts ist zurückzurechnen.

**Quoting über `DB::getPdo()->quote()`.** lsverein7 nutzte `str_replace("'", "\\'", …)` — der Backslash selbst blieb unescaped, ein Wert mit `\` am Ende schloss das Literal zu früh und verschob jede folgende Spalte. `PDO::quote` ist treiberabhängig und damit für MySQL *und* SQLite richtig; ein Test auf SQLite beweist deshalb nur, dass das Literal geschlossen ist, nicht das genaue Escaping.

**Geteilte Zeilen kommen mit, soweit benutzt.** `events` und `roles` haben nullable `club_id` (`sections` auch, bis 2026-08-30). lsverein7 exportierte nur `club_id = N` — sobald ein Mitglied einer installationsweiten Zeile zugeordnet ist, zeigt der Pivot im Export ins Leere. `ownOrShared()` nimmt zusätzlich genau die geteilten Zeilen mit, die die Mitglieder dieses Vereins referenzieren. Am 2026-08-26 in Produktion geprüft: aktuell nutzt kein Verein eine — nur deshalb ist der Fehler nie aufgefallen. Die Migration `insert_events_defaults` sät sieben davon in jede Installation.

**`tracings` ist bewusst nicht dabei:** das Protokoll hängt an `user_id`/`row_id` ohne eigenen Verein, eine ehrliche Aufteilung gibt es nicht. `debits` ist neu dabei (in lsverein7 vergessen), es hängt über `member_id` am Verein.

Der Verein kommt aus der **Route**, nicht aus `currentClub()`: root arbeitet womöglich in Verein 1 und sieht die Seite von Verein 2 an. `ClubPolicy::export()` delegiert an `update()` — root für jeden Verein, Club-Admin nur für den aktuellen.

## Die Mitglieder-Zähler müssen zur verlinkten Auswahl passen — count(distinct), nicht withCount
Die Zahlen auf den Listen von Abteilungen, Funktionen und Inventar sind **Links** auf die Mitgliederliste mit der passenden Auswahl. Damit Zahl und Ziel nie auseinanderlaufen, baut `App\AssignedMemberCount::for()` jeden Zähler als Unterabfrage, die genau den zugehörigen Member-Scope spiegelt.

| Spalte | Scope-Aufruf | spiegelt | Operatoren | `members()`? |
| --- | --- | --- | --- | --- |
| Abteilungen „Mitglieder" | `Section::withCurrentMemberCount()` | `Member::inSections()` | `<=` / `>=` | ja |
| Funktionen „Aktuell" | `Role::withMemberCounts()` | `Member::hasRole()` | `<` / `>` | ja |
| Funktionen „Jemals" | dieselbe | `Member::everRole()` | `<` / — | **nein** |
| Inventar „Aktuell" | `Item::withMemberCounts()` | `Member::hasItem()` | `<` / `>` | ja |
| Inventar „Jemals" | dieselbe | `Member::everItem()` | `<` / — | **nein** |

Drei Dinge, die man nicht „aufräumen" darf:

1. **`count(distinct member_id)`, nicht `withCount()`.** Die Pivots erlauben dasselbe Paar mehrfach mit verschiedenen Zeiträumen. In Produktion (2026-08-26): vier solche Paare in `member_role`, eines davon mit **zwei gleichzeitig offenen** Zeiträumen — `count(*)` meldete diese eine Person als zwei. `withCount()` kann kein DISTINCT, deshalb `addSelect([...])` mit einer Unterabfrage.
2. **Die unterschiedlichen Operatoren sind Absicht.** Abteilungen sind an beiden Enden einschließend, Funktionen und Inventar strikt — aus lsverein7 übernommen. Wer nur den Zähler vereinheitlicht, bringt ihn wieder aus dem Tritt mit der Auswahl; eine echte Vereinheitlichung müsste die drei Member-Scopes ändern.
3. **Die „Jemals"-Auswahlen dürfen kein `members()` bekommen.** Sie existieren, um Ehemalige zu zeigen. `ever_item` hatte es fälschlich (`$query->members()->everItem($id)`) und ließ damit genau die Leute weg, für die es gedacht ist; `ever_role` hatte es nie. Am 2026-08-26 in `SelectsMembers::applyFilter()` korrigiert, Test in ItemManagementTest.

Alle Argumente von `for()` sind `literal-string`, weil sie in rohes SQL gehen — nichts aus einem Request darf sie erreichen.

`isUsed()` ist davon unberührt und zählt weiter jede Zeile: für „darf gelöscht werden" ist die Historie die richtige Frage.

Testfalle: `insert_roles_defaults` sät sieben installationsweite Funktionen (u. a. „Kassier"), `insert_events_defaults` sieben Ehrungen. Eine Fixture mit so einem Namen kollidiert oder sortiert sich davor — deshalb „Platzwart" plus `search` in RoleManagementTest.

## Alle fünf Mitglieder-Zähler laufen über AssignedMemberCount
Jede Zahl in einer Listenspalte „Mitglieder"/„Aktuell"/„Jemals" ist ein **Link** auf die Mitgliederliste mit der passenden Auswahl. Damit Zahl und Ziel nie auseinanderlaufen, baut `App\AssignedMemberCount` jeden Zähler als Unterabfrage, die genau den zugehörigen Member-Scope spiegelt. Drei Formen:

| Methode | wer | Bedingung |
| --- | --- | --- |
| `current()` | Abteilung/Funktion/Inventar „Aktuell" | `memberIds()` **und** offener Zeitraum |
| `ever()` | Funktion/Inventar „Jemals", Ehrungen | nur: Zuordnung hat begonnen (`from`/`date` < Stichtag) |
| `held()` | Beiträge | nur `memberIds()` — der Pivot hat gar keine Daten |

| Spalte | Scope | spiegelt | Operatoren |
| --- | --- | --- | --- |
| Abteilungen | `Section::withCurrentMemberCount()` | `Member::inSections()` | `<=` / `>=` |
| Funktionen Aktuell/Jemals | `Role::withMemberCounts()` | `hasRole()` / `everRole()` | `<` / `>` |
| Inventar Aktuell/Jemals | `Item::withMemberCounts()` | `hasItem()` / `everItem()` | `<` / `>` |
| Ehrungen | `Event::withMemberCount()` | `hadEvent()` | `date <` |
| Beiträge | `Subscription::withCurrentMemberCount()` | `members()->hasSubscription()` | — |

Vier Dinge, die man nicht „aufräumen" darf:

1. **`count(distinct member_id)`, nie `withCount()`.** Jeder Pivot erlaubt dasselbe Paar mehrfach. In Produktion (2026-08-26): vier Paare in `member_role` (eines mit **zwei gleichzeitig offenen** Zeiträumen — `count(*)` meldete eine Person als zwei), eines in `event_member`. `withCount()` kann kein DISTINCT.
2. **Die unterschiedlichen Operatoren sind Absicht** und kommen aus lsverein7: Abteilungen einschließend, Funktionen/Inventar strikt. Nur den Zähler zu vereinheitlichen bringt ihn wieder aus dem Tritt mit der Auswahl.
3. **`ever()` bekommt kein `memberIds()`.** Die „Jemals"-Auswahlen und Ehrungen existieren, um Ehemalige zu zeigen. `ever_item` hatte fälschlich `members()` und ließ genau die weg — am 2026-08-26 in `SelectsMembers::applyFilter()` korrigiert.
4. **Ehrungen zählen `date < Stichtag`.** Sechs Zeilen in `event_member` sind auf heute oder später datiert; `withCount()` zählte sie, `hadEvent()` nicht.

Größenordnung des alten Fehlers an echten Daten: Fussball 222 → 103, Tennis 139 → 68, Beitrag „Erwachsen" 242 → 140, „Jugend" 14 → 2.

`isUsed()` ist unberührt und zählt weiter jede Zeile: für „darf gelöscht werden" ist die Historie die richtige Frage.

Testfallen: `insert_roles_defaults` sät sieben Funktionen (u. a. „Kassier"), `insert_events_defaults` sieben Ehrungen — eine Fixture mit so einem Namen kollidiert oder sortiert sich davor. Und eine Fixture, die ein Mitglied nur an den Pivot hängt, ohne `memberships()->attach()`, zählt bei `current()`/`held()` **nicht** mit.

## Backup-Dateinamen tragen UTC im Namen — nicht umrechnen
Alles wird in UTC gespeichert: Laravel (`app.timezone`), die Datenbank, der Server. Der Zeitstempel im Backup-Dateinamen ist deshalb ebenfalls UTC und trägt seit 2026-08-28 das Suffix `_utc` (`Backup::TIMEZONE_SUFFIX`), weil er sonst auf einem deutschen Server als Ortszeit gelesen wird und zwei Stunden falsch wirkt.

**Beschriften, nicht umrechnen** — bewusst so entschieden, nachdem die Alternative (eine `app.display_timezone` zum Umrechnen an den Rändern) gebaut und wieder verworfen wurde. Sie war schwerer als das Problem und schuf eine Fehlerklasse, die es mit einer Uhr gar nicht gibt: `isDirty()` vergleicht `Backup::latestDate()` gegen `max(updated_at)` aus der Datenbank. Sobald das ein Anzeige-String in einer anderen Zone ist, wirkt das Backup jünger als jede Änderung, `isDirty()` meldet dauerhaft `false` und **der nächtliche Dump läuft nie wieder**, ohne Fehlermeldung. Keine zweite Zone einführen. Auch `clubs.timezone` wurde erwogen und verworfen: ein Backup umfasst alle Vereine (`manageBackups` ist root-only), kein Verein könnte also sagen, welche Zeit im Dateinamen steht.

Nebenbei: UTC-Namen sortieren chronologisch und wiederholen bei der Zeitumstellung keine Stunde — Ortszeit im Dateinamen könnte ein Backup überschreiben.

**Das Suffix ist Pflicht, nicht optional** (Gerald am 2026-08-28: Backups von vorher sind uninteressant). Dateien im alten Namensschema fallen damit aus `all()` heraus — sie erscheinen nicht mehr in der Liste und werden von `deleteOld()` auch nicht mehr gelöscht, bleiben also bis zum Aufräumen von Hand liegen. `dateFromFilename()` schneidet das Suffix mit `Str::chopEnd()` ab.

`routes/console.php` bekommt bewusst **kein** `->timezone()`: `dailyAt('23:15')` ist damit 23:15 UTC = 01:15 deutscher Zeit. Steht als Kommentar dort. `tests/Feature/BackupTest.php` pinnt das Namensschema ("a name without the zone suffix is not a backup") und das `isDirty()`-Verhalten.
