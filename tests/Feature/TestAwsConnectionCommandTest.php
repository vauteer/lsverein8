<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

it('uploads, reads and cleans up a probe file', function () {
    // Faked: the real disk is the off-site backup target, and the command
    // writes to it.
    $disk = Storage::fake('s3');

    $this->artisan('aws:test')
        ->expectsOutputToContain('Successfully uploaded test file')
        ->expectsOutputToContain('Successfully read the test file')
        ->expectsOutputToContain('Successfully deleted test file')
        ->assertSuccessful();

    // The probe leaves nothing behind on the backup disk.
    expect($disk->files(''))->toBeEmpty();
});

it('reports a failure instead of throwing when the disk is unreachable', function () {
    Storage::shouldReceive('disk')->with('s3')->andThrow(new Exception('Could not resolve host'));

    $this->artisan('aws:test')
        ->expectsOutputToContain('Could not resolve host')
        ->assertFailed();
});

it('is registered under its signature', function () {
    expect(array_keys(Artisan::all()))->toContain('aws:test');
});
