---
paths:
  - 'database/migrations/**'
---

# Migrations

## Migrations mirror the live lsverein7 database — never migrate:fresh
The `lsverein8` MariaDB database IS the production lsverein7 database (585 members, 1112 tracings, 16 users). The Laravel starter-kit defaults (0001_01_01_*) were deleted and replaced with the 24 lsverein7 migrations under their original filenames, so the pre-existing `migrations` table rows match and they register as already-Ran.

Consequences:
- Never run `migrate:fresh`/`migrate:refresh`/`db:wipe` against this database — it destroys real data.
- The 24 files dated 2014–2023 are history. Do not edit them; they are verified to rebuild the live schema byte-for-byte (mysqldump --no-data diff). Add new migrations instead.
- `password_resets` was renamed to `password_reset_tokens` (Laravel/Fortify default) by a 2026_08_22 migration; the 2014 migration still creates the old name, and the rename runs after it on a fresh build.
- `failed_jobs` comes from the 2019 lsverein7 migration, so the 2026 jobs migration creates only `jobs` + `job_batches`.
- To validate migration changes, build into a throwaway database and diff the schema dump against `lsverein8` rather than resetting it.
