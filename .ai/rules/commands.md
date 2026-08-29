---
paths:
  - 'app/Console/Commands/**'
  - app/Console/Commands/PruneTracingsCommand.php
---

# Commands

## Console-Commands aus lscraft5 portiert — app:user musste an die Vereine angepasst werden
Fünf Commands, alle mit `#[Signature]`/`#[Description]`-Attributen (nicht der `$signature`-Property), jeder mit einem Feature-Test, der auch `Artisan::all()` auf die Signatur prüft.

`app:user {name} {email} {--password=} {--club=1} {--role=admin}` ist die einzige Portierung mit inhaltlicher Abweichung. In lscraft5 legte sie nur name/email/password an; hier wäre so ein Konto wertlos, weil jeder Scope an `users.club_id` hängt und die Rechte am `club_user`-Pivot. Sie setzt deshalb zusätzlich `club_id`, `landing_page` (NOT NULL mit Default, den ein frisch erzeugtes Model nicht zurückliest — dieselbe Falle wie in UserFactory) und den Pivot per **`syncWithoutDetaching()`**: ein Konto kann mehreren Vereinen gehören, und der Command spricht nur für einen. Default-Verein ist 1, weil `currentClubId()` auf der CLI ohnehin dorthin auflöst.

`club_user.role` ist ein roher `int`, **kein** ClubRole-Cast (siehe ClubUser) — im Test gegen `ClubRole::Admin->value` prüfen, nicht gegen den Case.

`aws:test` prüft die S3-Zugangsdaten, bevor man `BACKUP_AWS_ENABLED` einschaltet; die Disk ist dieselbe, auf die `Backup::upload()` die Dumps schiebt. Der Test faked sie (`Storage::fake('s3')`) — das echte Ziel ist der Offsite-Backup-Speicher.

## app:prune-tracings: Monatsgrenze statt rollierendem Jahr, und was dabei verloren geht
`app:prune-tracings {--months=12} {--dry-run}` löscht `tracings` älter als die Aufbewahrung; sonntags 22:45 UTC geplant (routes/console.php), vor telescope:prune und dem Backup.

**Der Schnitt liegt auf `startOfMonth()->subMonths($months)`, nicht auf einem rollierenden `subMonths(12)`.** Sonst könnte die Login-Karte des Dashboards ihren ältesten Balken verlieren: die zeichnet zwölf ganze Monate ab dem Anfang des laufenden. Beides muss zusammen bleiben — wer das Fenster hier ändert, ändert die Karte mit. `PruneTracingsCommandTest` pinnt genau das („keeps every month the dashboard login card draws").

**Die jüngste Login-Zeile ruhender Konten ist ausgenommen** (`lastLoginOfDormantAccounts()`). `User::lastLogin()` und der `withLastLoginAt`-Scope lesen dieselbe Tabelle: ohne die Ausnahme stünde ein Konto, dessen letzter Login aus dem Fenster fällt, in der Benutzerliste auf „nie" — das schreibt eine Tatsache um, statt eine alte zu vergessen. In Produktion betrifft das 2 von 14 Konten (letzte Logins 2022-09 und 2024-01), der Lauf löscht dort 770 statt 772 Zeilen.

Verschont wird nur, wer **innerhalb** des Fensters gar keinen Login hat — bei aktiven Konten sind die alten Zeilen genau das, worum es der Aufbewahrung geht. Und nur `ActionType::Login` zählt: eine neuere `Update`-Zeile macht ein Konto nicht angemeldet. „Neueste Zeile je Gruppe" hat keine portable Einzelabfrage, deshalb wird in SQL sortiert und in PHP über `unique('user_id')` entdoppelt; geladen werden dabei nur die alten Logins stiller Konten.

`Backup::isDirty()` beobachtet `tracings` nicht (keine `updated_at`), ein Prune allein löst also kein Backup aus.
