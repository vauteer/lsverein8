<?php

use Illuminate\Support\Carbon;

beforeEach(function () {
    writeLog('');
});

test('errors are listed with their timestamp and level', function () {
    writeLog(logEntry(Carbon::parse('2026-08-27 19:34:10'), 'ERROR', 'SQLSTATE[23000]: Duplicate entry'));

    $this->artisan('app:show-errors')
        ->expectsOutput('2026-08-27 19:34:10 ERROR SQLSTATE[23000]: Duplicate entry')
        ->expectsOutputToContain('1 error(s) in the whole log.')
        ->assertExitCode(0);
});

test('everything below error is left out', function () {
    writeLog(
        logEntry(now(), 'INFO', 'a chatty line')
        .logEntry(now(), 'WARNING', 'a warning line')
        .logEntry(now(), 'DEBUG', 'a debug line')
    );

    $this->artisan('app:show-errors')
        ->doesntExpectOutputToContain('a chatty line')
        ->doesntExpectOutputToContain('a warning line')
        ->expectsOutputToContain('No errors in the whole log.')
        ->assertExitCode(0);
});

test('critical, alert and emergency count as errors too', function () {
    writeLog(
        logEntry(now(), 'CRITICAL', 'critical one')
        .logEntry(now(), 'ALERT', 'alert one')
        .logEntry(now(), 'EMERGENCY', 'emergency one')
    );

    $this->artisan('app:show-errors')
        ->expectsOutputToContain('critical one')
        ->expectsOutputToContain('alert one')
        ->expectsOutputToContain('emergency one')
        ->expectsOutputToContain('3 error(s)')
        ->assertExitCode(0);
});

test('the stack trace of an entry is not listed as its own line', function () {
    writeLog(
        logEntry(now(), 'ERROR', 'Allowed memory size exhausted')
        ."[stacktrace]\n"
        ."#0 /var/www/vendor/laravel/framework/src/Illuminate/Collections/Arr.php(1268)\n"
        ."#1 {main}\n"
    );

    $this->artisan('app:show-errors')
        ->expectsOutputToContain('Allowed memory size exhausted')
        ->doesntExpectOutputToContain('#0 /var/www')
        ->expectsOutputToContain('1 error(s)')
        ->assertExitCode(0);
});

test('the exception blob is trimmed off the message', function () {
    writeLog(logEntry(
        Carbon::parse('2026-08-27 19:34:10'),
        'ERROR',
        'Boom {"exception":"[object] (RuntimeException(code: 0): Boom at /app/x.php:12)"}'
    ));

    // Matched whole, not by substring: a substring would still be found if the
    // blob were left on the end.
    $this->artisan('app:show-errors')
        ->expectsOutput('2026-08-27 19:34:10 ERROR Boom')
        ->assertExitCode(0);
});

test('the days argument limits how far back it looks', function () {
    writeLog(
        logEntry(now()->subDays(5), 'ERROR', 'the old one')
        .logEntry(now()->subHours(2), 'ERROR', 'the recent one')
    );

    $this->artisan('app:show-errors', ['days' => 2])
        ->expectsOutputToContain('the recent one')
        ->doesntExpectOutputToContain('the old one')
        ->expectsOutputToContain('1 error(s) in the last 2 day(s).')
        ->assertExitCode(0);
});

test('without the argument the whole log is searched', function () {
    writeLog(logEntry(now()->subYear(), 'ERROR', 'the ancient one'));

    $this->artisan('app:show-errors')
        ->expectsOutputToContain('the ancient one')
        ->assertExitCode(0);
});

test('an empty log reports that there is nothing', function () {
    $this->artisan('app:show-errors')
        ->expectsOutputToContain('No errors in the whole log.')
        ->assertExitCode(0);
});

test('a missing log file is reported instead of failing', function () {
    unlink(storage_path('logs/laravel.log'));

    $this->artisan('app:show-errors')
        ->expectsOutputToContain('No log file at')
        ->assertExitCode(0);
});

test('the stack option prints the trace lines of an error', function () {
    writeLog(
        logEntry(Carbon::parse('2026-08-27 23:51:44'), 'ERROR', 'Allowed memory size exhausted')
        ."[stacktrace]\n"
        ."#0 /var/www/vendor/laravel/framework/src/Illuminate/Collections/Arr.php(1268)\n"
        ."#1 {main}\n"
    );

    $this->artisan('app:show-errors', ['--stack' => true])
        ->expectsOutputToContain('Allowed memory size exhausted')
        ->expectsOutput('[stacktrace]')
        ->expectsOutput('#0 /var/www/vendor/laravel/framework/src/Illuminate/Collections/Arr.php(1268)')
        ->expectsOutput('#1 {main}')
        ->assertExitCode(0);
});

test('the stack option keeps the exception blob on the message', function () {
    writeLog(logEntry(
        Carbon::parse('2026-08-27 19:34:10'),
        'ERROR',
        'Boom {"exception":"[object] (RuntimeException(code: 0): Boom at /app/x.php:12)"}'
    ));

    $this->artisan('app:show-errors', ['--stack' => true])
        ->expectsOutput('2026-08-27 19:34:10 ERROR Boom {"exception":"[object] (RuntimeException(code: 0): Boom at /app/x.php:12)"}')
        ->assertExitCode(0);
});

test('the stack option does not print the trace of an entry that was filtered out', function () {
    writeLog(
        logEntry(Carbon::parse('2026-08-27 19:30:00'), 'INFO', 'a chatty line')
        ."#0 an info trace line\n"
        .logEntry(Carbon::parse('2026-08-27 19:34:10'), 'ERROR', 'the real one')
        ."#0 an error trace line\n"
        // Follows a shown error, so it only stays hidden if the new entry ends
        // the previous one's trace.
        .logEntry(Carbon::parse('2026-08-27 20:00:00'), 'INFO', 'another chatty line')
        ."#0 a later info trace line\n"
    );

    $this->artisan('app:show-errors', ['--stack' => true])
        ->expectsOutput('#0 an error trace line')
        ->doesntExpectOutput('#0 an info trace line')
        ->doesntExpectOutput('#0 a later info trace line')
        ->expectsOutputToContain('1 error(s)')
        ->assertExitCode(0);
});

test('the stack option does not print the trace of an entry outside the days window', function () {
    writeLog(
        logEntry(now()->subDays(5), 'ERROR', 'the old one')
        ."#0 an old trace line\n"
        .logEntry(now()->subHours(2), 'ERROR', 'the recent one')
        ."#0 a recent trace line\n"
    );

    $this->artisan('app:show-errors', ['days' => 2, '--stack' => true])
        ->expectsOutput('#0 a recent trace line')
        ->doesntExpectOutput('#0 an old trace line')
        ->expectsOutputToContain('1 error(s) in the last 2 day(s).')
        ->assertExitCode(0);
});

test('a days argument that is not a number is refused', function () {
    writeLog(logEntry(now()->subYear(), 'ERROR', 'the ancient one'));

    // What "app:show-errors days=1" passes: it would otherwise cast to 0 and
    // read the whole log, which reads as the filter being ignored.
    $this->artisan('app:show-errors', ['days' => 'days=1'])
        ->expectsOutputToContain('The days argument must be a whole number, not "days=1".')
        ->expectsOutputToContain('php artisan app:show-errors 1')
        ->doesntExpectOutputToContain('the ancient one')
        ->assertExitCode(1);
});

test('a negative days argument is refused', function () {
    $this->artisan('app:show-errors', ['days' => '-3'])
        ->expectsOutputToContain('must be a whole number')
        ->assertExitCode(1);
});
