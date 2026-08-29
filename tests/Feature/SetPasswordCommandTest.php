<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

it('sets the password of an existing user', function () {
    $user = User::factory()->create(['email' => 'kassier@example.test']);

    $this->artisan('app:set-password', ['email' => 'kassier@example.test', 'password' => 'neuesGeheimnis1!'])
        ->expectsOutputToContain('Password updated for kassier@example.test')
        ->assertSuccessful();

    expect(Hash::check('neuesGeheimnis1!', $user->refresh()->password))->toBeTrue();
});

it('fails on an unknown email without touching anybody', function () {
    $user = User::factory()->create(['email' => 'kassier@example.test']);
    $before = $user->password;

    $this->artisan('app:set-password', ['email' => 'niemand@example.test', 'password' => 'neuesGeheimnis1!'])
        ->expectsOutputToContain('No user found with email niemand@example.test')
        ->assertFailed();

    expect($user->refresh()->password)->toBe($before);
});

it('is registered under its signature', function () {
    expect(array_keys(Artisan::all()))->toContain('app:set-password');
});
