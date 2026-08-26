---
paths:
  - app/Backup.php
  - app/ClubExport.php
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

**Geteilte Zeilen kommen mit, soweit benutzt.** `sections`, `events` und `roles` haben nullable `club_id`. lsverein7 exportierte nur `club_id = N` — sobald ein Mitglied einer installationsweiten Zeile zugeordnet ist, zeigt der Pivot im Export ins Leere. `ownOrShared()` nimmt zusätzlich genau die geteilten Zeilen mit, die die Mitglieder dieses Vereins referenzieren. Am 2026-08-26 in Produktion geprüft: aktuell nutzt kein Verein eine — nur deshalb ist der Fehler nie aufgefallen. Die Migration `insert_events_defaults` sät sieben davon in jede Installation.

**`tracings` ist bewusst nicht dabei:** das Protokoll hängt an `user_id`/`row_id` ohne eigenen Verein, eine ehrliche Aufteilung gibt es nicht. `debits` ist neu dabei (in lsverein7 vergessen), es hängt über `member_id` am Verein.

Der Verein kommt aus der **Route**, nicht aus `currentClub()`: root arbeitet womöglich in Verein 1 und sieht die Seite von Verein 2 an. `ClubPolicy::export()` delegiert an `update()` — root für jeden Verein, Club-Admin nur für den aktuellen.
