<?php

namespace App\Console\Commands;

use App\ErrorLog;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:show-errors {days=0 : How many days back to look, 0 for the whole log} {--stack : Show the full stack trace of every error}')]
#[Description('Show the errors in storage/logs/laravel.log')]
class ShowErrorsCommand extends Command
{
    public function handle(): int
    {
        if (! ErrorLog::exists()) {
            $this->info('No log file at '.ErrorLog::path());

            return self::SUCCESS;
        }

        $given = (string) $this->argument('days');

        // Anything unparseable would silently cast to 0 and read the whole
        // log, which reads as the filter being ignored.
        if (! ctype_digit($given)) {
            $this->error("The days argument must be a whole number, not \"{$given}\".");
            $this->line('It is positional: php artisan app:show-errors 1');

            return self::FAILURE;
        }

        $days = (int) $given;
        $since = $days > 0 ? Carbon::now()->subDays($days) : null;
        $stack = (bool) $this->option('stack');

        $count = 0;

        foreach (ErrorLog::entries($since) as $entry) {
            if ($stack && $count > 0) {
                $this->newLine();
            }

            $this->line(ErrorLog::line($entry, whole: $stack));

            if ($stack) {
                foreach ($entry['trace'] as $line) {
                    $this->line($line);
                }
            }

            $count++;
        }

        $scope = $since === null ? 'the whole log' : "the last {$days} day(s)";

        $this->newLine();
        $this->info($count === 0
            ? "No errors in {$scope}."
            : "{$count} error(s) in {$scope}.");

        return self::SUCCESS;
    }
}
