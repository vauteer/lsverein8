<?php

use App\Enums\ClubRole;
use App\Enums\Gender;
use App\Enums\MemberFilter;
use App\Enums\PaymentMethod;
use App\Models\Club;
use App\Models\Debit;
use App\Models\Event;
use App\Models\Item;
use App\Models\Member;
use App\Models\Role;
use App\Models\Section;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\QueryException;

/**
 * currentClubId() resolves to 1 on the CLI, so every request is read as though
 * the acting user were working in club 1.
 */
beforeEach(function () {
    $this->club = Club::factory()->create(['id' => 1, 'honor_years' => '25,40']);
    Member::$_keyDate = null;
});

afterEach(fn () => Member::$_keyDate = null);

/**
 * Create a user belonging to the given club (defaulting to club 1) with the
 * given role in it.
 */
function memberUser(ClubRole $role = ClubRole::Admin, ?Club $club = null, array $attributes = []): User
{
    $club ??= Club::find(1) ?? Club::factory()->create(['id' => 1]);

    $user = User::factory()->create([...$attributes, 'club_id' => $club->id]);
    $user->clubs()->attach($club->id, ['role' => $role->value]);

    return $user;
}

/**
 * A member of club 1 with an open membership, so the default `members`
 * selection actually returns them.
 */
function joinedMember(array $attributes = [], string $from = '2016-01-01'): Member
{
    $member = Member::factory()->ofClub(1)->create($attributes);
    $member->memberships()->attach(1, ['from' => $from, 'to' => null]);

    return $member;
}

/**
 * A valid payload; individual tests override the field they are exercising.
 *
 * @return array<string, mixed>
 */
function memberPayload(array $overrides = []): array
{
    return [
        'surname' => 'Meier',
        'first_name' => 'Anna',
        'gender' => Gender::Frau->value,
        'birthday' => '1980-05-04',
        'death_day' => null,
        'street' => 'Hauptstr. 1',
        'zipcode' => '86720',
        'city' => 'Nördlingen',
        'email' => 'anna@example.test',
        'phone' => '0900 12345',
        'payment_method' => PaymentMethod::Invoice->value,
        'memo' => null,
        ...$overrides,
    ];
}

test('guests are redirected to the login page', function () {
    $this->get(route('members.index'))->assertRedirect(route('login'));
});

test('the index lists the club members but no other club', function () {
    $own = joinedMember(['surname' => 'Eigen', 'first_name' => 'Erika']);
    $foreign = Member::factory()->create(['surname' => 'Fremd']);

    $this->actingAs(memberUser(ClubRole::Basic))
        ->get(route('members.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('members/Index')
            ->has('members.data', 1)
            ->where('members.data.0.id', $own->id)
            ->where('members.data.0.is_member', true)
            // Everybody in the club may read the list, only an admin may edit.
            ->where('members.data.0.modifiable', false)
            ->whereNot('members.data.0.id', $foreign->id)
        );
});

test('a read-only account is not shown what a member pays', function () {
    $member = joinedMember();
    $subscription = Subscription::factory()->create(['club_id' => 1, 'name' => 'Jahresbeitrag']);
    $member->subscriptions()->attach($subscription->id);

    $this->actingAs(memberUser(ClubRole::Basic))
        ->get(route('members.index'))
        ->assertInertia(fn ($page) => $page
            ->where('members.data.0.subscriptions', null)
            ->where('members.data.0.last_event', null)
        );

    $this->actingAs(memberUser())
        ->get(route('members.index'))
        ->assertInertia(fn ($page) => $page
            ->where('members.data.0.subscriptions', 'Jahresbeitrag')
        );
});

test('only an admin is offered the without-a-subscription selection', function () {
    $ids = fn ($page) => collect($page->toArray()['props']['filterOptions'])->pluck('id');

    $this->actingAs(memberUser())
        ->get(route('members.index'))
        ->assertInertia(fn ($page) => expect($ids($page))->toContain('no_subscription'));

    $this->actingAs(memberUser(ClubRole::Basic))
        ->get(route('members.index'))
        ->assertInertia(fn ($page) => expect($ids($page))->not->toContain('no_subscription'));
});

test('a selection a non-admin may not pick falls back to the default', function () {
    joinedMember();
    Member::factory()->ofClub(1)->create(['surname' => 'Nichtmitglied']);

    // Not a 403: filters live in bookmarks and in the back button, so an
    // unavailable one quietly becomes the default rather than an error page.
    $this->actingAs(memberUser(ClubRole::Basic))
        ->get(route('members.index', ['filter' => 'no_subscription']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('members.data', 1));

    $this->get(route('members.index', ['filter' => 'erfunden']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('members.data', 1));
});

test('the default selection hides former members and the all selection shows them', function () {
    joinedMember(['surname' => 'Aktiv']);
    $former = Member::factory()->ofClub(1)->create(['surname' => 'Ehemalig']);
    $former->memberships()->attach(1, ['from' => '2010-01-01', 'to' => '2015-01-01']);

    $this->actingAs(memberUser());

    $this->get(route('members.index'))
        ->assertInertia(fn ($page) => $page
            ->has('members.data', 1)
            ->where('members.data.0.surname', 'Aktiv')
        );

    $this->get(route('members.index', ['filter' => 'all']))
        ->assertInertia(fn ($page) => $page->has('members.data', 2));

    $this->get(route('members.index', ['filter' => 'former']))
        ->assertInertia(fn ($page) => $page
            ->has('members.data', 1)
            ->where('members.data.0.surname', 'Ehemalig')
        );
});

test('the age selections read against the chosen key date', function () {
    joinedMember(['surname' => 'Kind', 'birthday' => '2015-06-01']);
    joinedMember(['surname' => 'Erwachsen', 'birthday' => '1980-06-01']);

    $this->actingAs(memberUser());

    $this->get(route('members.index', ['filter' => 'children']))
        ->assertInertia(fn ($page) => $page
            ->has('members.data', 1)
            ->where('members.data.0.surname', 'Kind')
        );

    $this->get(route('members.index', ['filter' => 'adults']))
        ->assertInertia(fn ($page) => $page
            ->has('members.data', 1)
            ->where('members.data.0.surname', 'Erwachsen')
        );
});

test('the year picker moves the key date, so ages and membership years follow', function () {
    joinedMember(['surname' => 'Jubilar', 'birthday' => '1980-06-01'], from: '2000-01-01');

    $this->actingAs(memberUser());

    $current = $this->get(route('members.index'))
        ->assertInertia(fn ($page) => $page->where('filters.year', now()->year));

    $past = $this->get(route('members.index', ['year' => now()->year - 5]))
        ->assertInertia(fn ($page) => $page->where('filters.year', now()->year - 5));

    $ageNow = $current->viewData('page')['props']['members']['data'][0]['age'];
    $ageThen = $past->viewData('page')['props']['members']['data'][0]['age'];

    expect($ageThen)->toBe($ageNow - 5);
});

test('an out-of-range year is clamped rather than refused', function () {
    joinedMember();

    $this->actingAs(memberUser());

    $this->get(route('members.index', ['year' => 1990]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('filters.year', now()->year - 10));

    $this->get(route('members.index', ['year' => now()->year + 5]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('filters.year', now()->year));
});

test('the search covers name, address and memo', function () {
    joinedMember(['surname' => 'Meier', 'first_name' => 'Anna', 'city' => 'Nördlingen', 'memo' => 'Kassiererin']);
    joinedMember(['surname' => 'Huber', 'first_name' => 'Bert', 'city' => 'Augsburg', 'memo' => null]);

    $this->actingAs(memberUser());

    foreach (['Meier', 'Nördlingen', 'Kassiererin'] as $term) {
        $this->get(route('members.index', ['search' => $term]))
            ->assertInertia(fn ($page) => $page
                ->has('members.data', 1)
                ->where('members.data.0.surname', 'Meier')
            );
    }
});

test('the order is picked by name and stays stable on a tie', function () {
    joinedMember(['surname' => 'Zwerg', 'first_name' => 'Anton', 'city' => 'Aachen']);
    joinedMember(['surname' => 'Adler', 'first_name' => 'Berta', 'city' => 'Zwickau']);

    $this->actingAs(memberUser());

    $this->get(route('members.index'))
        ->assertInertia(fn ($page) => $page->where('members.data.0.surname', 'Adler'));

    $this->get(route('members.index', ['sort' => 'address']))
        ->assertInertia(fn ($page) => $page->where('members.data.0.surname', 'Zwerg'));

    // An unknown order falls back to the default rather than 500ing.
    $this->get(route('members.index', ['sort' => 'erfunden']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('filters.sort', 'name'));
});

/**
 * Five selections rest on scopes written in MySQL-only SQL (YEAR, LEAST,
 * FIND_IN_SET), which the SQLite test connection cannot execute — see the
 * model-layer rule. The driver still reports the prepared statement, so the
 * exception is what proves the selection reaches the scope it should.
 */
test('the MySQL-only selections are wired to the right scope', function () {
    $this->club->update(['honor_years' => '25,40']);
    Member::$_keyDate = Carbon\Carbon::parse('2024-06-01');

    $cases = [
        [MemberFilter::MilestoneBirthdays, 'YEAR(birthday)'],
        [MemberFilter::Deaths, 'YEAR(`death_day`)'],
        [MemberFilter::Joined, 'YEAR(`from`)'],
        [MemberFilter::Retired, 'YEAR(`to`)'],
        [MemberFilter::DueHonours, 'FIND_IN_SET'],
    ];

    foreach ($cases as [$filter, $fragment]) {
        try {
            $query = Member::query();
            $filter->apply($query);
            $query->get();

            $this->fail("Expected SQLite to reject the MySQL-only SQL behind {$filter->value}.");
        } catch (QueryException $exception) {
            expect($exception->getSql())->toContain($fragment);
        }
    }
});

test('the section selection is offered per club row and narrows the list', function () {
    $section = Section::factory()->create(['club_id' => 1, 'name' => 'Fussball']);
    $inSection = joinedMember(['surname' => 'Kicker']);
    $inSection->sections()->attach($section->id, ['from' => '2016-01-01']);
    joinedMember(['surname' => 'Ohne']);

    $this->actingAs(memberUser())
        ->get(route('members.index', ['filter' => "section_{$section->id}"]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('members.data', 1)
            ->where('members.data.0.surname', 'Kicker')
            ->where('members.data.0.sections', 'Fussball')
        );
});

test('a subscription selection switches the year picker off', function () {
    $subscription = Subscription::factory()->create(['club_id' => 1]);

    $this->actingAs(memberUser());

    $this->get(route('members.index'))
        ->assertInertia(fn ($page) => $page->where('yearApplies', true));

    // A subscription has no from/to, so "who held it in 2019" is unanswerable.
    $this->get(route('members.index', ['filter' => "subscription_{$subscription->id}"]))
        ->assertInertia(fn ($page) => $page->where('yearApplies', false));
});

test('the payment selection takes the enum value, not a raw letter', function () {
    joinedMember(['surname' => 'Zahler', 'payment_method' => 'k']);
    joinedMember(['surname' => 'Rechnung', 'payment_method' => 'r']);

    $this->actingAs(memberUser());

    $this->get(route('members.index', ['filter' => 'payment_k']))
        ->assertInertia(fn ($page) => $page
            ->has('members.data', 1)
            ->where('members.data.0.surname', 'Zahler')
        );

    // An unknown letter is not a PaymentMethod, so it falls back.
    $this->get(route('members.index', ['filter' => 'payment_x']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('members.data', 2));
});

test('the inventory selections only appear where the club keeps one', function () {
    $ids = fn ($page) => collect($page->toArray()['props']['filterOptions'])->pluck('id');

    Item::factory()->create(['club_id' => 1, 'name' => 'Helm']);

    $this->actingAs(memberUser())
        ->get(route('members.index'))
        ->assertInertia(fn ($page) => expect($ids($page))->not->toContain('item_1'));

    $this->club->update(['use_items' => true]);

    $this->get(route('members.index'))
        ->assertInertia(fn ($page) => expect($ids($page))->toContain('item_1', 'ever_item_1'));
});

test('only an admin reaches the create form and stores a member', function () {
    $section = Section::factory()->create(['club_id' => 1]);
    $subscription = Subscription::factory()->create(['club_id' => 1]);

    $this->actingAs(memberUser(ClubRole::Advanced))
        ->get(route('members.create'))
        ->assertForbidden();

    $this->actingAs(memberUser())
        ->get(route('members.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('members/Create')
            ->has('sections', 1)
            ->has('genders', 2)
            ->has('paymentMethods', 3)
        );

    $this->post(route('members.store'), memberPayload([
        'entry_date' => '2024-03-01',
        'section_id' => $section->id,
        'subscription_id' => $subscription->id,
    ]))->assertRedirect();

    $member = Member::firstOrFail();

    expect($member->surname)->toBe('Meier')
        // Never taken from the request, always the current club.
        ->and($member->club_id)->toBe(1)
        ->and($member->gender)->toBe(Gender::Frau)
        ->and($member->payment_method)->toBe(PaymentMethod::Invoice)
        // The membership, the first section and the subscription all start
        // from the one entry date.
        ->and($member->memberships)->toHaveCount(1)
        ->and($member->memberships->first()->pivot->from->format('Y-m-d'))->toBe('2024-03-01')
        ->and($member->sections)->toHaveCount(1)
        ->and($member->sections->first()->pivot->from->format('Y-m-d'))->toBe('2024-03-01')
        ->and($member->subscriptions)->toHaveCount(1);
});

test('the diverse gender is parked: neither offered nor accepted', function () {
    $section = Section::factory()->create(['club_id' => 1]);

    // The case exists and the column would hold it, but the BLSV statistic can
    // only report m or w. Refused outright rather than half-disabled: hiding it
    // from the picker alone would still let a posted value through.
    $this->actingAs(memberUser())
        ->post(route('members.store'), memberPayload([
            'entry_date' => '2024-03-01',
            'section_id' => $section->id,
            'gender' => Gender::Divers->value,
        ]))
        ->assertSessionHasErrors('gender');

    expect(Member::query()->where('surname', 'Meier')->count())->toBe(0)
        ->and(Gender::selectable())->toBe([Gender::Frau, Gender::Mann])
        // The mapping is ready for the day the association answers.
        ->and(Gender::Mann->blsvValue())->toBe('m')
        ->and(Gender::Frau->blsvValue())->toBe('w')
        ->and(Gender::Divers->blsvValue())->toBe('w');
});

test('the member number is handed out by the server, never taken from the form', function () {
    $section = Section::factory()->create(['club_id' => 1]);
    Member::factory()->ofClub(1)->create(['member_id' => 41]);
    // Another club's numbering must not push this club's along.
    Member::factory()->create(['member_id' => 900]);

    $this->actingAs(memberUser())
        ->post(route('members.store'), memberPayload([
            'entry_date' => '2024-03-01',
            'section_id' => $section->id,
            'member_id' => 999,
        ]))
        ->assertRedirect();

    expect(Member::query()->where('surname', 'Meier')->firstOrFail()->member_id)->toBe(42);
});

test('a new member cannot be filed under another club section or subscription', function () {
    $foreignSection = Section::factory()->create();
    $ownSection = Section::factory()->create(['club_id' => 1]);
    $foreignSubscription = Subscription::factory()->create();

    $this->actingAs(memberUser());

    // `exists` runs a plain query and does not pick up the ClubScope, so the
    // rules scope by club by hand — this is what proves they do.
    $this->post(route('members.store'), memberPayload([
        'entry_date' => '2024-03-01',
        'section_id' => $foreignSection->id,
    ]))->assertSessionHasErrors('section_id');

    $this->post(route('members.store'), memberPayload([
        'entry_date' => '2024-03-01',
        'section_id' => $ownSection->id,
        'subscription_id' => $foreignSubscription->id,
    ]))->assertSessionHasErrors('subscription_id');

    expect(Member::withoutGlobalScopes()->where('surname', 'Meier')->count())->toBe(0);
});

test('an installation-wide section is accepted for a new member', function () {
    // sections.club_id is nullable; those rows belong to every club.
    $shared = Section::factory()->create(['club_id' => null]);

    $this->actingAs(memberUser())
        ->post(route('members.store'), memberPayload([
            'entry_date' => '2024-03-01',
            'section_id' => $shared->id,
        ]))
        ->assertSessionHasNoErrors();
});

test('the bank details are only required of somebody paying by direct debit', function () {
    $section = Section::factory()->create(['club_id' => 1]);
    $entry = ['entry_date' => '2024-03-01', 'section_id' => $section->id];

    $this->actingAs(memberUser());

    $this->post(route('members.store'), memberPayload([
        ...$entry,
        'payment_method' => PaymentMethod::Account->value,
    ]))->assertSessionHasErrors(['bank', 'account_owner', 'iban', 'bic']);

    $this->post(route('members.store'), memberPayload([
        ...$entry,
        'payment_method' => PaymentMethod::Account->value,
        'bank' => 'Sparkasse',
        'account_owner' => 'Anna Meier',
        'iban' => 'DE89 3704 0044 0532 0130 01',
        'bic' => 'COBADEFFXXX',
    ]))->assertSessionHasErrors('iban');

    $this->post(route('members.store'), memberPayload([
        ...$entry,
        'payment_method' => PaymentMethod::Account->value,
        'bank' => 'Sparkasse',
        'account_owner' => 'Anna Meier',
        // Unspaced on the way in, stored grouped in fours.
        'iban' => 'DE89370400440532013000',
        'bic' => 'COBADEFFXXX',
    ]))->assertSessionHasNoErrors();

    expect(Member::query()->where('surname', 'Meier')->firstOrFail()->iban)
        ->toBe('DE89 3704 0044 0532 0130 00');
});

test('the dates have to make sense', function () {
    $section = Section::factory()->create(['club_id' => 1]);
    $entry = ['entry_date' => '2024-03-01', 'section_id' => $section->id];

    $this->actingAs(memberUser());

    $this->post(route('members.store'), memberPayload([...$entry, 'birthday' => now()->addYear()->format('Y-m-d')]))
        ->assertSessionHasErrors('birthday');

    $this->post(route('members.store'), memberPayload([
        ...$entry,
        'entry_date' => now()->addYear()->format('Y-m-d'),
    ]))->assertSessionHasErrors('entry_date');
});

test('a date of death is recorded on the edit form and nowhere else', function () {
    $section = Section::factory()->create(['club_id' => 1]);
    $member = joinedMember(['birthday' => '1980-05-04']);

    $this->actingAs(memberUser());

    // Nobody is entered into the club dead, so the create form does not offer
    // the field and the request does not accept it.
    $this->post(route('members.store'), memberPayload([
        'entry_date' => '2024-03-01',
        'section_id' => $section->id,
        'death_day' => '2024-01-01',
    ]))->assertSessionHasNoErrors();

    expect(Member::query()->where('surname', 'Meier')->firstOrFail()->death_day)->toBeNull();

    $this->get(route('members.create'))
        ->assertInertia(fn ($page) => $page->component('members/Create'));

    // On the edit form it is accepted, and the transposed pair that would
    // otherwise give a negative age is refused.
    $this->put(route('members.update', $member), memberPayload(['death_day' => '1975-01-01']))
        ->assertSessionHasErrors('death_day');

    $this->put(route('members.update', $member), memberPayload(['death_day' => now()->addYear()->format('Y-m-d')]))
        ->assertSessionHasErrors('death_day');

    $this->put(route('members.update', $member), memberPayload(['death_day' => '2024-01-01']))
        ->assertSessionHasNoErrors();

    expect($member->refresh()->death_day->format('Y-m-d'))->toBe('2024-01-01');
});

test('an admin edits a member and a non-admin does not', function () {
    $member = joinedMember(['surname' => 'Meier', 'first_name' => 'Anna']);

    $this->actingAs(memberUser(ClubRole::Advanced))
        ->get(route('members.edit', $member))
        ->assertForbidden();

    $this->actingAs(memberUser())
        ->get(route('members.edit', $member))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('members/Edit')
            ->where('member.surname', 'Meier')
            ->where('resignable', true)
            ->where('deletable', true)
        );

    $this->put(route('members.update', $member), memberPayload(['surname' => 'Huber']))
        ->assertRedirect();

    expect($member->refresh()->surname)->toBe('Huber');
});

test('the entry fields never reach the members table on an update', function () {
    $member = joinedMember();
    $section = Section::factory()->create(['club_id' => 1]);

    $this->actingAs(memberUser())
        ->put(route('members.update', $member), memberPayload([
            'entry_date' => '1999-01-01',
            'section_id' => $section->id,
            'club_id' => 999,
        ]))
        ->assertRedirect();

    // Neither the joining date nor a planted club_id is applied; the
    // membership is unchanged and the member stays in club 1.
    expect($member->refresh()->club_id)->toBe(1)
        ->and($member->memberships->first()->pivot->from->format('Y-m-d'))->toBe('2016-01-01')
        ->and($member->sections)->toHaveCount(0);
});

test('the detail page is readable by everybody but hides the finances', function () {
    $member = joinedMember(['surname' => 'Meier', 'first_name' => 'Anna']);
    $section = Section::factory()->create(['club_id' => 1, 'name' => 'Fussball']);
    $member->sections()->attach($section->id, ['from' => '2016-01-01', 'to' => null]);
    $member->subscriptions()->attach(Subscription::factory()->create(['club_id' => 1])->id);

    $this->actingAs(memberUser(ClubRole::Basic))
        ->get(route('members.show', $member))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('members/Show')
            ->where('member.full_name', 'Anna Meier')
            ->where('member.entry', '01.01.2016')
            ->has('member.sections', 1)
            ->where('member.sections.0.name', 'Fussball')
            ->where('modifiable', false)
            ->where('showsFinances', false)
        );

    $this->actingAs(memberUser())
        ->get(route('members.show', $member))
        ->assertInertia(fn ($page) => $page
            ->where('showsFinances', true)
            ->has('member.subscriptions', 1)
        );
});

test('ending a membership closes the memberships and sections but keeps the member', function () {
    $member = joinedMember();
    $open = Section::factory()->create(['club_id' => 1]);
    $closed = Section::factory()->create(['club_id' => 1]);
    $member->sections()->attach($open->id, ['from' => '2016-01-01', 'to' => null]);
    $member->sections()->attach($closed->id, ['from' => '2016-01-01', 'to' => '2018-01-01']);

    $this->actingAs(memberUser())
        // To the member page: the default selection is current members, so the
        // list would usually no longer contain them and reads as a failure.
        ->put(route('members.resign', $member), ['date' => '2024-06-30'])
        ->assertRedirect(route('members.show', $member));

    $member->refresh()->load(['memberships', 'sections']);

    expect($member->memberships->first()->pivot->to->format('Y-m-d'))->toBe('2024-06-30')
        ->and($member->sections->firstWhere('id', $open->id)->pivot->to->format('Y-m-d'))->toBe('2024-06-30')
        // An already closed section keeps the date it was closed on.
        ->and($member->sections->firstWhere('id', $closed->id)->pivot->to->format('Y-m-d'))->toBe('2018-01-01')
        ->and(Member::find($member->id))->not->toBeNull();
});

test('a membership cannot be ended before it started', function () {
    $member = joinedMember(from: '2016-01-01');
    $section = Section::factory()->create(['club_id' => 1]);
    // A section that started later than the membership: the floor is the
    // latest open start, not the joining date.
    $member->sections()->attach($section->id, ['from' => '2020-03-01', 'to' => null]);

    $this->actingAs(memberUser());

    foreach (['2015-12-31', '2016-01-01', '2020-03-01'] as $tooEarly) {
        $this->put(route('members.resign', $member), ['date' => $tooEarly])
            ->assertSessionHasErrors('date');
    }

    // One day after the latest open start is the earliest that works.
    $this->put(route('members.resign', $member), ['date' => '2020-03-02'])
        ->assertRedirect(route('members.show', $member));

    $member->refresh()->load(['memberships', 'sections']);

    expect($member->memberships->first()->pivot->to->format('Y-m-d'))->toBe('2020-03-02')
        ->and($member->sections->first()->pivot->to->format('Y-m-d'))->toBe('2020-03-02');
});

test('a member who rejoined is measured against the open period, not the first one', function () {
    $member = Member::factory()->ofClub(1)->create();
    $member->memberships()->attach(1, ['from' => '2000-01-01', 'to' => '2004-12-31']);
    $member->memberships()->attach(1, ['from' => '2010-01-01', 'to' => null]);

    $this->actingAs(memberUser());

    // After entry() (2000) but inside the closed gap — it would write a `to`
    // before the open row's own `from`.
    $this->put(route('members.resign', $member), ['date' => '2005-01-01'])
        ->assertSessionHasErrors('date');

    $this->put(route('members.resign', $member), ['date' => '2024-06-30'])
        ->assertRedirect(route('members.show', $member));

    $periods = $member->fresh()->load('memberships')->memberships
        ->map(fn ($club) => $club->pivot->to->format('Y-m-d'))
        ->sort()
        ->values()
        ->all();

    // The already closed period keeps its own end date.
    expect($periods)->toBe(['2004-12-31', '2024-06-30']);
});

test('the edit page ships the floor the picker needs', function () {
    $member = joinedMember(from: '2016-01-01');

    $this->actingAs(memberUser())
        ->get(route('members.edit', $member))
        ->assertInertia(fn ($page) => $page
            ->where('earliestResignation', '2016-01-02')
        );
});

test('the member page is reachable right after resigning, though the list is not', function () {
    $member = joinedMember();

    $this->actingAs(memberUser())
        ->put(route('members.resign', $member), ['date' => '2024-06-30'])
        ->assertRedirect(route('members.show', $member));

    // The page still renders them, with the membership now closed.
    $this->get(route('members.show', $member))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('member.is_member', false)
            ->where('member.memberships.0.range', '01.01.2016-30.06.2024')
        );

    // Whereas the default selection has dropped them, which is why the
    // redirect does not go there.
    $this->get(route('members.index'))
        ->assertInertia(fn ($page) => $page->has('members.data', 0));
});

test('somebody without an open membership is not offered the resign button', function () {
    $member = Member::factory()->ofClub(1)->create();
    $member->memberships()->attach(1, ['from' => '2010-01-01', 'to' => '2015-01-01']);

    $this->actingAs(memberUser())
        ->get(route('members.edit', $member))
        ->assertInertia(fn ($page) => $page->where('resignable', false));
});

test('a non-admin may neither resign nor delete a member', function () {
    $member = joinedMember();

    $this->actingAs(memberUser(ClubRole::Advanced));

    $this->put(route('members.resign', $member), ['date' => '2024-06-30'])->assertForbidden();
    $this->delete(route('members.destroy', $member))->assertForbidden();
});

test('a member with anything recorded against them cannot be deleted', function () {
    $member = joinedMember();
    $section = Section::factory()->create(['club_id' => 1]);
    $member->sections()->attach($section->id, ['from' => '2016-01-01']);

    $this->actingAs(memberUser());

    // Every member_id column is ON DELETE CASCADE, so the database would take
    // the whole history without complaint. MemberPolicy is the only brake.
    $this->delete(route('members.destroy', $member))->assertForbidden();

    expect(Member::find($member->id))->not->toBeNull();

    $this->get(route('members.edit', $member))
        ->assertInertia(fn ($page) => $page->where('deletable', false));
});

test('the club membership alone does not block a deletion', function () {
    // Every member has one by construction, so counting it would make nobody
    // deletable ever.
    $member = joinedMember();

    $this->actingAs(memberUser())
        ->get(route('members.edit', $member))
        ->assertInertia(fn ($page) => $page->where('deletable', true));

    $this->delete(route('members.destroy', $member))->assertRedirect();

    expect(Member::find($member->id))->toBeNull()
        ->and(DB::table('club_member')->where('member_id', $member->id)->count())->toBe(0);
});

test('every other reference blocks a deletion, one at a time', function () {
    $this->actingAs(memberUser());

    $cases = [
        'section' => fn (Member $m) => $m->sections()->attach(
            Section::factory()->create(['club_id' => 1])->id, ['from' => '2016-01-01']
        ),
        'role' => fn (Member $m) => $m->roles()->attach(
            Role::factory()->create(['club_id' => 1])->id, ['from' => '2016-01-01']
        ),
        'honour' => fn (Member $m) => $m->events()->attach(
            Event::factory()->create(['club_id' => 1])->id, ['date' => '2016-01-01']
        ),
        'subscription' => fn (Member $m) => $m->subscriptions()->attach(
            Subscription::factory()->create(['club_id' => 1])->id
        ),
        'issued item' => fn (Member $m) => $m->items()->attach(
            Item::factory()->create(['club_id' => 1])->id, ['from' => '2016-01-01']
        ),
        // debits.member_id cascades too, and a pending collection is exactly
        // the kind of thing that must not vanish silently.
        'debit' => fn (Member $m) => Debit::factory()->create(['member_id' => $m->id]),
    ];

    foreach ($cases as $label => $attach) {
        $member = joinedMember();
        $attach($member);

        expect($member->isUsed())->toBeTrue("a {$label} should block the deletion");

        $this->delete(route('members.destroy', $member))->assertForbidden();
    }
});

test('stripping the relations makes a member deletable again', function () {
    $member = joinedMember();
    $section = Section::factory()->create(['club_id' => 1]);
    $member->sections()->attach($section->id, ['from' => '2016-01-01']);

    $this->actingAs(memberUser());

    $this->delete(route('members.destroy', $member))->assertForbidden();

    // The way out is the member page, which makes the loss deliberate rather
    // than a side effect of pressing Delete.
    $row = $member->sections()->first()->pivot;
    $this->delete(route('members.sections.destroy', [$member, $row->id]))->assertRedirect();

    $this->delete(route('members.destroy', $member))->assertRedirect();

    expect(Member::find($member->id))->toBeNull();
});

test('a member of another club cannot be reached by guessing their id', function () {
    $foreign = Member::factory()->create();

    $this->actingAs(memberUser());

    $this->get(route('members.show', $foreign))->assertNotFound();
    $this->get(route('members.edit', $foreign))->assertNotFound();
    $this->delete(route('members.destroy', $foreign))->assertNotFound();
});

test('the sidebar carries the member list for everybody in the club', function () {
    $this->actingAs(memberUser(ClubRole::Basic))
        ->get(route('members.index'))
        ->assertOk();
});
