<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Backdate every table Backup::isDirty() consults, so a test can assert
 * "nothing has changed since the last backup".
 *
 * A fresh test database is never quiet on its own: the insert_roles_defaults
 * and insert_events_defaults migrations stamp their rows with the time the
 * migration ran, and factories stamp the current time, so isDirty() sees a
 * change unless those rows are pushed into the past first.
 */
function settleTrackedTables(): void
{
    $tables = [
        'clubs', 'club_member', 'club_user', 'debits', 'events', 'event_member',
        'items', 'item_member', 'members', 'member_role', 'member_section',
        'member_subscription', 'roles', 'sections', 'subscriptions', 'users',
    ];

    foreach ($tables as $table) {
        DB::table($table)->update(['updated_at' => now()->subWeek()]);
    }
}
