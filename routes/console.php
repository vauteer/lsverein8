<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Both times below are in the application timezone, which is UTC — the
// scheduler reads them the same way everything else is stored. 23:15 UTC is
// therefore 01:15 the next morning in Germany, which is if anything the
// quieter hour for a dump.
Schedule::command('app:backup')
    ->dailyAt('23:15');

// Telescope writes a row per request and never cleans up after itself. Pruned
// before the backup runs so the nightly dump is not taken while the largest
// table in the database is at its fullest.
Schedule::command('telescope:prune --hours=48')
    ->dailyAt('23:00');

// 05:00 UTC is early morning in Germany, so the digest is waiting rather
// than arriving during the day.
Schedule::command('app:mail-errors')
    ->dailyAt('05:00');

// Weekly, and placed before the Sunday backup so a dump taken that night
// carries the pruned table rather than one last copy of the rows we just
// decided not to keep. Only "so if" — `Backup::isDirty()` does not watch
// `tracings` (it has no `updated_at`), so a prune on its own does not earn a
// backup. The window is twelve whole months, which is what the dashboard's
// login card draws.
Schedule::command('app:prune-tracings --months=12')
    ->weeklyOn(0, '22:45');
