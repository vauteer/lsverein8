<?php

use App\Enums\ClubRole;
use App\Models\Club;
use App\Models\User;

/**
 * Note throughout: currentClubId() is hardcoded to 1 on the CLI, so switching
 * cannot be observed through the scoped models in a test. What the switch
 * actually does is write users.club_id, and that is what is asserted.
 */
beforeEach(function () {
    $this->club = Club::factory()->create(['id' => 1, 'name' => 'TSV Musterstadt']);
    $this->other = Club::factory()->create(['name' => 'FF Musterdorf']);
});

function switchUser(array $clubIds, ?int $currentClubId = null, array $attributes = []): User
{
    $user = User::factory()->create([...$attributes, 'club_id' => $currentClubId ?? $clubIds[0]]);

    foreach ($clubIds as $clubId) {
        $user->clubs()->attach($clubId, ['role' => ClubRole::Admin->value]);
    }

    return $user;
}

test('guests cannot switch', function () {
    $this->post(route('clubs.switch', $this->other))->assertRedirect(route('login'));
});

test('a user switches to another club they belong to', function () {
    $user = switchUser([1, $this->other->id]);

    $this->actingAs($user)
        ->post(route('clubs.switch', $this->other))
        ->assertRedirect(route('dashboard'));

    expect($user->refresh()->club_id)->toBe($this->other->id);
});

test('a user cannot switch to a club they do not belong to', function () {
    $user = switchUser([1]);

    $this->actingAs($user)
        ->post(route('clubs.switch', $this->other))
        ->assertForbidden();

    expect($user->refresh()->club_id)->toBe(1);
});

test('switching to the club already current is refused', function () {
    $user = switchUser([1, $this->other->id], currentClubId: 1);

    $this->actingAs($user)
        ->post(route('clubs.switch', $this->club))
        ->assertForbidden();
});

test('a root account may switch into a club it does not belong to', function () {
    // That is how root inspects another club at all: every scoped model keys
    // off users.club_id, so there is no other way in.
    $root = switchUser([1], attributes: ['admin' => true]);

    $this->actingAs($root)
        ->post(route('clubs.switch', $this->other))
        ->assertRedirect(route('dashboard'));

    expect($root->refresh()->club_id)->toBe($this->other->id);
});

test('the picker is empty for a user with a single club', function () {
    $this->actingAs(switchUser([1]))
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('switchableClubs', []));
});

test('the picker lists the users own clubs, marking the current one', function () {
    $user = switchUser([1, $this->other->id], currentClubId: 1);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->has('switchableClubs', 2)
            // Ordered by name: FF Musterdorf before TSV Musterstadt.
            ->where('switchableClubs.0.name', 'FF Musterdorf')
            ->where('switchableClubs.0.current', false)
            ->where('switchableClubs.1.name', 'TSV Musterstadt')
            ->where('switchableClubs.1.current', true)
        );
});

test('the picker does not offer clubs a root account merely administers', function () {
    // Root can switch anywhere from the club list, but the sidebar picker
    // stays its own memberships - otherwise it would list the installation.
    $root = switchUser([1], attributes: ['admin' => true]);

    $this->actingAs($root)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('switchableClubs', []));
});

test('the sidebar club flags follow the policy', function () {
    $this->actingAs(switchUser([1]))
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('auth.canManageClubs', false)
            ->where('auth.canEditCurrentClub', true)
            ->where('currentClub.id', 1)
        );

    $this->actingAs(switchUser([1], attributes: ['admin' => true]))
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.canManageClubs', true));
});

test('a read-only member gets no club link at all', function () {
    $user = User::factory()->create(['club_id' => 1]);
    $user->clubs()->attach(1, ['role' => ClubRole::Basic->value]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('auth.canManageClubs', false)
            ->where('auth.canEditCurrentClub', false)
        );
});
