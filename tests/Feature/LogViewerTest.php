<?php

use App\Enums\ClubRole;
use App\Models\Club;
use App\Models\User;
use Opcodes\LogViewer\Facades\LogViewer;

/**
 * The log viewer route is registered by opcodesio/log-viewer itself and
 * carries no auth middleware — the only thing standing in front of it is the
 * viewLogViewer gate defined in AppServiceProvider. Reading storage/logs means
 * reading every club's data, so it is root-only.
 */
beforeEach(function () {
    $this->club = Club::factory()->create(['id' => 1]);
});

function logViewerUser(ClubRole $role = ClubRole::Admin, array $attributes = []): User
{
    $user = User::factory()->create([...$attributes, 'club_id' => 1]);
    $user->clubs()->attach(1, ['role' => $role->value]);

    return $user;
}

test('a guest is sent to the login screen', function () {
    // The package route carries no auth middleware of its own; config/log-viewer
    // adds it, so a guest gets the login page rather than a bare 403.
    $this->get('/log-viewer')->assertRedirect(route('login'));
});

test('a club admin cannot reach the log viewer', function () {
    $this->actingAs(logViewerUser())
        ->get('/log-viewer')
        ->assertForbidden();
});

test('a root account can reach the log viewer', function () {
    $this->actingAs(logViewerUser(attributes: ['admin' => true]))
        ->get('/log-viewer')
        ->assertOk();
});

test('the gate denies a user whose admin attribute was never loaded', function () {
    // users.admin is NOT NULL DEFAULT 0, but a model created without an
    // explicit admin does not read that default back, so $user->admin is null
    // on the instance. The gate must still return false rather than fatal.
    $user = logViewerUser();

    expect($user->getAttributes())->not->toHaveKey('admin')
        ->and($user->admin)->toBeNull()
        ->and($user->can('viewLogViewer'))->toBeFalse();
});

test('the sidebar flag follows the gate, not the club role', function () {
    // canViewLogs drives whether the Logs entry renders at all, so it has to
    // agree with the gate the route enforces.
    $this->actingAs(logViewerUser())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('auth.canManageUsers', true)
            ->where('auth.canViewLogs', false)
        );

    $this->actingAs(logViewerUser(attributes: ['admin' => true]))
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.canViewLogs', true));
});

test('a guest gets no log flag', function () {
    $this->get(route('login'))
        ->assertInertia(fn ($page) => $page->where('auth.canViewLogs', false));
});

test('only the application log is listed', function () {
    // "I only need laravel.log": include_files is narrowed to laravel*.log, so
    // storage/logs/browser.log and any server logs never appear.
    //
    // storage/logs is gitignored and starts out empty on CI, so this makes its
    // own files rather than asserting against whatever happens to be there.
    $wanted = storage_path('logs/laravel-'.uniqid().'.log');
    $unwanted = storage_path('logs/browser-'.uniqid().'.log');

    // Real Laravel-format lines in both: an empty file is an "unknown" file and
    // hide_unknown_files would filter it regardless of include_files, which
    // would make this test pass for the wrong reason.
    $line = '['.now()->format('Y-m-d H:i:s').'] testing.ERROR: log viewer fixture'.PHP_EOL;

    file_put_contents($wanted, $line);
    file_put_contents($unwanted, $line);

    try {
        LogViewer::clearFileCache();

        $names = collect(LogViewer::getFiles())->map(fn ($file) => $file->name);

        expect($names)->toContain(basename($wanted))
            ->and($names)->not->toContain(basename($unwanted));
    } finally {
        @unlink($wanted);
        @unlink($unwanted);
        LogViewer::clearFileCache();
    }
});

test('the include list is narrowed to the application log', function () {
    // Re-publishing the package config would silently restore its own list of
    // system log paths, and nothing above would necessarily catch it.
    expect(config('log-viewer.include_files'))->toBe(['laravel*.log'])
        ->and(config('log-viewer.hide_unknown_files'))->toBeTrue();
});
