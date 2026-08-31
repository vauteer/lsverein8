<?php

use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
    // Storage::fake('public') for EVERY feature test, not per file.
    //
    // ProfileController and ClubController sweep orphaned files on every save,
    // and the test database is empty, so the sweep treats every real profile
    // image and club logo as an orphan and deletes it. That is not theoretical:
    // on 2026-08-25 a test run deleted both real club logos off the developer's
    // disk, because the fake was set per test file and a new file forgot it.
    //
    // Do not move this back into individual files.
    ->beforeEach(fn () => Storage::fake('public'))
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

/**
 * The visible text of a generated PDF.
 *
 * FPDF deflates its content streams, so the drawn strings are not in the raw
 * output. Inflating every stream gives back the text operators, which is
 * enough to assert that a label or a column heading was printed.
 */
function pdfText(string $contents): string
{
    preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $contents, $matches);

    return array_reduce(
        $matches[1],
        function (string $text, string $stream): string {
            $inflated = @gzuncompress($stream);

            return $inflated === false ? $text : $text.$inflated;
        },
        ''
    );
}

/**
 * The parts of a generated .xlsx that a test needs to look at: the worksheet
 * and the styles. An xlsx is a zip, and the export hands it out as a string.
 *
 * @return array{0: string, 1: string} sheet1.xml, styles.xml
 */
function xlsxParts(string $contents): array
{
    $path = tempnam(sys_get_temp_dir(), 'test-xlsx-');
    file_put_contents($path, $contents);

    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();

    $parts = [
        (string) $zip->getFromName('xl/worksheets/sheet1.xml'),
        (string) $zip->getFromName('xl/styles.xml'),
    ];

    $zip->close();
    @unlink($path);

    return $parts;
}

/**
 * A worksheet as a plain grid of A..G strings, missing cells read as ''.
 *
 * Inline strings only — the export writes no sharedStrings table.
 *
 * @return list<list<string>>
 */
function xlsxRows(string $sheet): array
{
    $xml = new SimpleXMLElement($sheet);
    $rows = [];

    foreach ($xml->sheetData->row as $row) {
        $cells = array_fill_keys(range('A', 'G'), '');

        foreach ($row->c as $cell) {
            $reference = rtrim((string) $cell['r'], '0123456789');

            // Inline string (<is><t>) or a raw value (<v>) for numbers and
            // date serials.
            $cells[$reference] = isset($cell->is)
                ? (string) $cell->is->t
                : (string) $cell->v;
        }

        $rows[] = array_values($cells);
    }

    return $rows;
}

/**
 * Replace the application log with the given content.
 */
function writeLog(string $content): void
{
    file_put_contents(storage_path('logs/laravel.log'), $content);
}

/**
 * One opening line of a log entry, as Monolog writes it.
 */
function logEntry(CarbonInterface $at, string $level, string $message): string
{
    return "[{$at->format('Y-m-d H:i:s')}] production.{$level}: {$message}\n";
}
