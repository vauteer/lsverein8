<?php

use App\Enums\ActionType;
use App\Models\Tracing;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;

/**
 * The cut is aligned to the start of the month, so a row is described relative
 * to that boundary rather than to today.
 */
function tracingAt(string $modifier, ?User $user = null): Tracing
{
    return Tracing::factory()->create([
        'user_id' => $user?->id ?? User::factory(),
        'at' => now()->startOfMonth()->subMonths(12)->modify($modifier),
    ]);
}

/**
 * An account that signed in today, so the "spare the last login of a dormant
 * account" rule does not mask what a test about the window itself is asserting.
 */
function activeUser(): User
{
    $user = User::factory()->create();
    Tracing::factory()->create(['user_id' => $user->id, 'at' => now()]);

    return $user;
}

it('deletes what is older than the window and keeps the rest', function () {
    $user = activeUser();
    $stale = tracingAt('-1 day', $user);
    $onBoundary = tracingAt('+0 day', $user);
    $fresh = tracingAt('+1 month', $user);

    $this->artisan('app:prune-tracings')
        ->expectsOutputToContain('Deleted 1 tracings')
        ->assertSuccessful();

    // The boundary row is kept: the window is "from here on", not "after here".
    expect(Tracing::find($stale->id))->toBeNull()
        ->and(Tracing::find($onBoundary->id))->not->toBeNull()
        ->and(Tracing::find($fresh->id))->not->toBeNull();
});

it('honours a shorter window', function () {
    $user = activeUser();
    // Month-aligned like the cutoff itself: a bare subMonths(4) off the 31st
    // overflows onto the first of the month and lands exactly on the boundary,
    // which the window keeps rather than deletes.
    Tracing::factory()->create(['user_id' => $user->id, 'at' => now()->startOfMonth()->subMonths(4)]);

    $this->artisan('app:prune-tracings', ['--months' => 3])
        ->expectsOutputToContain('Deleted 1 tracings')
        ->assertSuccessful();

    // Only today's login is left.
    expect(Tracing::count())->toBe(1);
});

it('deletes nothing on a dry run', function () {
    tracingAt('-1 day', activeUser());

    $this->artisan('app:prune-tracings', ['--dry-run' => true])
        ->expectsOutputToContain('Would delete 1 tracings')
        ->assertSuccessful();

    expect(Tracing::count())->toBe(2);
});

it('says so when there is nothing to prune', function () {
    tracingAt('+1 month');

    $this->artisan('app:prune-tracings')
        ->expectsOutputToContain('Nothing to prune')
        ->assertSuccessful();

    expect(Tracing::count())->toBe(1);
});

it('refuses a window shorter than a month', function () {
    tracingAt('-1 day');

    $this->artisan('app:prune-tracings', ['--months' => 0])
        ->expectsOutputToContain('--months must be at least 1')
        ->assertFailed();

    expect(Tracing::count())->toBe(1);
});

it('keeps every month the dashboard login card draws', function () {
    $root = User::factory()->create(['admin' => true]);

    // The oldest bar of the card: the start of the month eleven months back.
    $oldestOnCard = Tracing::factory()->create([
        'user_id' => $root->id,
        'at' => now()->startOfMonth()->subMonths(11),
    ]);

    $this->artisan('app:prune-tracings')->assertSuccessful();

    expect(Tracing::find($oldestOnCard->id))->not->toBeNull();
});

it('spares the newest login of an account that has gone quiet', function () {
    $dormant = User::factory()->create();

    $older = Tracing::factory()->create(['user_id' => $dormant->id, 'at' => now()->subYears(4)]);
    $newest = Tracing::factory()->create(['user_id' => $dormant->id, 'at' => now()->subYears(3)]);

    $this->artisan('app:prune-tracings')
        ->expectsOutputToContain('keeping the last login of 1 dormant account(s)')
        ->assertSuccessful();

    // Otherwise the user list would report them as never having signed in,
    // which rewrites a fact rather than forgetting an old one.
    expect(Tracing::find($newest->id))->not->toBeNull()
        ->and(Tracing::find($older->id))->toBeNull();
});

it('spares nothing for an account that has signed in within the window', function () {
    $active = User::factory()->create();

    $old = Tracing::factory()->create(['user_id' => $active->id, 'at' => now()->subYears(3)]);
    Tracing::factory()->create(['user_id' => $active->id, 'at' => now()]);

    $this->artisan('app:prune-tracings')->assertSuccessful();

    // Its last login is on the record either way, so the old rows are exactly
    // what the retention window is about.
    expect(Tracing::find($old->id))->toBeNull()
        ->and(Tracing::count())->toBe(1);
});

it('does not mistake another action for a login when sparing', function () {
    $dormant = User::factory()->create();

    $login = Tracing::factory()->create(['user_id' => $dormant->id, 'at' => now()->subYears(3)]);
    $edit = Tracing::factory()->create([
        'user_id' => $dormant->id,
        'at' => now()->subYears(2),
        'action_type' => ActionType::Update,
    ]);

    $this->artisan('app:prune-tracings')->assertSuccessful();

    // The newer row is an edit, so the login is still the one worth keeping.
    expect(Tracing::find($login->id))->not->toBeNull()
        ->and(Tracing::find($edit->id))->toBeNull();
});

test('the prune command is scheduled weekly on sunday at 22:45', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'app:prune-tracings'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('45 22 * * 0');
});

it('is registered under its signature', function () {
    expect(array_keys(Artisan::all()))->toContain('app:prune-tracings');
});
