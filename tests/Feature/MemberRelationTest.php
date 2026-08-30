<?php

use App\Enums\ClubRole;
use App\Models\Club;
use App\Models\Event;
use App\Models\Item;
use App\Models\Member;
use App\Models\MemberSection;
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
    Member::setKeyDate(null);
    $this->member = Member::factory()->ofClub(1)->create();
    $this->member->memberships()->attach(1, ['from' => '2016-01-01', 'to' => null]);
});

afterEach(fn () => Member::setKeyDate(null));

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

    // A second one, so removing the first is not removing the last — that is
    // refused while the membership is open, and has its own test below.
    $this->member->subscriptions()->attach(
        Subscription::factory()->create(['club_id' => 1])->id
    );

    $this->delete(route('members.subscriptions.destroy', [$this->member, $row->id]))
        ->assertRedirect();

    expect($this->member->subscriptions()->count())->toBe(1);
});

test('a current member cannot be left without a subscription', function () {
    $subscription = Subscription::factory()->create(['club_id' => 1]);
    $this->member->subscriptions()->attach($subscription->id);
    $row = $this->member->subscriptions()->first()->pivot;

    $this->actingAs(relationUser());

    // The confirmation dialog has no field to hang a message on, so this is a
    // toast rather than a validation error — same split as the last section.
    $this->delete(route('members.subscriptions.destroy', [$this->member, $row->id]))
        ->assertRedirect();

    expect($this->member->subscriptions()->count())->toBe(1);

    // Once the membership is over the leftover row has to be removable again,
    // otherwise a departed member could never be tidied up.
    $this->member->memberships()->updateExistingPivot(1, ['to' => now()->subDay()->format('Y-m-d')]);

    $this->delete(route('members.subscriptions.destroy', [$this->member, $row->id]))
        ->assertRedirect();

    expect($this->member->subscriptions()->count())->toBe(0);
});

test('a member who has died may lose their last subscription', function () {
    $subscription = Subscription::factory()->create(['club_id' => 1]);
    $this->member->subscriptions()->attach($subscription->id);
    // The membership row stays open, but the dead are not current members and
    // nothing bills them, so the guard has nothing to protect here.
    $this->member->update(['death_day' => '2024-06-30']);
    $row = $this->member->subscriptions()->first()->pivot;

    $this->actingAs(relationUser())
        ->delete(route('members.subscriptions.destroy', [$this->member, $row->id]))
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

/**
 * A BLSV club reports its members section by section, so somebody still in the
 * club has to be in at least one — a member in none would simply be missing
 * from the yearly Meldung. Refused at the point of entry rather than found
 * later.
 */
function blsvClub(): void
{
    Club::query()->whereKey(1)->update(['blsv_member' => true]);
}

test('the last active section of a member cannot be closed in a blsv club', function () {
    blsvClub();
    $section = Section::factory()->create(['club_id' => 1]);
    $this->member->sections()->attach($section->id, ['from' => '2016-01-01']);
    $row = $this->member->sections()->first()->pivot;

    $this->actingAs(relationUser())
        ->put(route('members.sections.update', [$this->member, $row->id]), [
            'section_id' => $section->id,
            'from' => '2016-01-01',
            'to' => '2020-12-31',
            'memo' => null,
        ])
        ->assertSessionHasErrors('to');

    expect($row->refresh()->to)->toBeNull();
});

test('the last active section of a member cannot be deleted in a blsv club', function () {
    blsvClub();
    $section = Section::factory()->create(['club_id' => 1]);
    $this->member->sections()->attach($section->id, ['from' => '2016-01-01']);
    $row = $this->member->sections()->first()->pivot;

    // No form to hang an error on, so this one comes back as a toast — the
    // row simply survives.
    $this->actingAs(relationUser())
        ->delete(route('members.sections.destroy', [$this->member, $row->id]))
        ->assertRedirect();

    expect($this->member->sections()->count())->toBe(1);
});

test('a second active section frees the first to be closed or removed', function () {
    blsvClub();
    $first = Section::factory()->create(['club_id' => 1]);
    $second = Section::factory()->create(['club_id' => 1]);
    $this->member->sections()->attach($first->id, ['from' => '2016-01-01']);
    $this->member->sections()->attach($second->id, ['from' => '2020-01-01']);

    $row = MemberSection::query()->where('section_id', $first->id)->firstOrFail();

    $this->actingAs(relationUser())
        ->delete(route('members.sections.destroy', [$this->member, $row->id]))
        ->assertRedirect();

    expect($this->member->sections()->count())->toBe(1);
});

test('a section already closed is not what the guard is about', function () {
    blsvClub();
    $open = Section::factory()->create(['club_id' => 1]);
    $closed = Section::factory()->create(['club_id' => 1]);
    $this->member->sections()->attach($open->id, ['from' => '2016-01-01']);
    $this->member->sections()->attach($closed->id, ['from' => '2010-01-01', 'to' => '2012-12-31']);

    // Removing a spell that ended years ago leaves the member in one section,
    // so it is none of the guard's business.
    $row = MemberSection::query()->where('section_id', $closed->id)->firstOrFail();

    $this->actingAs(relationUser())
        ->delete(route('members.sections.destroy', [$this->member, $row->id]))
        ->assertRedirect();

    expect($this->member->sections()->count())->toBe(1);
});

test('the note and the start of the last section stay editable', function () {
    blsvClub();
    $section = Section::factory()->create(['club_id' => 1]);
    $this->member->sections()->attach($section->id, ['from' => '2016-01-01']);
    $row = $this->member->sections()->first()->pivot;

    // Only closing the row can leave the member with none.
    $this->actingAs(relationUser())
        ->put(route('members.sections.update', [$this->member, $row->id]), [
            'section_id' => $section->id,
            'from' => '2015-01-01',
            'to' => null,
            'memo' => 'Korrigiert',
        ])
        ->assertSessionHasNoErrors();

    expect($row->refresh()->memo)->toBe('Korrigiert')
        ->and($row->from->format('Y-m-d'))->toBe('2015-01-01');
});

test('a member who has left may lose their last section', function () {
    blsvClub();
    $section = Section::factory()->create(['club_id' => 1]);
    $this->member->sections()->attach($section->id, ['from' => '2016-01-01']);
    // resign() closes membership and sections together; the guard must not
    // then block tidying up what it left behind.
    $this->member->memberships()->updateExistingPivot(1, ['to' => '2020-12-31']);
    $row = $this->member->sections()->first()->pivot;

    $this->actingAs(relationUser())
        ->delete(route('members.sections.destroy', [$this->member, $row->id]))
        ->assertRedirect();

    expect($this->member->sections()->count())->toBe(0);
});

test('a member who has died may lose their last section', function () {
    blsvClub();
    $section = Section::factory()->create(['club_id' => 1]);
    $this->member->sections()->attach($section->id, ['from' => '2016-01-01']);
    // The membership row stays open, but the Meldung is built from
    // Member::memberIds(), which leaves the dead out — so the guard has
    // nothing to protect here either.
    $this->member->update(['death_day' => '2024-06-30']);
    $row = $this->member->sections()->first()->pivot;

    $this->actingAs(relationUser())
        ->delete(route('members.sections.destroy', [$this->member, $row->id]))
        ->assertRedirect();

    expect($this->member->sections()->count())->toBe(0);
});

test('a club outside the BLSV is not held to this', function () {
    $section = Section::factory()->create(['club_id' => 1]);
    $this->member->sections()->attach($section->id, ['from' => '2016-01-01']);
    $row = $this->member->sections()->first()->pivot;

    // The Feuerwehr keeps sections too, but reports to nobody.
    $this->actingAs(relationUser())
        ->delete(route('members.sections.destroy', [$this->member, $row->id]))
        ->assertRedirect();

    expect($this->member->sections()->count())->toBe(0);
});
