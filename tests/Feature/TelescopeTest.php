<?php

use App\Enums\ClubRole;
use App\Models\Club;
use App\Models\User;
use App\Providers\TelescopeServiceProvider;
use Illuminate\Http\Request;
use Laravel\Telescope\EntryType;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;

/**
 * A Telescope entry carries the request payload, the query bindings and the
 * model attributes of whoever was on the site — every club at once, exactly
 * like storage/logs. It is therefore root-only, the same way the log viewer is.
 *
 * Two things stand in front of the package's routes: `auth` (added to
 * config('telescope.middleware')) and the package's Authorize middleware, which
 * asks `Telescope::check()`. That callback is what
 * App\Providers\TelescopeServiceProvider replaces, and it is what these tests
 * exercise directly: `TELESCOPE_ENABLED=false` in phpunit.xml means the package
 * never registers its routes here, so `GET /telescope` is a 404 in the suite
 * and cannot be asserted the way LogViewerTest asserts /log-viewer.
 */
beforeEach(function () {
    $this->club = Club::factory()->create(['id' => 1]);
});

function telescopeUser(ClubRole $role = ClubRole::Admin, array $attributes = []): User
{
    $user = User::factory()->create([...$attributes, 'club_id' => 1]);
    $user->clubs()->attach(1, ['role' => $role->value]);

    return $user;
}

/**
 * A request entry, healthy by default. 500 makes it a failed request, which is
 * the cheapest of the five "points at a problem" predicates to construct.
 */
function telescopeEntry(int $status = 200): IncomingEntry
{
    return IncomingEntry::make(['response_status' => $status])
        ->type(EntryType::REQUEST);
}

function telescopeRequestFrom(?User $user): Request
{
    $request = Request::create('/telescope');
    $request->setUserResolver(fn () => $user);

    return $request;
}

test('a guest is refused', function () {
    expect(Telescope::check(telescopeRequestFrom(null)))->toBeFalse();
});

test('a club admin is refused', function () {
    expect(Telescope::check(telescopeRequestFrom(telescopeUser())))->toBeFalse();
});

test('a root account is let in', function () {
    $root = telescopeUser(attributes: ['admin' => true]);

    expect(Telescope::check(telescopeRequestFrom($root)))->toBeTrue();
});

test('the local environment is not a way in', function () {
    // TelescopeApplicationServiceProvider::authorization() ships an
    // `app()->environment('local') || Gate::check(...)` callback, which would
    // open every entry in the installation to anyone — a guest included, since
    // the package's own routes carry no auth middleware — on a developer
    // machine. The overridden authorization() drops that half, so the gate
    // decides in every environment.
    app()['env'] = 'local';

    expect(Telescope::check(telescopeRequestFrom(null)))->toBeFalse()
        ->and(Telescope::check(telescopeRequestFrom(telescopeUser())))->toBeFalse();
});

test('the gate denies a user whose admin attribute was never loaded', function () {
    // users.admin is NOT NULL DEFAULT 0, but a model created without an
    // explicit admin does not read that default back, so $user->admin is null
    // on the instance. The gate must still return false rather than fatal.
    $user = telescopeUser();

    expect($user->getAttributes())->not->toHaveKey('admin')
        ->and($user->admin)->toBeNull()
        ->and($user->can('viewTelescope'))->toBeFalse();
});

test('a guest reaches the login screen rather than a bare 403', function () {
    // The gate alone would answer a signed-out visitor with 403. Re-publishing
    // config/telescope.php would restore the package's own middleware list and
    // silently take this away.
    expect(config('telescope.middleware'))->toContain('auth');
});

test('the sidebar flag follows the gate, not the club role', function () {
    // canViewTelescope drives whether the entry renders at all, so it has to
    // agree with the gate the route enforces.
    $this->actingAs(telescopeUser())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('auth.canManageUsers', true)
            ->where('auth.canViewTelescope', false)
        );

    $this->actingAs(telescopeUser(attributes: ['admin' => true]))
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.canViewTelescope', true));
});

test('a guest gets no telescope flag', function () {
    $this->get(route('login'))
        ->assertInertia(fn ($page) => $page->where('auth.canViewTelescope', false));
});

test('telescope records nothing during the test suite', function () {
    // Otherwise every test writes a row per query into the in-memory database.
    // PHPUnit's <env value="false"> arrives as an empty string rather than the
    // boolean, so this asserts the effect, not the literal false.
    expect(config('telescope.enabled'))->toBeFalsy();
});

test('outside local only entries pointing at a problem are recorded', function () {
    // A healthy production site should record next to nothing, or the table
    // grows without bound and every dump carries traffic nobody will read.
    app()['env'] = 'production';

    $provider = new TelescopeServiceProvider(app());
    $shouldRecord = new ReflectionMethod($provider, 'shouldRecord');

    expect($shouldRecord->invoke($provider, telescopeEntry()))->toBeFalse()
        ->and($shouldRecord->invoke($provider, telescopeEntry(500)))->toBeTrue();
});

test('TELESCOPE_RECORD_EVERYTHING records healthy traffic too', function () {
    // The escape hatch for chasing a bug in production. Read per entry rather
    // than captured at boot, so flipping it does not need a redeploy.
    app()['env'] = 'production';

    $provider = new TelescopeServiceProvider(app());
    $shouldRecord = new ReflectionMethod($provider, 'shouldRecord');

    config(['telescope.record_everything' => true]);
    expect($shouldRecord->invoke($provider, telescopeEntry()))->toBeTrue();

    config(['telescope.record_everything' => false]);
    expect($shouldRecord->invoke($provider, telescopeEntry()))->toBeFalse();
});

test('the switch is off unless the env var is set', function () {
    // .env.example carries it commented out; nothing may turn it on by itself.
    expect(config('telescope.record_everything'))->toBeFalsy();
});

test('the telescope tables are excluded from the backup', function () {
    // A backup is club data. Telescope entries are debug telemetry that
    // telescope:prune throws away anyway, and their `content` column holds
    // request payloads and query bindings verbatim.
    expect(config('backup.exclude_tables'))
        ->toContain('telescope_entries')
        ->toContain('telescope_entries_tags')
        ->toContain('telescope_monitoring');
});
