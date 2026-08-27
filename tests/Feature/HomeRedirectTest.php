<?php

use App\Enums\ClubRole;
use App\Enums\LandingPage;
use App\Models\Club;
use App\Models\User;

/**
 * The app has no public page — the starter kit's Welcome screen is gone and
 * `/` only points the way, to whichever screen the user chose in their
 * settings.
 */
beforeEach(function () {
    $this->club = Club::factory()->create(['id' => 1]);
});

function homeUser(LandingPage $landingPage = LandingPage::Dashboard): User
{
    $user = User::factory()->create(['club_id' => 1, 'landing_page' => $landingPage]);
    $user->clubs()->attach(1, ['role' => ClubRole::Basic->value]);

    return $user;
}

test('a guest asking for the root is sent to the login', function () {
    $this->get(route('home'))->assertRedirect(route('login'));

    // Deliberately with no intended URL behind it: an intended URL beats the
    // landing page, so bouncing them off a protected screen instead would
    // silently override the preference of everybody who starts at the root.
    expect(session('url.intended'))->toBeNull();
});

test('a signed-in user asking for the root lands on their chosen page', function () {
    $this->actingAs(homeUser(LandingPage::Dashboard))
        ->get(route('home'))
        ->assertRedirect(route('dashboard'));

    $this->actingAs(homeUser(LandingPage::Members))
        ->get(route('home'))
        ->assertRedirect(route('members.index'));
});

test('a user without the column read back still gets the dashboard', function () {
    // users.landing_page is NOT NULL, but a model created without it never
    // reads the default back — the same trap as users.admin.
    $user = User::factory()->make(['club_id' => 1]);
    $user->landing_page = null;

    expect($user->landingPage())->toBe(LandingPage::Dashboard);
});
