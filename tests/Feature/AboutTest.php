<?php

use App\Enums\ClubRole;
use App\Models\Club;
use App\Models\User;

beforeEach(function () {
    Club::factory()->create(['id' => 1]);
});

test('guests are sent to the login', function () {
    $this->get(route('about'))->assertRedirect(route('login'));
});

/**
 * A read-only account too: the credits and the contact address say nothing
 * about a club, and the address is what a user needs when something is wrong.
 */
test('every signed in user can view the about page', function (ClubRole $role) {
    $user = User::factory()->create(['club_id' => 1]);
    $user->clubs()->attach(1, ['role' => $role->value]);

    $response = $this->actingAs($user)->get(route('about'));

    $response->assertOk();

    $response->assertInertia(fn ($page) => $page
        ->component('About')
        ->where('appName', config('app.name'))
        ->where('phpVersion', PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION)
        ->where('laravelVersion', explode('.', app()->version())[0]));
})->with(ClubRole::cases());
