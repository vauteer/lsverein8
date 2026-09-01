<?php

namespace App\Console\Commands;

use App\ErrorLog;
use App\Mail\ErrorDigest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * @phpstan-import-type Entry from ErrorLog
 */
#[Signature('app:mail-errors')]
#[Description('Mail the errors of the last 24 hours to the admins')]
class MailErrorsCommand extends Command
{
    /**
     * A digest is meant to be read. Past this many the mail stops listing and
     * says how many were left out, so one repeating fault cannot turn the
     * daily mail into thousands of lines.
     */
    private const int MAX_LINES = 50;

    /**
     * The window matches the daily schedule, so consecutive runs neither
     * overlap nor leave a gap.
     */
    public function handle(): int
    {
        $since = Carbon::now()->subDay();

        /** @var list<Entry> $entries */
        $entries = [];
        /** @var array<string, int> $levels */
        $levels = [];
        $count = 0;

        foreach (ErrorLog::entries($since) as $entry) {
            $count++;
            $levels[$entry['level']] = ($levels[$entry['level']] ?? 0) + 1;

            if ($count <= self::MAX_LINES) {
                $entries[] = $entry;
            }
        }

        if ($count === 0) {
            $this->info('No errors in the last 24 hours.');

            return self::SUCCESS;
        }

        $admins = User::where('admin', true)->get();

        if ($admins->isEmpty()) {
            $this->error('No admin to mail to');

            return self::FAILURE;
        }

        foreach ($admins as $admin) {
            Mail::to($admin)->send(new ErrorDigest($entries, $count, $levels, $since));
        }

        $this->info("{$count} error(s) mailed to {$admins->count()} admin(s).");

        return self::SUCCESS;
    }
}
