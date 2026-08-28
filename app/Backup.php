<?php

namespace App;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;

class Backup
{
    public const string DATE_FORMAT = 'Y_m_d_H_i_s';

    /**
     * Appended after the timestamp, which is UTC like everything else the
     * application stores.
     *
     * The names are read off the server by a person, and an unlabelled stamp
     * on a German machine reads as local time and is then two hours out. The
     * suffix says which clock it is instead of converting to another one:
     * one clock everywhere cannot drift against itself, and UTC names sort
     * chronologically and never repeat an hour when the clocks go back.
     *
     * Required, not optional: dumps written before it existed are of no
     * interest and simply stop being listed.
     */
    private const string TIMEZONE_SUFFIX = '_utc';

    /**
     * Tables whose `updated_at` is consulted to decide whether anything has
     * changed since the last backup. Every table that holds club data and
     * carries timestamps is listed; `tracings` deliberately is not, because it
     * has no `updated_at` and a login would otherwise mark the database dirty
     * forever.
     *
     * @var array<int, string>
     */
    private const TABLES = [
        'clubs',
        'club_member',
        'club_user',
        'debits',
        'events',
        'event_member',
        'items',
        'item_member',
        'members',
        'member_role',
        'member_section',
        'member_subscription',
        'roles',
        'sections',
        'subscriptions',
        'users',
    ];

    /**
     * Dumps smaller than this many bytes are treated as failed, because a
     * broken pipeline still produces a small (or empty) gzip file.
     */
    private const int MIN_FILE_SIZE = 2000;

    /**
     * Dump the database into a gzipped file and replicate it to S3.
     *
     * @return array{success: true, file: string}|array{success: false, skipped: true, message: string}|array{success: false, message: string, output: array<int, string>}
     */
    public static function create(bool $force = false): array
    {
        if (! $force && ! self::isDirty()) {
            $message = 'No changes since the last backup.';
            Log::info($message);

            return [
                'success' => false,
                'skipped' => true,
                'message' => $message,
            ];
        }

        self::deleteOld();

        File::ensureDirectoryExists(self::path());

        $filePath = self::path(self::makeFilename());
        $credentialsFile = self::createCredentialsFile();

        try {
            $command = self::buildDumpCommand($filePath, $credentialsFile);

            exec("({$command}) 2>&1", $output, $exitCode);
        } finally {
            File::delete($credentialsFile);
        }

        if ($exitCode === 0 && File::exists($filePath) && File::size($filePath) > self::MIN_FILE_SIZE) {
            self::copyToS3($filePath);
            Log::info("Backup succeeded: {$filePath}");

            return [
                'success' => true,
                'file' => $filePath,
            ];
        }

        File::delete($filePath);
        Log::error("Backup failed: {$filePath}", ['output' => $output]);

        return [
            'success' => false,
            'message' => "Backup failed: {$filePath}",
            'output' => $output,
        ];
    }

    /**
     * Restore the database from the given backup file.
     */
    public static function restore(string $filename, bool $backupBefore = true): bool
    {
        $filename = basename($filename);
        $path = self::path($filename);

        if (! self::isBackupFilename($filename) || ! File::exists($path)) {
            Log::error("Backup.restore: invalid or missing backup file: {$filename}");

            return false;
        }

        // A corrupt archive would make gunzip fail mid-pipeline while mysql
        // still exits successfully after the partial input, so the integrity
        // has to be verified up front.
        exec(sprintf('gunzip -t %s 2>&1', escapeshellarg($path)), $testOutput, $testExitCode);
        if ($testExitCode !== 0) {
            Log::error("Backup.restore: corrupt backup file: {$path}", ['output' => $testOutput]);

            return false;
        }

        if ($backupBefore && ! self::create(force: true)['success']) {
            Log::error('Backup.restore aborted: the safety backup could not be created.');

            return false;
        }

        $credentialsFile = self::createCredentialsFile();

        try {
            $command = self::buildRestoreCommand($path, $credentialsFile);

            exec("({$command}) 2>&1", $output, $exitCode);
        } finally {
            File::delete($credentialsFile);
        }

        if ($exitCode !== 0) {
            Log::error("Backup.restore failed: {$path}", ['output' => $output]);

            return false;
        }

        Log::info("Backup restored: {$path}");

        return true;
    }

    public static function prefix(): string
    {
        return self::databaseName().'_';
    }

    public static function path(string $filename = ''): string
    {
        $directory = rtrim(config('backup.directory'), DIRECTORY_SEPARATOR);
        $filename = trim($filename, DIRECTORY_SEPARATOR);

        return $filename === '' ? $directory : $directory.DIRECTORY_SEPARATOR.$filename;
    }

    public static function makeFilename(): string
    {
        return self::prefix().now()->format(self::DATE_FORMAT).self::TIMEZONE_SUFFIX.'.sql.gz';
    }

    /**
     * All backups in the backup directory, newest first.
     *
     * @return array<int, array{id: int, timestamp: int, date: string, filename: string, age: int}>
     */
    public static function all(): array
    {
        $now = now();

        return collect(File::glob(self::path('*.sql.gz')))
            ->map(fn (string $path) => basename($path))
            ->filter(fn (string $filename) => self::isBackupFilename($filename))
            ->map(function (string $filename) use ($now) {
                $date = self::dateFromFilename($filename);

                return [
                    'timestamp' => $date->getTimestamp(),
                    'date' => $date->format('Y-m-d H:i:s'),
                    'filename' => $filename,
                    'age' => (int) abs($date->diffInMinutes($now)),
                ];
            })
            ->sortByDesc('timestamp')
            ->values()
            ->map(fn (array $backup, int $index) => ['id' => $index + 1, ...$backup])
            ->all();
    }

    /**
     * The newest backup, or null if none exists.
     *
     * @return array{id: int, timestamp: int, date: string, filename: string, age: int}|null
     */
    public static function latest(): ?array
    {
        return self::all()[0] ?? null;
    }

    public static function latestDate(): ?string
    {
        return self::latest()['date'] ?? null;
    }

    /**
     * Delete backups older than the given number of days, but always keep
     * the newest $retain backups. Returns the number of deleted files.
     */
    public static function deleteOld(?int $days = null, ?int $retain = null): int
    {
        $days ??= (int) config('backup.retain_days');
        $retain ??= (int) config('backup.retain_count');
        $maxAgeMinutes = $days * 24 * 60;

        $backups = self::all();
        $deleted = 0;

        while (count($backups) > $retain && last($backups)['age'] > $maxAgeMinutes) {
            File::delete(self::path(array_pop($backups)['filename']));
            $deleted++;
        }

        return $deleted;
    }

    /**
     * Whether the database has changed since the latest backup.
     */
    public static function isDirty(): bool
    {
        $latestBackupDate = self::latestDate();
        if ($latestBackupDate === null) {
            return true;
        }

        foreach (self::TABLES as $table) {
            $latestUpdate = DB::table($table)->max('updated_at');

            if ($latestUpdate !== null && Carbon::parse($latestUpdate) >= Carbon::parse($latestBackupDate)) {
                Log::info("Backup: '{$table}' has changed since the last backup");

                return true;
            }
        }

        return false;
    }

    /**
     * Build the mysqldump-to-gzip shell pipeline. Operational tables (queues,
     * cache, sessions) hold no application data worth restoring,
     * but their structure is still dumped so a restore recreates them empty
     * instead of leaving them missing entirely.
     */
    private static function buildDumpCommand(string $filePath, string $credentialsFile): string
    {
        $database = self::databaseName();
        $credentials = escapeshellarg($credentialsFile);
        $db = escapeshellarg($database);
        $mysqldump = escapeshellarg(self::mysqldumpBinary());

        $ignoreFlags = collect((array) config('backup.exclude_tables', []))
            ->map(fn (string $table) => escapeshellarg("--ignore-table={$database}.{$table}"))
            ->implode(' ');

        $schemaDump = "{$mysqldump} --defaults-extra-file={$credentials} --no-data {$db}";
        $dataDump = sprintf('%s --defaults-extra-file=%s --no-create-info %s%s',
            $mysqldump,
            $credentials,
            $ignoreFlags === '' ? '' : "{$ignoreFlags} ",
            $db);

        return sprintf('{ %s; %s; } | gzip -c > %s', $schemaDump, $dataDump, escapeshellarg($filePath));
    }

    private static function buildRestoreCommand(string $path, string $credentialsFile): string
    {
        return sprintf('gunzip -c %s | %s --defaults-extra-file=%s %s',
            escapeshellarg($path),
            escapeshellarg(self::mysqlBinary()),
            escapeshellarg($credentialsFile),
            escapeshellarg(self::databaseName()));
    }

    private static function mysqlBinary(): string
    {
        return self::resolveBinary('mysql', 'mysql_binary');
    }

    private static function mysqldumpBinary(): string
    {
        return self::resolveBinary('mysqldump', 'mysqldump_binary');
    }

    /**
     * Resolve a client binary. An explicit config override always wins;
     * otherwise fall back to PATH plus common install locations that PHP-FPM
     * (e.g. under Laravel Herd) often doesn't have on its own PATH, before
     * finally falling back to the bare command name.
     */
    private static function resolveBinary(string $name, string $configKey): string
    {
        $configured = config("backup.{$configKey}");
        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return (new ExecutableFinder)->find($name, $name, self::binarySearchDirs());
    }

    /**
     * @return array<int, string>
     */
    private static function binarySearchDirs(): array
    {
        $home = getenv('HOME');

        return array_filter([
            $home !== false ? $home.'/Library/Application Support/Herd/bin' : null,
            '/opt/homebrew/bin',
            '/usr/local/bin',
            '/usr/local/mysql/bin',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function connection(): array
    {
        return config('database.connections.mariadb');
    }

    private static function databaseName(): string
    {
        return self::connection()['database'];
    }

    private static function isBackupFilename(string $filename): bool
    {
        $pattern = '/^'.preg_quote(self::prefix(), '/').'\d{4}(_\d{2}){5}'
            .preg_quote(self::TIMEZONE_SUFFIX, '/').'\.sql\.gz$/';

        return preg_match($pattern, $filename) === 1;
    }

    private static function dateFromFilename(string $filename): Carbon
    {
        $encodedDate = substr(basename($filename, '.sql.gz'), strlen(self::prefix()));

        return Carbon::createFromFormat(
            self::DATE_FORMAT, Str::chopEnd($encodedDate, self::TIMEZONE_SUFFIX)
        );
    }

    /**
     * Write a temporary MySQL option file so credentials never appear on the
     * command line, where they would be visible in the process list.
     */
    private static function createCredentialsFile(): string
    {
        $connection = self::connection();

        File::ensureDirectoryExists(self::path());

        $path = self::path('.credentials-'.bin2hex(random_bytes(8)).'.cnf');

        File::put($path, sprintf("[client]\nuser=%s\npassword=\"%s\"\nhost=%s\nport=%s\n",
            $connection['username'],
            str_replace('"', '\"', $connection['password'] ?? ''),
            $connection['host'],
            $connection['port']));

        chmod($path, 0o600);

        return $path;
    }

    private static function copyToS3(string $filePath): void
    {
        if (! config('backup.s3.enabled')) {
            return;
        }

        $stream = fopen($filePath, 'r');
        if ($stream === false) {
            Log::error("Backup.copyToS3: could not open file: {$filePath}");

            return;
        }

        Storage::disk('s3')->put(basename($filePath), $stream);
        fclose($stream);
    }
}
