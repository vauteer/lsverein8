---
paths:
  - app/Backup.php
---

# App

## Backup: root-only, and tests must never run the real dump
Ported from lscraft5. `App\Backup` shells out to mysqldump/mysql against `config('database.connections.mariadb')` — which on this machine is the live lsverein8 database with 585 real members. A `Backup::restore()` executed by mistake replaces it wholesale.

Every test therefore overrides `database.connections.mariadb.database` in `beforeEach` (to `testdb`, or to a name that certainly does not exist so the pipeline fails fast) and points `backup.directory` at a temp dir. The shell pipelines are asserted as strings through `ReflectionMethod` on `buildDumpCommand`/`buildRestoreCommand`; nothing in the suite executes a real dump. Keep it that way — do not add a test that calls `Backup::create()` expecting success.

Authorization is the `manageBackups` gate (AppServiceProvider), root-only via `users.admin`, applied as `Route::middleware('can:manageBackups')` in routes/web.php. This is the one place where `hasAdminRights()` is deliberately NOT the check: a dump spans every club, so a club admin must not reach it. `auth.canManageBackups` mirrors the gate for the sidebar.

`isDirty()` consults `updated_at` on all 16 data tables including the pivots — `tracings` is excluded because it has no `updated_at`. A fresh test database is never quiet: `insert_roles_defaults` and `insert_events_defaults` stamp their rows at migration time, so use the shared `settleTrackedTables()` helper in tests/Pest.php before asserting "nothing has changed".

`storage/backups/.gitignore` (`*` plus `!.gitignore`) is what keeps dumps of real member data out of the repository. Do not delete it.
