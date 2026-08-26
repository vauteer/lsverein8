<?php

use App\Enums\ClubRole;
use App\Models\Club;
use App\Models\Event;
use App\Models\Member;
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
function eventUser(ClubRole $role = ClubRole::Admin, ?Club $club = null, array $attributes = []): User
{
    $club ??= Club::find(1) ?? Club::factory()->create(['id' => 1]);

    $user = User::factory()->create([...$attributes, 'club_id' => $club->id]);
    $user->clubs()->attach($club->id, ['role' => $role->value]);

    return $user;
}

/**
 * Drop the installation-wide events the 2022_08_20 migration seeds ('25 Jahre'
 * … 'Ehrenvorstand'), so a listing contains only the fixtures under test.
 */
function withoutDefaultEvents(): void
{
    Event::query()->whereNull('club_id')->delete();
}

test('guests are redirected to the login page', function () {
    $this->get(route('events.index'))->assertRedirect(route('login'));
});

test('the seeded installation-wide events are listed for the club', function () {
    $this->actingAs(eventUser(ClubRole::Basic))
        ->get(route('events.index', ['search' => 'Ehrenvorstand']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('events/Index')
            ->has('events.data', 1)
            ->where('events.data.0.name', 'Ehrenvorstand')
            ->where('events.data.0.shared', true)
            // Only a root account may touch an installation-wide event.
            ->where('events.data.0.modifiable', false)
        );
});

test('the index lists the club events and the shared ones, but no other club', function () {
    withoutDefaultEvents();

    $own = Event::factory()->create(['club_id' => 1, 'name' => 'Vereinsjubiläum']);
    $shared = Event::factory()->create(['club_id' => null, 'name' => 'Aufnahme']);
    $foreign = Event::factory()->create(['name' => 'Fremdes Ereignis']);

    $this->actingAs(eventUser(ClubRole::Basic))
        ->get(route('events.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('events/Index')
            ->has('events.data', 2)
            ->where('events.data.0.id', $shared->id)
            ->where('events.data.0.shared', true)
            ->where('events.data.1.id', $own->id)
            ->where('events.data.1.shared', false)
            ->whereNot('events.data.0.id', $foreign->id)
            ->whereNot('events.data.1.id', $foreign->id)
        );
});

test('the index counts only the current club members and can be searched', function () {
    $event = Event::factory()->create(['club_id' => 1, 'name' => 'Zeltlager']);
    Event::factory()->create(['club_id' => 1, 'name' => 'Vereinsjubiläum']);

    $member = Member::factory()->ofClub(1)->create();
    $foreignMember = Member::factory()->create();

    $event->members()->attach([$member->id, $foreignMember->id], ['date' => now()->subYear()]);

    $this->actingAs(eventUser())
        ->get(route('events.index', ['search' => 'Zelt']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('events.data', 1)
            ->where('events.data.0.id', $event->id)
            ->where('events.data.0.members_count', 1)
            ->where('filters.search', 'Zelt')
        );
});

test('the count is who has been given the honour, matching what it links to', function () {
    $event = Event::factory()->create(['club_id' => 1, 'name' => 'Zeltlager']);

    $given = Member::factory()->ofClub(1)->create(['surname' => 'Geehrt']);
    $given->events()->attach($event->id, ['date' => '2020-06-01']);

    // Former members keep their honours, so the selection shows them and the
    // count includes them — no members() restriction on either side.
    $gone = Member::factory()->ofClub(1)->create(['surname' => 'Ausgetreten']);
    $gone->memberships()->attach(1, ['from' => '2005-01-01', 'to' => '2009-12-31']);
    $gone->events()->attach($event->id, ['date' => '2008-06-01']);

    // Twice over the years: one person, not two. event_member holds one such
    // pair in production.
    $given->events()->attach($event->id, ['date' => '2022-06-01']);

    // Dated ahead, so hadEvent() excludes it; six such rows exist in
    // production and withCount('members') counted every one.
    $planned = Member::factory()->ofClub(1)->create(['surname' => 'Vorgemerkt']);
    $planned->events()->attach($event->id, ['date' => now()->addMonth()]);

    $this->actingAs(eventUser());

    $this->get(route('events.index', ['search' => 'Zelt']))
        ->assertInertia(fn ($page) => $page
            ->has('events.data', 1)
            ->where('events.data.0.members_count', 2)
        );

    // Exactly what the number links to.
    $this->get(route('members.index', ['filter' => "event_{$event->id}"]))
        ->assertInertia(fn ($page) => $page->has('members.data', 2));
});

test('a member without admin rights may view but not change events', function () {
    $event = Event::factory()->create(['club_id' => 1]);

    $this->actingAs(eventUser(ClubRole::Advanced));

    $this->get(route('events.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->where('canCreate', false));
    $this->get(route('events.create'))->assertForbidden();
    $this->get(route('events.edit', $event))->assertForbidden();
    $this->delete(route('events.destroy', $event))->assertForbidden();
});

test('an admin creates an event for the current club', function () {
    $this->actingAs(eventUser())
        ->post(route('events.store'), ['name' => 'Vereinsjubiläum'])
        ->assertRedirect();

    $event = Event::query()->where('club_id', 1)->firstOrFail();

    expect($event->name)->toBe('Vereinsjubiläum');
});

test('an event name must be unique among the club and shared events', function () {
    Event::factory()->create(['club_id' => 1, 'name' => 'Vereinsjubiläum']);
    Event::factory()->create(['name' => 'Fremdes Ereignis']);

    $this->actingAs(eventUser());

    $this->post(route('events.store'), ['name' => 'Vereinsjubiläum'])->assertSessionHasErrors('name');
    // '50 Jahre' is one of the seeded installation-wide events.
    $this->post(route('events.store'), ['name' => '50 Jahre'])->assertSessionHasErrors('name');

    // Another club's name is free: it is never listed alongside these.
    $this->post(route('events.store'), ['name' => 'Fremdes Ereignis'])->assertSessionHasNoErrors();
});

test('an admin renames an event', function () {
    $event = Event::factory()->create(['club_id' => 1, 'name' => 'Zeltlager']);

    $this->actingAs(eventUser())
        ->get(route('events.edit', $event))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('events/Edit')
            ->where('event.name', 'Zeltlager')
            ->where('deletable', true)
        );

    $this->put(route('events.update', $event), ['name' => 'Sommerzeltlager'])
        ->assertRedirect();

    expect($event->refresh()->name)->toBe('Sommerzeltlager');
});

test('an unused event is deleted and a used one is kept', function () {
    $unused = Event::factory()->create(['club_id' => 1]);
    $used = Event::factory()->create(['club_id' => 1]);

    $used->members()->attach(Member::factory()->ofClub(1)->create()->id, ['date' => now()->subYear()]);

    $this->actingAs(eventUser());

    $this->delete(route('events.destroy', $unused))->assertRedirect();
    expect(Event::find($unused->id))->toBeNull();

    $this->delete(route('events.destroy', $used))->assertForbidden();
    expect(Event::find($used->id))->not->toBeNull();

    $this->get(route('events.edit', $used))
        ->assertInertia(fn ($page) => $page->where('deletable', false));
});

test('a club admin may not change a shared event, but a root account may', function () {
    $shared = Event::query()->whereNull('club_id')->where('name', '50 Jahre')->firstOrFail();

    $this->actingAs(eventUser())
        ->get(route('events.edit', $shared))
        ->assertForbidden();

    $this->actingAs(eventUser(attributes: ['admin' => true]))
        ->put(route('events.update', $shared), ['name' => '50 Jahre Mitgliedschaft'])
        ->assertRedirect();

    expect($shared->refresh()->name)->toBe('50 Jahre Mitgliedschaft');
});

test('an event of another club cannot be reached by guessing its id', function () {
    $foreign = Event::factory()->create();

    $this->actingAs(eventUser())
        ->get(route('events.edit', $foreign))
        ->assertNotFound();
});
