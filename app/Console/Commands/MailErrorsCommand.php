<?php

namespace App\Console\Commands;

use App\ErrorLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;

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
        $lines = [];
        $count = 0;

        foreach (ErrorLog::entries(Carbon::now()->subDay()) as $entry) {
            $count++;

            if ($count <= self::MAX_LINES) {
                $lines[] = ErrorLog::line($entry);
            }
        }

        if ($count === 0) {
            $this->info('No errors in the last 24 hours.');

            return self::SUCCESS;
        }

        if ($count > self::MAX_LINES) {
            $lines[] = '... and '.($count - self::MAX_LINES).' more.';
        }

        $admins = User::where('admin', true)->get();

        if ($admins->isEmpty()) {
            $this->error('No admin to mail to');

            return self::FAILURE;
        }

        $subject = config('app.name').": {$count} error(s) in the last 24 hours";
        $body = implode(PHP_EOL, $lines);

        // Plain text on purpose: log lines are not prose, and a markdown mail
        // would mangle the paths and quoting in them.
        foreach ($admins as $admin) {
            Mail::raw($body, fn (Message $message) => $message->to($admin->email)->subject($subject));
        }

        $this->info("{$count} error(s) mailed to {$admins->count()} admin(s).");

        return self::SUCCESS;
    }
}
