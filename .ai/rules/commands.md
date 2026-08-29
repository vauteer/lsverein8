---
paths:
  - 'app/Console/Commands/**'
---

# Commands

## Console-Commands aus lscraft5 portiert — app:user musste an die Vereine angepasst werden
Fünf Commands, alle mit `#[Signature]`/`#[Description]`-Attributen (nicht der `$signature`-Property), jeder mit einem Feature-Test, der auch `Artisan::all()` auf die Signatur prüft.

`app:user {name} {email} {--password=} {--club=1} {--role=admin}` ist die einzige Portierung mit inhaltlicher Abweichung. In lscraft5 legte sie nur name/email/password an; hier wäre so ein Konto wertlos, weil jeder Scope an `users.club_id` hängt und die Rechte am `club_user`-Pivot. Sie setzt deshalb zusätzlich `club_id`, `landing_page` (NOT NULL mit Default, den ein frisch erzeugtes Model nicht zurückliest — dieselbe Falle wie in UserFactory) und den Pivot per **`syncWithoutDetaching()`**: ein Konto kann mehreren Vereinen gehören, und der Command spricht nur für einen. Default-Verein ist 1, weil `currentClubId()` auf der CLI ohnehin dorthin auflöst.

`club_user.role` ist ein roher `int`, **kein** ClubRole-Cast (siehe ClubUser) — im Test gegen `ClubRole::Admin->value` prüfen, nicht gegen den Case.

`aws:test` prüft die S3-Zugangsdaten, bevor man `BACKUP_AWS_ENABLED` einschaltet; die Disk ist dieselbe, auf die `Backup::upload()` die Dumps schiebt. Der Test faked sie (`Storage::fake('s3')`) — das echte Ziel ist der Offsite-Backup-Speicher.
