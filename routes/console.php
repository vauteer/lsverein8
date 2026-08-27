<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:backup')
    ->dailyAt('23:15');

// Telescope writes a row per request and never cleans up after itself. Pruned
// before the backup runs so the nightly dump is not taken while the largest
// table in the database is at its fullest.
Schedule::command('telescope:prune --hours=48')
    ->dailyAt('23:00');
