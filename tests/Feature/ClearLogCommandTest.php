<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

it('truncates the laravel log', function () {
    // faked so the run does not wipe the real development log
    File::shouldReceive('put')
        ->once()
        ->with(storage_path('logs/laravel.log'), '')
        ->andReturnTrue();

    $this->artisan('app:clear-log')
        ->expectsOutputToContain('Log cleared')
        ->assertSuccessful();
});

it('is registered under its signature', function () {
    expect(array_keys(Artisan::all()))->toContain('app:clear-log');
});
