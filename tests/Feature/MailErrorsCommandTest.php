<?php

use App\Models\User;

beforeEach(function () {
    writeLog('');
});

/**
 * The addresses the array transport was handed, one per sent message.
 *
 * @return list<string>
 */
function mailedTo(): array
{
    return collect(app('mailer')->getSymfonyTransport()->messages())
        ->map(fn ($message) => $message->getOriginalMessage()->getTo()[0]->getAddress())
        ->all();
}

function mailedBody(): string
{
    $messages = collect(app('mailer')->getSymfonyTransport()->messages());

    return $messages->isEmpty() ? '' : $messages->first()->toString();
}

test('nothing is sent when there are no errors', function () {
    User::factory()->create(['admin' => true]);

    $this->artisan('app:mail-errors')
        ->expectsOutputToContain('No errors in the last 24 hours.')
        ->assertExitCode(0);

    expect(mailedTo())->toBe([]);
});

test('the errors are mailed to every admin and to nobody else', function () {
    $first = User::factory()->create(['admin' => true]);
    $second = User::factory()->create(['admin' => true]);
    $other = User::factory()->create(['admin' => false]);

    writeLog(logEntry(now()->subHours(2), 'ERROR', 'the recent one'));

    $this->artisan('app:mail-errors')
        ->expectsOutputToContain('1 error(s) mailed to 2 admin(s).')
        ->assertExitCode(0);

    expect(mailedTo())->toEqualCanonicalizing([$first->email, $second->email]);
    expect(mailedTo())->not->toContain($other->email);
    expect(mailedBody())->toContain('the recent one');
});

test('errors older than a day are left out', function () {
    User::factory()->create(['admin' => true]);

    writeLog(logEntry(now()->subDays(3), 'ERROR', 'the old one'));

    $this->artisan('app:mail-errors')
        ->expectsOutputToContain('No errors in the last 24 hours.')
        ->assertExitCode(0);

    expect(mailedTo())->toBe([]);
});

test('warnings and below never reach the mail', function () {
    User::factory()->create(['admin' => true]);

    writeLog(logEntry(now()->subHour(), 'WARNING', 'only a warning'));

    $this->artisan('app:mail-errors')
        ->expectsOutputToContain('No errors in the last 24 hours.')
        ->assertExitCode(0);

    expect(mailedTo())->toBe([]);
});

test('a long list is capped and says how many were left out', function () {
    User::factory()->create(['admin' => true]);

    $log = '';
    foreach (range(1, 60) as $i) {
        $log .= logEntry(now()->subHours(2), 'ERROR', "error number {$i}");
    }
    writeLog($log);

    $this->artisan('app:mail-errors')
        ->expectsOutputToContain('60 error(s) mailed to 1 admin(s).')
        ->assertExitCode(0);

    $body = mailedBody();
    expect($body)->toContain('error number 50');
    expect($body)->not->toContain('error number 51');
    expect($body)->toContain('... and 10 more.');
});

test('the exception blob is left off the mailed lines', function () {
    User::factory()->create(['admin' => true]);

    writeLog(logEntry(now()->subHour(), 'ERROR',
        'Boom {"exception":"[object] (RuntimeException(code: 0): Boom at /app/x.php:12)"}'));

    $this->artisan('app:mail-errors')->assertExitCode(0);

    expect(mailedBody())->toContain('ERROR Boom');
    expect(mailedBody())->not->toContain('[object]');
});

test('it reports when there is no admin to mail to', function () {
    User::factory()->create(['admin' => false]);

    writeLog(logEntry(now()->subHour(), 'ERROR', 'the recent one'));

    $this->artisan('app:mail-errors')
        ->expectsOutputToContain('No admin to mail to')
        ->assertExitCode(1);

    expect(mailedTo())->toBe([]);
});
