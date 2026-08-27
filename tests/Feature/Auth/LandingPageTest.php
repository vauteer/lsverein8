<?php

use App\Enums\ClubRole;
use App\Enums\LandingPage;
use App\Models\Club;
use App\Models\User;

beforeEach(function () {
    $this->club = Club::factory()->create(['id' => 1]);
});

function landingUser(LandingPage $landingPage): User
{
    $user = User::factory()->create(['club_id' => 1, 'landing_page' => $landingPage]);
    $user->clubs()->attach(1, ['role' => ClubRole::Basic->value]);

    return $user;
}

test('signing in lands on the page the user chose', function () {
    $user = landingUser(LandingPage::Members);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('members.index'));

    $this->assertAuthenticatedAs($user);
});

test('the dashboard is where the other choice lands', function () {
    $user = landingUser(LandingPage::Dashboard);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard'));
});

test('a deep link the user was stopped at beats the landing page', function () {
    $user = landingUser(LandingPage::Dashboard);

    // Asked for a protected screen as a guest: the auth middleware remembers
    // it, and that is what somebody following a link actually wanted.
    $this->get(route('sections.index'))->assertRedirect(route('login'));

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('sections.index'));
});
