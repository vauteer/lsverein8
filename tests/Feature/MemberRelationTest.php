<?php

use App\Enums\ClubRole;
use App\Models\Club;
use App\Models\Event;
use App\Models\Item;
use App\Models\Member;
use App\Models\Role;
use App\Models\Section;
use App\Models\Subscription;
use App\Models\User;

/**
 * The six relations a member carries, all edited from the member page.
 *
 * currentClubId() resolves to 1 on the CLI, so every request is read as though
 * the acting user were working in club 1.
 */
beforeEach(function () {
    $this->club = Club::factory()->create(['id' => 1]);
    Member::$_keyDate = null;
    $this->member = Member::factory()->ofClub(1)->create();
    $this->member->memberships()->attach(1, ['from' => '2016-01-01', 'to' => null]);
});

afterEach(fn () => Member::$_keyDate = null);

/**
 * Create a user of club 1 with the given role in it.
 *
 * Deliberately local rather than shared with MemberManagementTest: Pest only
 * loads a test file's helpers when that file runs, so a single-file run of
 * this one would not see the other's.
 */
function relationUser(ClubRole $role = ClubRole::Admin): User
{
    $user = User::factory()->create(['club_id' => 1]);
    $user->clubs()->attach(1, ['role' => $role->value]);

    return $user;
}

test('the member page carries the relations and offers the pickers to an admin', function () {
    $section = Section::factory()->create(['club_id' => 1, 'name' => 'Fussball']);
    $this->member->sections()->attach($section->id, ['from' => '2016-01-01', 'memo' => 'Eintritt']);

    $this->actingAs(relationUser())
        ->get(route('members.show', $this->member))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('members/Show')
            ->has('member.memberships', 1)
            ->has('member.sections', 1)
            ->where('member.sections.0.name', 'Fussball')
            ->where('member.sections.0.related_id', $section->id)
            ->where('member.sections.0.from', '2016-01-01')
            // Still running, so the label says "seit" rather than carrying a
            // trailing dash.
            ->where('member.sections.0.range', 'seit 01.01.2016')
            ->where('member.sections.0.memo', 'Eintritt')
            ->where('modifiable', true)
            ->has('options.sections', 1)
            ->has('options.roles')
            ->has('options.events')
        );
});

test('a closed range keeps both dates', function () {
    $section = Section::factory()->create(['club_id' => 1]);
    $this->member->sections()->attach($section->id, ['from' => '2010-01-01', 'to' => '2012-12-31']);

    $this->actingAs(relationUser())
        ->get(route('members.show', $this->member))
        ->assertInertia(fn ($page) => $page
            ->where('member.sections.0.range', '01.01.2010-31.12.2012')
        );
});

test('a read-only account sees the relations but gets no pickers', function () {
    $this->actingAs(relationUser(ClubRole::Basic))
        ->get(route('members.show', $this->member))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('member.memberships', 1)
            ->where('modifiable', false)
            // Nothing to pick from, because nothing can be changed.
            ->where('options', null)
        );
});

test('an admin adds, edits and removes a section', function () {
    $section = Section::factory()->create(['club_id' => 1]);
    $other = Section::factory()->create(['club_id' => 1]);

    $this->actingAs(relationUser());

    $this->post(route('members.sections.store', $this->member), [
        'section_id' => $section->id,
        'from' => '2020-01-01',
        'to' => null,
        'memo' => 'Eintritt',
    ])->assertRedirect();

    $row = $this->member->sections()->first()->pivot;

    expect($row->section_id)->toBe($section->id)
        ->and($row->from->format('Y-m-d'))->toBe('2020-01-01')
        ->and($row->to)->toBeNull();

    // The related row may be swapped, which is what "correcting" a wrong pick
    // means; the pivot row keeps its identity.
    $this->put(route('members.sections.update', [$this->member, $row->id]), [
        'section_id' => $other->id,
        'from' => '2020-01-01',
        'to' => '2022-12-31',
        'memo' => null,
    ])->assertRedirect();

    $row->refresh();

    expect($row->section_id)->toBe($other->id)
        ->and($row->to->format('Y-m-d'))->toBe('2022-12-31');

    $this->delete(route('members.sections.destroy', [$this->member, $row->id]))
        ->assertRedirect();

    expect($this->member->sections()->count())->toBe(0);
});

test('the same section can be held twice, which is why rows carry a pivot id', function () {
    $section = Section::factory()->create(['club_id' => 1]);

    $this->actingAs(relationUser());

    foreach ([['2010-01-01', '2012-12-31'], ['2018-01-01', null]] as [$from, $to]) {
        $this->post(route('members.sections.store', $this->member), [
            'section_id' => $section->id,
            'from' => $from,
            'to' => $to,
        ])->assertRedirect();
    }

    $rows = $this->member->sections()->get();

    expect($rows)->toHaveCount(2)
        // Same section, two distinct rows — nothing but the pivot id tells
        // them apart, so that is what the routes address.
        ->and($rows->pluck('pivot.id')->unique())->toHaveCount(2);
});

test('a range must last at least one day', function () {
    $section = Section::factory()->create(['club_id' => 1]);

    $this->actingAs(relationUser());

    // Same floor the bulk resignation applies, so a period is never zero-length
    // whichever way it was entered.
    foreach (['2019-01-01', '2020-01-01'] as $notAfter) {
        $this->post(route('members.sections.store', $this->member), [
            'section_id' => $section->id,
            'from' => '2020-01-01',
            'to' => $notAfter,
        ])->assertSessionHasErrors('to');
    }

    $this->post(route('members.sections.store', $this->member), [
        'section_id' => $section->id,
        'from' => '2020-01-01',
        'to' => '2020-01-02',
    ])->assertSessionHasNoErrors();

    // An open range stays allowed: null `to` is what "still running" means.
    $this->post(route('members.sections.store', $this->member), [
        'section_id' => $section->id,
        'from' => '2021-01-01',
        'to' => null,
    ])->assertSessionHasNoErrors();

    expect($this->member->sections()->count())->toBe(2);
});

test('another club section, role or subscription is refused', function () {
    $foreignSection = Section::factory()->create();
    $foreignRole = Role::factory()->create();
    $foreignSubscription = Subscription::factory()->create();

    $this->actingAs(relationUser());

    // `exists` runs a plain query and does not pick up the club scopes, so the
    // rules scope by hand — this is what proves they do.
    $this->post(route('members.sections.store', $this->member), [
        'section_id' => $foreignSection->id, 'from' => '2020-01-01',
    ])->assertSessionHasErrors('section_id');

    $this->post(route('members.roles.store', $this->member), [
        'role_id' => $foreignRole->id, 'from' => '2020-01-01',
    ])->assertSessionHasErrors('role_id');

    $this->post(route('members.subscriptions.store', $this->member), [
        'subscription_id' => $foreignSubscription->id,
    ])->assertSessionHasErrors('subscription_id');
});

test('an installation-wide section, role or honour is accepted', function () {
    // sections, roles and events all have a nullable club_id; those rows
    // belong to every club.
    $section = Section::factory()->create(['club_id' => null]);
    $role = Role::factory()->create(['club_id' => null]);
    $event = Event::factory()->create(['club_id' => null]);

    $this->actingAs(relationUser());

    $this->post(route('members.sections.store', $this->member), [
        'section_id' => $section->id, 'from' => '2020-01-01',
    ])->assertSessionHasNoErrors();

    $this->post(route('members.roles.store', $this->member), [
        'role_id' => $role->id, 'from' => '2020-01-01',
    ])->assertSessionHasNoErrors();

    $this->post(route('members.events.store', $this->member), [
        'event_id' => $event->id, 'date' => '2020-01-01',
    ])->assertSessionHasNoErrors();

    expect($this->member->sections()->count())->toBe(1)
        ->and($this->member->roles()->count())->toBe(1)
        ->and($this->member->events()->count())->toBe(1);
});

test('an honour carries a single date rather than a range', function () {
    $event = Event::factory()->create(['club_id' => 1, 'name' => '25 Jahre Mitglied']);

    $this->actingAs(relationUser());

    $this->post(route('members.events.store', $this->member), [
        'event_id' => $event->id,
        'date' => '2024-05-01',
        'memo' => 'Jahreshauptversammlung',
    ])->assertRedirect();

    $row = $this->member->events()->first()->pivot;

    expect($row->date->format('Y-m-d'))->toBe('2024-05-01');

    $this->put(route('members.events.update', [$this->member, $row->id]), [
        'event_id' => $event->id,
        'date' => '2024-06-01',
    ])->assertRedirect();

    expect($row->refresh()->date->format('Y-m-d'))->toBe('2024-06-01');
});

test('a subscription carries nothing but a memo', function () {
    $subscription = Subscription::factory()->create(['club_id' => 1]);

    $this->actingAs(relationUser());

    $this->post(route('members.subscriptions.store', $this->member), [
        'subscription_id' => $subscription->id,
        'memo' => 'Ermaessigt',
    ])->assertRedirect();

    $row = $this->member->subscriptions()->first()->pivot;

    expect($row->memo)->toBe('Ermaessigt');

    $this->delete(route('members.subscriptions.destroy', [$this->member, $row->id]))
        ->assertRedirect();

    expect($this->member->subscriptions()->count())->toBe(0);
});

test('a second membership period is what makes a rejoining member add up', function () {
    $this->actingAs(relationUser());

    // Eight members in production have two periods, because they left and
    // rejoined; Member::membershipYears() sums them.
    $this->put(route('members.memberships.update', [
        $this->member, $this->member->memberships()->first()->pivot->id,
    ]), ['from' => '2000-01-01', 'to' => '2004-12-31'])->assertRedirect();

    $this->post(route('members.memberships.store', $this->member), [
        'from' => '2010-01-01', 'to' => null,
    ])->assertRedirect();

    $member = $this->member->fresh()->load('memberships');

    expect($member->memberships)->toHaveCount(2)
        // Every club_member row points at the member's own club; there is
        // nothing to pick, so the controller supplies it.
        ->and($member->memberships->pluck('id')->unique()->all())->toBe([1]);
});

test('the inventory relation is closed for a club that keeps no inventory', function () {
    $item = Item::factory()->create(['club_id' => 1]);

    $this->actingAs(relationUser());

    // ItemPolicy on top of the member policy: use_items is false by default.
    $this->post(route('members.items.store', $this->member), [
        'item_id' => $item->id, 'from' => '2020-01-01',
    ])->assertForbidden();

    $this->get(route('members.show', $this->member))
        ->assertInertia(fn ($page) => $page
            ->where('usesItems', false)
            ->has('member.items', 0)
        );

    $this->club->update(['use_items' => true]);

    $this->post(route('members.items.store', $this->member), [
        'item_id' => $item->id, 'from' => '2020-01-01',
    ])->assertRedirect();

    expect($this->member->items()->count())->toBe(1);
});

test('a non-admin may not change any relation', function () {
    $section = Section::factory()->create(['club_id' => 1]);
    $this->member->sections()->attach($section->id, ['from' => '2016-01-01']);
    $row = $this->member->sections()->first()->pivot;

    $this->actingAs(relationUser(ClubRole::Advanced));

    $this->post(route('members.sections.store', $this->member), [
        'section_id' => $section->id, 'from' => '2020-01-01',
    ])->assertForbidden();
    $this->put(route('members.sections.update', [$this->member, $row->id]), [
        'section_id' => $section->id, 'from' => '2020-01-01',
    ])->assertForbidden();
    $this->delete(route('members.sections.destroy', [$this->member, $row->id]))
        ->assertForbidden();
});

test('a row belonging to another member cannot be edited through this one', function () {
    $section = Section::factory()->create(['club_id' => 1]);
    $other = Member::factory()->ofClub(1)->create();
    $other->sections()->attach($section->id, ['from' => '2016-01-01']);
    $foreignRow = $other->sections()->first()->pivot;

    // Route model binding is deliberately not used for the pivot: it resolves
    // by primary key alone, so this row would otherwise be found and edited.
    $this->actingAs(relationUser())
        ->put(route('members.sections.update', [$this->member, $foreignRow->id]), [
            'section_id' => $section->id, 'from' => '2020-01-01',
        ])
        ->assertNotFound();

    $this->delete(route('members.sections.destroy', [$this->member, $foreignRow->id]))
        ->assertNotFound();

    expect($other->sections()->count())->toBe(1);
});

test('a member of another club is out of reach entirely', function () {
    $foreign = Member::factory()->create();
    $section = Section::factory()->create(['club_id' => 1]);

    $this->actingAs(relationUser())
        ->post(route('members.sections.store', $foreign), [
            'section_id' => $section->id, 'from' => '2020-01-01',
        ])
        ->assertNotFound();
});

test('a read-only account is not shown the subscriptions section at all', function () {
    $this->member->subscriptions()->attach(Subscription::factory()->create(['club_id' => 1])->id);

    $this->actingAs(relationUser(ClubRole::Basic))
        ->get(route('members.show', $this->member))
        ->assertInertia(fn ($page) => $page
            ->where('showsFinances', false)
            ->has('member.subscriptions', 0)
        );

    $this->actingAs(relationUser())
        ->get(route('members.show', $this->member))
        ->assertInertia(fn ($page) => $page
            ->where('showsFinances', true)
            ->has('member.subscriptions', 1)
        );
});
