<?php

use App\Enums\ClubRole;
use App\Enums\LandingPage;
use App\Models\Club;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

beforeEach(fn () => Club::factory()->create(['id' => 1, 'name' => 'SSV Brand']));

it('creates a user in club 1 and prints the generated password', function () {
    $this->artisan('app:user', ['name' => 'Anna Meier', 'email' => 'anna@example.test'])
        ->expectsOutputToContain('Updated user Anna Meier in SSV Brand as Admin')
        ->assertSuccessful();

    $user = User::where('email', 'anna@example.test')->sole();

    // A bare account would be useless here: every scope hangs off club_id and
    // the permissions off the pivot role.
    expect($user->club_id)->toBe(1)
        ->and($user->landing_page)->toBe(LandingPage::Dashboard)
        ->and($user->clubs()->sole()->pivot->role)->toBe(ClubRole::Admin->value);
});

it('takes an explicit password and role', function () {
    $this->artisan('app:user', [
        'name' => 'Bert Klein',
        'email' => 'bert@example.test',
        '--password' => 'geheim123!',
        '--role' => 'basic',
    ])->assertSuccessful();

    $user = User::where('email', 'bert@example.test')->sole();

    expect(Hash::check('geheim123!', $user->password))->toBeTrue()
        ->and($user->clubs()->sole()->pivot->role)->toBe(ClubRole::Basic->value);
});

it('edits an existing user without dropping its other clubs', function () {
    $other = Club::factory()->create(['id' => 2]);
    $user = User::factory()->create(['email' => 'anna@example.test', 'name' => 'Alt']);
    $user->clubs()->attach($other->id, ['role' => ClubRole::Basic->value]);
    $before = $user->password;

    $this->artisan('app:user', ['name' => 'Anna Neu', 'email' => 'anna@example.test'])
        ->assertSuccessful();

    // No --password given and one already set, so it is left alone.
    expect($user->refresh()->name)->toBe('Anna Neu')
        ->and($user->password)->toBe($before)
        ->and($user->clubs()->pluck('clubs.id')->sort()->values()->all())->toBe([1, 2]);
});

it('refuses an unknown club or role', function () {
    $this->artisan('app:user', ['name' => 'X', 'email' => 'x@example.test', '--club' => 99])
        ->expectsOutputToContain('No club found with id 99')
        ->assertFailed();

    $this->artisan('app:user', ['name' => 'X', 'email' => 'x@example.test', '--role' => 'chef'])
        ->expectsOutputToContain('Unknown role chef')
        ->assertFailed();

    expect(User::where('email', 'x@example.test')->exists())->toBeFalse();
});

it('is registered under its signature', function () {
    expect(array_keys(Artisan::all()))->toContain('app:user');
});
