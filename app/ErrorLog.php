<?php

namespace App;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Generator;
use Illuminate\Support\Str;

/**
 * Reader for the errors in the application log, shared by the command that
 * prints them and the one that mails them.
 *
 * @phpstan-type Entry array{date: CarbonInterface, level: string, message: string, trace: list<string>}
 */
class ErrorLog
{
    /**
     * Everything at ERROR or above. Warnings and below are not what this is
     * for.
     */
    private const array LEVELS = ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

    /**
     * The opening line of a log entry:
     * "[2026-08-27 19:34:10] production.ERROR: message". Every line after it
     * belongs to the entry's stack trace.
     */
    private const string ENTRY = '/^\[(\d{4}-\d{2}-\d{2}[ T][^\]]*)\] [^.\s]+\.([A-Z]+): (.*)$/';

    public static function path(): string
    {
        return storage_path('logs/laravel.log');
    }

    public static function exists(): bool
    {
        return file_exists(self::path());
    }

    /**
     * Every entry at ERROR or above, oldest first, optionally only those on or
     * after the given moment.
     *
     * Read and handed over one entry at a time: a production log grows past the
     * memory limit long before it stops being worth searching.
     *
     * @return Generator<int, Entry>
     */
    public static function entries(?CarbonInterface $since = null): Generator
    {
        if (! self::exists()) {
            return;
        }

        $handle = fopen(self::path(), 'r');
        if ($handle === false) {
            return;
        }

        /** @var Entry|null $current */
        $current = null;

        try {
            while (($line = fgets($handle)) !== false) {
                $line = rtrim($line, "\r\n");

                if (! preg_match(self::ENTRY, $line, $matches)) {
                    if ($current !== null) {
                        $current['trace'][] = $line;
                    }

                    continue;
                }

                // A new entry begins, so the one being collected is complete
                // whether this one is kept or not.
                if ($current !== null) {
                    yield $current;
                    $current = null;
                }

                [, $timestamp, $level, $message] = $matches;

                if (! in_array($level, self::LEVELS, true)) {
                    continue;
                }

                $date = Carbon::parse($timestamp);

                if ($since !== null && $date->lessThan($since)) {
                    continue;
                }

                $current = ['date' => $date, 'level' => $level, 'message' => $message, 'trace' => []];
            }

            if ($current !== null) {
                yield $current;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * The one-line form of an entry.
     *
     * No column padding: the console collapses runs of spaces, so the
     * fixed-width timestamp is the only alignment on offer. Unless the whole
     * message is asked for, the trailing {"exception":"[object] (...)"} blob is
     * cut, because it repeats the message and buries it.
     *
     * @param  Entry  $entry
     */
    public static function line(array $entry, bool $whole = false): string
    {
        return sprintf('%s %s %s',
            $entry['date']->format('Y-m-d H:i:s'),
            $entry['level'],
            $whole ? $entry['message'] : Str::before($entry['message'], ' {"exception":')
        );
    }
}
