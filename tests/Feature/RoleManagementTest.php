<?php

use App\Enums\ClubRole;
use App\Models\Club;
use App\Models\Member;
use App\Models\Role;
use App\Models\User;

/**
 * currentClubId() resolves to 1 on the CLI, so every request is read as though
 * the acting user were working in club 1.
 */
beforeEach(function () {
    $this->club = Club::factory()->create(['id' => 1]);
});

/**
 * Create a user belonging to the given club (defaulting to club 1) with the
 * given role in it.
 */
function roleUser(ClubRole $clubRole = ClubRole::Admin, ?Club $club = null, array $attributes = []): User
{
    $club ??= Club::find(1) ?? Club::factory()->create(['id' => 1]);

    $user = User::factory()->create([...$attributes, 'club_id' => $club->id]);
    $user->clubs()->attach($club->id, ['role' => $clubRole->value]);

    return $user;
}

/**
 * Drop the installation-wide roles the 2022_08_20 migration seeds ('1.
 * Vorstand' … 'Kassenprüfer'), so a listing contains only the fixtures.
 */
function withoutDefaultRoles(): void
{
    Role::query()->whereNull('club_id')->delete();
}

test('guests are redirected to the login page', function () {
    $this->get(route('roles.index'))->assertRedirect(route('login'));
});

test('the seeded installation-wide roles are listed for the club', function () {
    $this->actingAs(roleUser(ClubRole::Basic))
        ->get(route('roles.index', ['search' => 'Kassier']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('roles/Index')
            ->has('roles.data', 1)
            ->where('roles.data.0.name', 'Kassier')
            ->where('roles.data.0.shared', true)
            // Only a root account may touch an installation-wide role.
            ->where('roles.data.0.modifiable', false)
        );
});

test('the index lists the club roles and the shared ones, but no other club', function () {
    withoutDefaultRoles();

    $own = Role::factory()->create(['club_id' => 1, 'name' => 'Jugendleiter']);
    $shared = Role::factory()->create(['club_id' => null, 'name' => 'Beisitzer']);
    $foreign = Role::factory()->create(['name' => 'Fremdes Amt']);

    $this->actingAs(roleUser(ClubRole::Basic))
        ->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('roles/Index')
            ->has('roles.data', 2)
            ->where('roles.data.0.id', $shared->id)
            ->where('roles.data.0.shared', true)
            ->where('roles.data.1.id', $own->id)
            ->where('roles.data.1.shared', false)
            ->whereNot('roles.data.0.id', $foreign->id)
            ->whereNot('roles.data.1.id', $foreign->id)
        );
});

test('the index counts only the current club members and can be searched', function () {
    $role = Role::factory()->create(['club_id' => 1, 'name' => 'Jugendleiter']);
    Role::factory()->create(['club_id' => 1, 'name' => 'Platzwart']);

    $member = Member::factory()->ofClub(1)->create();
    $member->memberships()->attach(1, ['from' => '2016-01-01', 'to' => null]);

    // Another club's member holds it too, but must not be counted here.
    $foreignMember = Member::factory()->create();

    $role->members()->attach([$member->id, $foreignMember->id], ['from' => now()->subYear()]);

    $this->actingAs(roleUser())
        ->get(route('roles.index', ['search' => 'Jugend']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('roles.data', 1)
            ->where('roles.data.0.id', $role->id)
            ->where('roles.data.0.members_count', 1)
            ->where('filters.search', 'Jugend')
        );
});

test('the two counts are who holds the role now and who ever did', function () {
    // Not one of the seven roles insert_roles_defaults seeds into every
    // installation — those are listed too, and would sort ahead of it.
    $role = Role::factory()->create(['club_id' => 1, 'name' => 'Platzwart']);

    // Holds it and is in the club: both columns.
    $current = Member::factory()->ofClub(1)->create(['surname' => 'Aktiv']);
    $current->memberships()->attach(1, ['from' => '2016-01-01', 'to' => null]);
    $current->roles()->attach($role->id, ['from' => '2016-01-01', 'to' => null]);

    // Handed the role on, still in the club: "ever" only.
    $former = Member::factory()->ofClub(1)->create(['surname' => 'Vorgaenger']);
    $former->memberships()->attach(1, ['from' => '2010-01-01', 'to' => null]);
    $former->roles()->attach($role->id, ['from' => '2010-01-01', 'to' => '2015-12-31']);

    // Left the club altogether: "ever" only, because that selection is not
    // narrowed to current members.
    $gone = Member::factory()->ofClub(1)->create(['surname' => 'Ausgetreten']);
    $gone->memberships()->attach(1, ['from' => '2005-01-01', 'to' => '2009-12-31']);
    $gone->roles()->attach($role->id, ['from' => '2005-01-01', 'to' => '2009-12-31']);

    $this->actingAs(roleUser());

    $this->get(route('roles.index', ['search' => 'Platzwart']))
        ->assertInertia(fn ($page) => $page
            ->has('roles.data', 1)
            ->where('roles.data.0.members_count', 1)
            ->where('roles.data.0.ever_members_count', 3)
        );

    // Each number equals the selection it links to.
    $this->get(route('members.index', ['filter' => "role_{$role->id}"]))
        ->assertInertia(fn ($page) => $page
            ->has('members.data', 1)
            ->where('members.data.0.surname', 'Aktiv')
        );

    $this->get(route('members.index', ['filter' => "ever_role_{$role->id}"]))
        ->assertInertia(fn ($page) => $page->has('members.data', 3));
});

test('a member who held the same role twice counts once', function () {
    $role = Role::factory()->create(['club_id' => 1, 'name' => 'Platzwart']);

    $member = Member::factory()->ofClub(1)->create();
    $member->memberships()->attach(1, ['from' => '2000-01-01', 'to' => null]);
    // Production holds four such pairs in member_role, one with both ranges
    // open at once — count(*) reported that member as two people.
    $member->roles()->attach($role->id, ['from' => '2000-01-01', 'to' => '2004-12-31']);
    $member->roles()->attach($role->id, ['from' => '2010-01-01', 'to' => null]);

    $this->actingAs(roleUser())
        ->get(route('roles.index', ['search' => 'Platzwart']))
        ->assertInertia(fn ($page) => $page
            ->where('roles.data.0.members_count', 1)
            ->where('roles.data.0.ever_members_count', 1)
        );
});

test('a member without admin rights may view but not change roles', function () {
    $role = Role::factory()->create(['club_id' => 1]);

    $this->actingAs(roleUser(ClubRole::Advanced));

    $this->get(route('roles.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->where('canCreate', false));
    $this->get(route('roles.create'))->assertForbidden();
    $this->get(route('roles.edit', $role))->assertForbidden();
    $this->delete(route('roles.destroy', $role))->assertForbidden();
});

test('an admin creates a role for the current club', function () {
    $this->actingAs(roleUser())
        ->post(route('roles.store'), ['name' => 'Jugendleiter'])
        ->assertRedirect();

    $role = Role::query()->where('club_id', 1)->firstOrFail();

    expect($role->name)->toBe('Jugendleiter');
});

test('a role name must be unique among the club and shared roles', function () {
    Role::factory()->create(['club_id' => 1, 'name' => 'Jugendleiter']);
    Role::factory()->create(['name' => 'Fremdes Amt']);

    $this->actingAs(roleUser());

    $this->post(route('roles.store'), ['name' => 'Jugendleiter'])->assertSessionHasErrors('name');
    // 'Kassier' is one of the seeded installation-wide roles.
    $this->post(route('roles.store'), ['name' => 'Kassier'])->assertSessionHasErrors('name');

    // Another club's name is free: it is never listed alongside these.
    $this->post(route('roles.store'), ['name' => 'Fremdes Amt'])->assertSessionHasNoErrors();
});

test('an admin renames a role', function () {
    $role = Role::factory()->create(['club_id' => 1, 'name' => 'Jugendleiter']);

    $this->actingAs(roleUser())
        ->get(route('roles.edit', $role))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('roles/Edit')
            ->where('role.name', 'Jugendleiter')
            ->where('deletable', true)
        );

    $this->put(route('roles.update', $role), ['name' => 'Jugendleiterin'])
        ->assertRedirect();

    expect($role->refresh()->name)->toBe('Jugendleiterin');
});

test('an unused role is deleted and one a member has held is kept', function () {
    $unused = Role::factory()->create(['club_id' => 1]);
    $used = Role::factory()->create(['club_id' => 1]);

    $used->members()->attach(Member::factory()->ofClub(1)->create()->id, ['from' => now()->subYear()]);

    $this->actingAs(roleUser());

    $this->delete(route('roles.destroy', $unused))->assertRedirect();
    expect(Role::find($unused->id))->toBeNull();

    $this->delete(route('roles.destroy', $used))->assertForbidden();
    expect(Role::find($used->id))->not->toBeNull();

    $this->get(route('roles.edit', $used))
        ->assertInertia(fn ($page) => $page->where('deletable', false));
});

test('a club admin may not change a shared role, but a root account may', function () {
    $shared = Role::query()->whereNull('club_id')->where('name', 'Kassier')->firstOrFail();

    $this->actingAs(roleUser())
        ->get(route('roles.edit', $shared))
        ->assertForbidden();

    $this->actingAs(roleUser(attributes: ['admin' => true]))
        ->put(route('roles.update', $shared), ['name' => 'Kassenwart'])
        ->assertRedirect();

    expect($shared->refresh()->name)->toBe('Kassenwart');
});

test('a role of another club cannot be reached by guessing its id', function () {
    $foreign = Role::factory()->create();

    $this->actingAs(roleUser())
        ->get(route('roles.edit', $foreign))
        ->assertNotFound();
});
