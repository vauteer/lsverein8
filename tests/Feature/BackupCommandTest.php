<?php

use App\Backup;
use Carbon\CarbonInterface;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->backupDirectory = sys_get_temp_dir().'/backup-command-test-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($this->backupDirectory);

    // A database name that certainly does not exist, so that mysqldump
    // deterministically fails when a test reaches the shell pipeline, and
    // never touches the real lsverein8 database.
    config([
        'backup.directory' => $this->backupDirectory,
        'database.connections.mariadb.database' => 'backup_command_test_missing_db',
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->backupDirectory);
});

function createCommandBackupFile(CarbonInterface $date): string
{
    $filename = 'backup_command_test_missing_db_'.$date->format(Backup::DATE_FORMAT).'.sql.gz';
    File::put(Backup::path($filename), 'dump');

    return $filename;
}

test('the command skips when nothing has changed', function () {
    settleTrackedTables();
    createCommandBackupFile(now()->subDay());

    $this->artisan('app:backup')
        ->expectsOutputToContain('No changes since the last backup.')
        ->assertSuccessful();

    expect(Backup::all())->toHaveCount(1);
});

test('the command fails with a non-zero exit code when the dump fails', function () {
    $this->artisan('app:backup')->assertFailed();

    expect(File::glob($this->backupDirectory.'/*.sql.gz'))->toBeEmpty();
});

test('the force option attempts a backup even without changes', function () {
    createCommandBackupFile(now()->subDay());

    $this->artisan('app:backup', ['--force' => true])->assertFailed();
});

test('the backup command is scheduled daily at 23:15', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'app:backup'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('15 23 * * *');
});
