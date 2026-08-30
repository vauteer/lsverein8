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
- **Die einzige Ausnahme, 2026-08-30: `insert_roles_defaults` und `insert_events_defaults` sind auskommentiert.** Es sind reine *Daten*-Migrationen, am Schema-Diff ändert sich also nichts. Sie legten je sieben Zeilen mit `club_id IS NULL` an; seit die Spalte NOT NULL ist, kann es die nicht mehr geben, und ein frischer Build wäre an der Migration gescheitert, die die Spalte anzieht. Die Dateien bleiben samt ihrer Zeile in `migrations` liegen — die Produktionsdatenbank hat sie 2022 ausgeführt, ihre Zeilen gehören längst Verein 1. Ein neuer Verein bekommt seine eigenen aus `Role::DEFAULTS` / `Event::DEFAULTS` in `ClubController::store()`.
- `password_resets` was renamed to `password_reset_tokens` (Laravel/Fortify default) by a 2026_08_22 migration; the 2014 migration still creates the old name, and the rename runs after it on a fresh build.
- `failed_jobs` comes from the 2019 lsverein7 migration, so the 2026 jobs migration creates only `jobs` + `job_batches`.
- To validate migration changes, build into a throwaway database and diff the schema dump against `lsverein8` rather than resetting it.

## clubs.sepa/sepa_date wurden 2026-08-29 umbenannt
Die 2022er Migration `create_clubs_table` legt weiterhin `sepa` und `sepa_date` an; `2026_08_29_061346_rename_sepa_columns_on_clubs_table` benennt sie danach in `sepa_creditor_id` und `sepa_mandate_date` um (gleiches Muster wie `password_resets` → `password_reset_tokens`). Bei einem frischen Build läuft der Rename also nach der Erstellung — die historische Migration nicht anfassen.

Grund für die Namen: `sepa` benannte den Standard statt des Werts. Die Spalte hält die SEPA-Gläubiger-ID (z. B. DE31ZZZ00000102910), die `Subscription::generateSepa()` als `sepaId` in die XML schreibt; `sepa_mandate_date` ist das Vorgabe-Mandatsdatum, gegen das dort `$defaultDate->max($member->entry())` läuft.
