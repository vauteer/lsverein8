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

/**
 * The first mail as one searchable string, HTML and text part alike.
 *
 * The parts travel quoted-printable, which wraps long lines, so the encoding
 * is undone before anything is looked for.
 */
function mailedBody(): string
{
    $messages = collect(app('mailer')->getSymfonyTransport()->messages());

    if ($messages->isEmpty()) {
        return '';
    }

    return quoted_printable_decode(str_replace("=\r\n", '', $messages->first()->toString()));
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
    expect($body)->toContain('and 10 more');
});

test('the exception blob is left off the mailed lines', function () {
    User::factory()->create(['admin' => true]);

    writeLog(logEntry(now()->subHour(), 'ERROR',
        'Boom {"exception":"[object] (RuntimeException(code: 0): Boom at /app/x.php:12)"}'));

    $this->artisan('app:mail-errors')->assertExitCode(0);

    expect(mailedBody())->toContain('Boom');
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

test('the mail is a formatted digest, not a plain log dump', function () {
    User::factory()->create(['admin' => true]);

    writeLog(logEntry(now()->subHours(2), 'ERROR', 'a *starred* | piped message'));

    $this->artisan('app:mail-errors')->assertExitCode(0);

    $body = mailedBody();
    expect($body)->toContain('Content-Type: text/html');
    expect($body)->toContain('Content-Type: text/plain');
    expect($body)->toContain('1 error(s) in the last 24 hours');
    expect($body)->toContain('Message');
    expect($body)->toContain('Open the dashboard');
    // The message travels through the markdown parser untouched.
    expect($body)->toContain('a *starred* | piped message');
});

test('the levels are summarized when more than one was logged', function () {
    User::factory()->create(['admin' => true]);

    writeLog(logEntry(now()->subHours(2), 'ERROR', 'the ordinary one')
        .logEntry(now()->subHour(), 'CRITICAL', 'the grave one'));

    $this->artisan('app:mail-errors')
        ->expectsOutputToContain('2 error(s) mailed to 1 admin(s).')
        ->assertExitCode(0);

    $body = mailedBody();
    expect($body)->toContain('2 error(s) in the last 24 hours');
    expect($body)->toContain('CRITICAL');
    expect($body)->toContain('ERROR');
});
