<?php

use App\Backup;
use App\Enums\ClubRole;
use App\Models\Club;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->club = Club::factory()->create(['id' => 1]);

    $this->backupDirectory = sys_get_temp_dir().'/backup-ui-test-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($this->backupDirectory);

    // A database name that certainly does not exist, so that mysqldump
    // deterministically fails when a test reaches the shell pipeline, and
    // never touches the real lsverein8 database.
    config([
        'backup.directory' => $this->backupDirectory,
        'database.connections.mariadb.database' => 'backup_ui_test_missing_db',
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->backupDirectory);
});

function backupUser(ClubRole $role = ClubRole::Admin, array $attributes = []): User
{
    $user = User::factory()->create([...$attributes, 'club_id' => 1]);
    $user->clubs()->attach(1, ['role' => $role->value]);

    return $user;
}

function createUiBackupFile(CarbonInterface $date, string $content = 'dump'): string
{
    $filename = 'backup_ui_test_missing_db_'.$date->format(Backup::DATE_FORMAT).'.sql.gz';
    File::put(Backup::path($filename), $content);

    return $filename;
}

test('guests are redirected to login', function () {
    $this->get(route('backups.index'))->assertRedirect(route('login'));
});

test('a club admin may not view, create, restore or delete backups', function () {
    // A backup spans every club, so even a club admin is not enough — this is
    // the one place where hasAdminRights() deliberately is not the check.
    $filename = createUiBackupFile(now()->subHour());

    foreach ([ClubRole::Basic, ClubRole::Advanced, ClubRole::Admin] as $role) {
        $this->actingAs(backupUser($role));

        $this->get(route('backups.index'))->assertForbidden();
        $this->post(route('backups.store'))->assertForbidden();
        $this->get(route('backups.download', ['filename' => $filename]))->assertForbidden();
        $this->post(route('backups.restore', ['filename' => $filename]))->assertForbidden();
        $this->delete(route('backups.destroy', ['filename' => $filename]))->assertForbidden();
    }
});

test('the index lists backups newest first with age and size', function () {
    $this->actingAs(backupUser(attributes: ['admin' => true]));
    createUiBackupFile(now()->subDays(3));
    $newest = createUiBackupFile(now()->subHours(2), str_repeat('x', 2048));

    $this->get(route('backups.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('backups/Index')
            ->has('backups', 2)
            ->where('backups.0.filename', $newest)
            ->where('backups.0.size', '2.0 KB')
            ->has('backups.0.date')
            ->has('backups.0.age')
            // The raw timestamp stays server-side.
            ->missing('backups.0.timestamp'));
});

test('the sidebar flag follows the gate', function () {
    $this->actingAs(backupUser())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.canManageBackups', false));

    $this->actingAs(backupUser(attributes: ['admin' => true]))
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.canManageBackups', true));
});

test('a backup can be downloaded', function () {
    $this->actingAs(backupUser(attributes: ['admin' => true]));
    $filename = createUiBackupFile(now()->subHour());

    $this->get(route('backups.download', ['filename' => $filename]))
        ->assertSuccessful()
        ->assertDownload($filename);
});

test('unknown or invalid filenames are rejected with 404', function (string $routeName, string $method) {
    $this->actingAs(backupUser(attributes: ['admin' => true]));
    createUiBackupFile(now()->subHour());

    $this->{$method}(route($routeName, ['filename' => 'unknown.sql.gz']))->assertNotFound();
    $this->{$method}(route($routeName, ['filename' => '.env']))->assertNotFound();
    $this->{$method}(route($routeName, [
        'filename' => 'backup_ui_test_missing_db_2020_01_01_00_00_00.sql.gz',
    ]))->assertNotFound();
})->with([
    ['backups.download', 'get'],
    ['backups.restore', 'post'],
    ['backups.destroy', 'delete'],
]);

test('a traversing filename cannot reach a file outside the backup directory', function () {
    $this->actingAs(backupUser(attributes: ['admin' => true]));

    $outside = sys_get_temp_dir().'/backup-ui-outside-'.bin2hex(random_bytes(4)).'.sql.gz';
    File::put($outside, 'secret');

    try {
        $this->get(route('backups.download', ['filename' => '../'.basename($outside)]))
            ->assertNotFound();

        expect(File::exists($outside))->toBeTrue();
    } finally {
        File::delete($outside);
    }
});

test('a backup can be deleted', function () {
    $this->actingAs(backupUser(attributes: ['admin' => true]));
    $filename = createUiBackupFile(now()->subHour());

    $this->delete(route('backups.destroy', ['filename' => $filename]))
        ->assertRedirect(route('backups.index'));

    expect(File::exists(Backup::path($filename)))->toBeFalse();
});

test('creating a backup is skipped with an info toast when nothing has changed', function () {
    $this->actingAs(backupUser(attributes: ['admin' => true]));
    settleTrackedTables();
    createUiBackupFile(now()->subDay());

    $this->post(route('backups.store'))
        ->assertRedirect(route('backups.index'));

    expect(session('inertia.flash_data')['toast'])->toBe([
        'type' => 'info',
        'message' => __('No changes since the last backup.'),
    ]);
    expect(File::glob($this->backupDirectory.'/*.sql.gz'))->toHaveCount(1);
});

test('creating a backup reports an error when the dump fails', function () {
    $this->actingAs(backupUser(attributes: ['admin' => true]));

    $this->post(route('backups.store'))
        ->assertRedirect(route('backups.index'));

    expect(session('inertia.flash_data')['toast']['type'])->toBe('error');
    expect(File::glob($this->backupDirectory.'/*.sql.gz'))->toBeEmpty();
});

test('a restore is rejected for a corrupt backup file', function () {
    $this->actingAs(backupUser(attributes: ['admin' => true]));
    $filename = createUiBackupFile(now()->subHour(), 'not a gzip file');

    $this->post(route('backups.restore', ['filename' => $filename]))
        ->assertRedirect(route('backups.index'));

    // The corrupt file is left alone, and no safety backup was attempted.
    expect(File::exists(Backup::path($filename)))->toBeTrue()
        ->and(File::glob($this->backupDirectory.'/*.sql.gz'))->toHaveCount(1);
});

test('a restore is aborted when the safety backup cannot be created', function () {
    $this->actingAs(backupUser(attributes: ['admin' => true]));
    $filename = createUiBackupFile(now()->subHour(), (string) gzencode('-- valid sql dump'));

    $this->post(route('backups.restore', ['filename' => $filename]))
        ->assertRedirect(route('backups.index'));

    expect(session('inertia.flash_data')['toast']['type'])->toBe('error')
        ->and(File::glob($this->backupDirectory.'/*.sql.gz'))->toHaveCount(1);
});
