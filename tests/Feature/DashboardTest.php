<?php

use App\Enums\ActionType;
use App\Enums\ClubRole;
use App\Enums\Gender;
use App\Models\Club;
use App\Models\Member;
use App\Models\Section;
use App\Models\Subscription;
use App\Models\Tracing;
use App\Models\User;

/**
 * currentClubId() resolves to 1 on the CLI, so every request is read as though
 * the acting user were working in club 1.
 */
beforeEach(function () {
    $this->club = Club::factory()->create(['id' => 1, 'honor_years' => '25,40']);
    Member::setKeyDate(null);
});

afterEach(fn () => Member::setKeyDate(null));

function dashboardUser(ClubRole $role = ClubRole::Admin): User
{
    $user = User::factory()->create(['club_id' => 1]);
    $user->clubs()->attach(1, ['role' => $role->value]);

    return $user;
}

/**
 * A member of club 1 with an open membership, so the `members` selection —
 * which everything on this screen is built on — actually returns them.
 */
function dashboardMember(array $attributes = [], string $from = '2016-01-01', ?string $to = null): Member
{
    $member = Member::factory()->ofClub(1)->create($attributes);
    $member->memberships()->attach(1, ['from' => $from, 'to' => $to]);

    return $member;
}

test('guests are redirected to the login page', function () {
    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('the dashboard counts the current members and their average age', function () {
    dashboardMember(['birthday' => now()->subYears(30)->toDateString()]);
    dashboardMember(['birthday' => now()->subYears(41)->toDateString()]);
    // Left last year, so current in neither the count nor the average.
    dashboardMember(['birthday' => now()->subYears(80)->toDateString()], '2000-01-01', now()->subYear()->toDateString());

    $this->actingAs(dashboardUser(ClubRole::Basic))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('year', now()->year)
            ->where('summary.members', 2)
            ->where('summary.former', 1)
            ->where('summary.average_age', 35.5)
        );
});

test('an empty club reports no average age instead of zero', function () {
    $this->actingAs(dashboardUser())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.members', 0)
            ->where('summary.average_age', null)
        );
});

test('the age structure splits the seven BLSV brackets by gender', function () {
    dashboardMember(['birthday' => now()->subYears(3)->toDateString(), 'gender' => Gender::Mann]);
    dashboardMember(['birthday' => now()->subYears(30)->toDateString(), 'gender' => Gender::Frau]);
    dashboardMember(['birthday' => now()->subYears(35)->toDateString(), 'gender' => Gender::Mann]);

    $this->actingAs(dashboardUser())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('ageStructure', 7)
            ->where('ageStructure.0.filter', 'age_0-5')
            ->where('ageStructure.0.male', 1)
            ->where('ageStructure.0.total', 1)
            ->where('ageStructure.4.filter', 'age_27-40')
            ->where('ageStructure.4.male', 1)
            ->where('ageStructure.4.female', 1)
            ->where('ageStructure.4.total', 2)
            ->where('ageStructure.6.total', 0)
        );
});

test('every age bracket is a member selection listing exactly the members it counted', function () {
    dashboardMember(['birthday' => now()->subYears(30)->toDateString()]);
    dashboardMember(['birthday' => now()->subYears(70)->toDateString()]);

    $this->actingAs(dashboardUser())
        ->get(route('members.index', ['filter' => 'age_27-40']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('members/Index')
            ->has('members.data', 1)
            ->where('members.data.0.age', 30)
        );

    $this->get(route('members.index', ['filter' => 'age_61+']))
        ->assertInertia(fn ($page) => $page
            ->has('members.data', 1)
            ->where('members.data.0.age', 70)
        );
});

test('an unknown age bracket falls back to the default selection', function () {
    dashboardMember();

    $this->actingAs(dashboardUser())
        ->get(route('members.index', ['filter' => 'age_nonsense']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('members.data', 1));
});

test('the age brackets are offered in the filter dropdown', function () {
    $this->actingAs(dashboardUser())
        ->get(route('members.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filterOptions', fn ($options) => collect($options)
                ->pluck('id')
                ->intersect(['age_0-5', 'age_18-26', 'age_61+'])
                ->count() === 3)
        );
});

test('arrivals and departures are counted per year, without the MySQL-only scopes', function () {
    dashboardMember([], now()->startOfYear()->toDateString());
    dashboardMember([], '2016-01-01', now()->startOfYear()->addMonth()->toDateString());

    $this->actingAs(dashboardUser())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.joined', 1)
            ->where('summary.left', 1)
            ->has('development', 10)
            ->where('development.9.year', now()->year)
            ->where('development.9.joined', 1)
            ->where('development.9.left', 1)
            ->where('development.9.members', 1)
            ->where('development.0.year', now()->year - 9)
        );
});

test('honours due counts the members whose years reach one of the club honour years', function () {
    // 25 years in as of this year, which is one of the club's honour years.
    dashboardMember([], now()->subYears(25)->startOfYear()->toDateString());
    dashboardMember([], now()->subYears(7)->startOfYear()->toDateString());

    $this->actingAs(dashboardUser())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('summary.due_honours', 1));
});

test('the membership year bands cover every current member exactly once', function () {
    dashboardMember([], now()->subYears(2)->startOfYear()->toDateString());
    dashboardMember([], now()->subYears(25)->startOfYear()->toDateString());
    dashboardMember([], now()->subYears(60)->startOfYear()->toDateString());

    $this->actingAs(dashboardUser())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('membershipYears', 7)
            ->where('membershipYears.0.label', '0–4')
            ->where('membershipYears.0.count', 1)
            ->where('membershipYears.3.label', '20–29')
            ->where('membershipYears.3.count', 1)
            ->where('membershipYears.6.label', '50+')
            ->where('membershipYears.6.count', 1)
        );
});

test('sections are listed with their current members, most first', function () {
    $tennis = Section::factory()->create(['club_id' => 1, 'name' => 'Tennis']);
    $chess = Section::factory()->create(['club_id' => 1, 'name' => 'Schach']);

    $first = dashboardMember();
    $second = dashboardMember();

    $first->sections()->attach($tennis->id, ['from' => '2016-01-01']);
    $second->sections()->attach($tennis->id, ['from' => '2016-01-01']);
    $first->sections()->attach($chess->id, ['from' => '2016-01-01']);

    $this->actingAs(dashboardUser())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('sections', 2)
            ->where('sections.0.label', 'Tennis')
            ->where('sections.0.count', 2)
            ->where('sections.0.filter', "section_{$tennis->id}")
            ->where('sections.1.label', 'Schach')
            ->where('sections.1.count', 1)
            // The roles distribution was dropped on 2026-08-27, deliberately.
            ->missing('roles')
        );
});

test('a section count is exactly what its selection lists', function () {
    $tennis = Section::factory()->create(['club_id' => 1, 'name' => 'Tennis']);
    dashboardMember()->sections()->attach($tennis->id, ['from' => '2016-01-01']);
    // Left the section again, so counted by neither.
    dashboardMember()->sections()->attach($tennis->id, ['from' => '2016-01-01', 'to' => '2018-01-01']);

    $this->actingAs(dashboardUser())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('sections.0.count', 1));

    $this->get(route('members.index', ['filter' => "section_{$tennis->id}"]))
        ->assertInertia(fn ($page) => $page->has('members.data', 1));
});

test('the subscription card is admin-only and ends with the members paying nothing', function () {
    $fee = Subscription::factory()->create(['club_id' => 1, 'name' => 'Erwachsene']);
    dashboardMember()->subscriptions()->attach($fee->id);
    dashboardMember();

    $this->actingAs(dashboardUser(ClubRole::Admin))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('subscriptions', 2)
            ->where('subscriptions.0.label', 'Erwachsene')
            ->where('subscriptions.0.count', 1)
            ->where('subscriptions.1.filter', 'no_subscription')
            ->where('subscriptions.1.count', 1)
        );

    $this->actingAs(dashboardUser(ClubRole::Basic))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('subscriptions', null));
});

test('the login card is root-only', function () {
    // Not merely administrative: the tracings span every club, so a club admin
    // would be reading who signs in elsewhere.
    $this->actingAs(dashboardUser(ClubRole::Admin))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('logins', null));

    $this->actingAs(User::factory()->create(['club_id' => 1, 'admin' => true]))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('logins'));
});

test('the login card counts twelve months per user, newest month last', function () {
    $root = User::factory()->create(['club_id' => 1, 'admin' => true, 'name' => 'Root']);
    $quiet = User::factory()->create(['club_id' => 1, 'name' => 'Leise']);
    User::factory()->create(['club_id' => 1, 'name' => 'Nie']);

    Tracing::factory()->count(3)->create(['user_id' => $root->id, 'at' => now()]);
    // startOfMonth() first, as the card itself does: subMonths() off the 31st
    // overflows (31 August less 11 months is 1 October, not September), which
    // would drop this login into the second bar instead of the first.
    Tracing::factory()->create(['user_id' => $root->id, 'at' => now()->startOfMonth()->subMonths(11)]);
    Tracing::factory()->create(['user_id' => $quiet->id, 'at' => now()]);

    // Just outside the window, and an action that is not a login: neither counts.
    Tracing::factory()->create(['user_id' => $root->id, 'at' => now()->startOfMonth()->subMonths(12)]);
    Tracing::factory()->create(['user_id' => $root->id, 'at' => now(), 'action_type' => ActionType::Update]);

    $this->actingAs($root)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('logins.total', 5)
            ->has('logins.months', 12)
            ->has('logins.users', 2)
            // Most active first.
            ->where('logins.users.0.name', 'Root')
            ->where('logins.users.0.count', 4)
            ->where('logins.users.0.months.0', 1)
            ->where('logins.users.0.months.11', 3)
            ->where('logins.users.1.name', 'Leise')
            // "Nie" never signed in: counted, not listed.
            ->where('logins.dormant', 1)
        );
});

test('another club is never counted in', function () {
    $other = Club::factory()->create();
    $foreign = Member::factory()->ofClub($other->id)->create();
    $foreign->memberships()->attach($other->id, ['from' => '2016-01-01']);

    dashboardMember();

    $this->actingAs(dashboardUser())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('summary.members', 1)
            ->where('development.9.members', 1)
            ->where('development.9.joined', 0)
        );
});
