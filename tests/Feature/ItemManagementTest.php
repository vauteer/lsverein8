<?php

use App\Enums\ClubRole;
use App\Models\Club;
use App\Models\Item;
use App\Models\Member;
use App\Models\User;
use Illuminate\Database\QueryException;

/**
 * currentClubId() resolves to 1 on the CLI, so every request is read as though
 * the acting user were working in club 1. The inventory is opt-in, so unlike
 * the other CRUDs club 1 has to switch it on before anything is reachable.
 */
beforeEach(function () {
    $this->club = Club::factory()->create(['id' => 1, 'use_items' => true]);
});

/**
 * Create a user belonging to the given club (defaulting to club 1) with the
 * given role in it.
 */
function itemUser(ClubRole $role = ClubRole::Admin, ?Club $club = null, array $attributes = []): User
{
    $club ??= Club::find(1) ?? Club::factory()->create(['id' => 1]);

    $user = User::factory()->create([...$attributes, 'club_id' => $club->id]);
    $user->clubs()->attach($club->id, ['role' => $role->value]);

    return $user;
}

test('guests are redirected to the login page', function () {
    $this->get(route('items.index'))->assertRedirect(route('login'));
});

test('the index lists the club items but no other club', function () {
    $own = Item::factory()->create(['club_id' => 1, 'name' => 'Helm 1950']);
    $foreign = Item::factory()->create(['name' => 'Fremder Gegenstand']);

    $this->actingAs(itemUser(ClubRole::Basic))
        ->get(route('items.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('items/Index')
            ->has('items.data', 1)
            ->where('items.data.0.id', $own->id)
            // Everybody in the club may look, only an admin may change.
            ->where('items.data.0.modifiable', false)
            ->whereNot('items.data.0.id', $foreign->id)
        );
});

test('an item always belongs to a club, so there are no shared rows', function () {
    // `items.club_id` is NOT NULL, unlike sections, events and roles. That is
    // why Item carries ClubScope and the CRUD has no `shared` flag and no
    // root-only branch. Pinned here so making the column nullable fails loudly
    // instead of quietly leaving unreachable rows behind.
    expect(fn () => Item::factory()->create(['club_id' => null]))
        ->toThrow(QueryException::class);
});

test('the index counts only the current club members and can be searched', function () {
    $item = Item::factory()->create(['club_id' => 1, 'name' => 'Jacke Bayern 2000']);
    Item::factory()->create(['club_id' => 1, 'name' => 'Helm 1950']);

    $holder = Member::factory()->ofClub(1)->create();
    $holder->memberships()->attach(1, ['from' => '2016-01-01', 'to' => null]);
    $item->members()->attach($holder->id, ['from' => now()->subYear()]);

    // Another club's member holds it too, but must not be counted here.
    $item->members()->attach(Member::factory()->create()->id, ['from' => now()->subYear()]);

    $this->actingAs(itemUser())
        ->get(route('items.index', ['search' => 'Jacke']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('items.data', 1)
            ->where('items.data.0.name', 'Jacke Bayern 2000')
            ->where('items.data.0.members_count', 1)
            ->where('filters.search', 'Jacke')
        );
});

test('the two counts are what is still out and what was ever issued', function () {
    $item = Item::factory()->create(['club_id' => 1, 'name' => 'Helm 1950']);

    // Still has it: both columns.
    $holder = Member::factory()->ofClub(1)->create(['surname' => 'Traegerin']);
    $holder->memberships()->attach(1, ['from' => '2016-01-01', 'to' => null]);
    $holder->items()->attach($item->id, ['from' => '2016-01-01', 'to' => null]);

    // Gave it back: "ever" only.
    $returned = Member::factory()->ofClub(1)->create(['surname' => 'Rueckgeberin']);
    $returned->memberships()->attach(1, ['from' => '2010-01-01', 'to' => null]);
    $returned->items()->attach($item->id, ['from' => '2010-01-01', 'to' => '2015-12-31']);

    $this->actingAs(itemUser());

    $this->get(route('items.index', ['search' => 'Helm']))
        ->assertInertia(fn ($page) => $page
            ->has('items.data', 1)
            ->where('items.data.0.members_count', 1)
            ->where('items.data.0.ever_members_count', 2)
        );

    // Each number equals the selection it links to.
    $this->get(route('members.index', ['filter' => "item_{$item->id}"]))
        ->assertInertia(fn ($page) => $page
            ->has('members.data', 1)
            ->where('members.data.0.surname', 'Traegerin')
        );

    $this->get(route('members.index', ['filter' => "ever_item_{$item->id}"]))
        ->assertInertia(fn ($page) => $page->has('members.data', 2));
});

test('the ever selection keeps former members, as its name says', function () {
    $item = Item::factory()->create(['club_id' => 1, 'name' => 'Helm 1950']);

    $gone = Member::factory()->ofClub(1)->create(['surname' => 'Ausgetreten']);
    $gone->memberships()->attach(1, ['from' => '2005-01-01', 'to' => '2009-12-31']);
    $gone->items()->attach($item->id, ['from' => '2005-01-01', 'to' => '2009-12-31']);

    $this->actingAs(itemUser());

    // ever_item used to be narrowed with members(), which dropped exactly the
    // people the selection exists to show. ever_role never was.
    $this->get(route('members.index', ['filter' => "ever_item_{$item->id}"]))
        ->assertInertia(fn ($page) => $page
            ->has('members.data', 1)
            ->where('members.data.0.surname', 'Ausgetreten')
        );

    $this->get(route('items.index', ['search' => 'Helm']))
        ->assertInertia(fn ($page) => $page
            ->where('items.data.0.members_count', 0)
            ->where('items.data.0.ever_members_count', 1)
        );
});

test('a club without the inventory switched on reaches nothing', function () {
    $this->club->update(['use_items' => false]);

    $item = Item::factory()->create(['club_id' => 1]);

    // Not merely hidden in the sidebar: every route is refused. Root included,
    // since a root account always works inside one club.
    $this->actingAs(itemUser(attributes: ['admin' => true]));

    $this->get(route('items.index'))->assertForbidden();
    $this->get(route('items.create'))->assertForbidden();
    $this->post(route('items.store'), ['name' => 'Helm'])->assertForbidden();
    $this->get(route('items.edit', $item))->assertForbidden();
    $this->put(route('items.update', $item), ['name' => 'Helm'])->assertForbidden();
    $this->delete(route('items.destroy', $item))->assertForbidden();
});

test('the sidebar only carries the inventory where the club uses one', function () {
    $this->actingAs(itemUser())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('currentClub.uses_items', true));

    $this->club->update(['use_items' => false]);

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('currentClub.uses_items', false));
});

test('only an admin reaches the create form and stores an item', function () {
    $this->actingAs(itemUser(ClubRole::Advanced))
        ->get(route('items.create'))
        ->assertForbidden();

    $this->actingAs(itemUser())
        ->get(route('items.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('items/Create'));

    $this->post(route('items.store'), ['name' => 'Helm 1950'])->assertRedirect();

    $item = Item::firstOrFail();

    expect($item->name)->toBe('Helm 1950')
        // Never taken from the request, always the current club.
        ->and($item->club_id)->toBe(1);
});

test('club_id from the request is ignored, so an item cannot be planted elsewhere', function () {
    $other = Club::factory()->create();

    $this->actingAs(itemUser())
        ->post(route('items.store'), ['name' => 'Helm 1950', 'club_id' => $other->id])
        ->assertRedirect();

    expect(Item::withoutGlobalScopes()->firstOrFail()->club_id)->toBe(1);
});

test('the name is unique within the club but not across clubs', function () {
    Item::factory()->create(['club_id' => 1, 'name' => 'Helm 1950']);

    $this->actingAs(itemUser());

    $this->post(route('items.store'), ['name' => 'Helm 1950'])
        ->assertSessionHasErrors('name');

    // The same name in another club is fine — unique(club_id, name).
    Item::factory()->create(['name' => 'Helm 1950']);

    expect(Item::withoutGlobalScopes()->where('name', 'Helm 1950')->count())->toBe(2);
});

test('a non-admin may not edit an item', function () {
    $item = Item::factory()->create(['club_id' => 1]);

    $this->actingAs(itemUser(ClubRole::Advanced))
        ->get(route('items.edit', $item))
        ->assertForbidden();
});

test('an admin renames an item', function () {
    $item = Item::factory()->create(['club_id' => 1, 'name' => 'Helm 1950']);

    $this->actingAs(itemUser())
        ->get(route('items.edit', $item))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('items/Edit')
            ->where('item.name', 'Helm 1950')
            ->where('deletable', true)
        );

    $this->put(route('items.update', $item), ['name' => 'Helm 1960'])->assertRedirect();

    expect($item->refresh()->name)->toBe('Helm 1960');
});

test('an unused item is deleted and one a member has held is kept', function () {
    $unused = Item::factory()->create(['club_id' => 1]);
    $used = Item::factory()->create(['club_id' => 1]);

    $used->members()->attach(Member::factory()->ofClub(1)->create()->id, ['from' => now()->subYear()]);

    $this->actingAs(itemUser());

    $this->delete(route('items.destroy', $unused))->assertRedirect();
    expect(Item::find($unused->id))->toBeNull();

    // item_member.item_id is ON DELETE RESTRICT, so the database would refuse
    // this anyway; the policy is what turns that into a 403 instead of a 500.
    $this->delete(route('items.destroy', $used))->assertForbidden();
    expect(Item::find($used->id))->not->toBeNull();

    $this->get(route('items.edit', $used))
        ->assertInertia(fn ($page) => $page->where('deletable', false));
});

test('an item of another club cannot be reached by guessing its id', function () {
    $foreign = Item::factory()->create();

    $this->actingAs(itemUser())
        ->get(route('items.edit', $foreign))
        ->assertNotFound();
});
