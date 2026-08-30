<?php

use App\Enums\ActionType;
use App\Enums\ClubRole;
use App\Enums\Gender;
use App\Enums\PaymentMethod;
use App\Models\Club;
use App\Models\ClubUser;
use App\Models\Event;
use App\Models\Item;
use App\Models\Member;
use App\Models\Role;
use App\Models\Section;
use App\Models\Subscription;
use App\Models\Tracing;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

/**
 * currentClubId() resolves to 1 on the CLI, so every scoped model is read as
 * though the user were working in club 1.
 */
beforeEach(function () {
    $this->club = Club::factory()->create(['id' => 1]);
    Member::setKeyDate(null);
});

afterEach(fn () => Member::setKeyDate(null));

it('casts member attributes', function () {
    $member = Member::factory()->ofClub($this->club)->create([
        'gender' => 'm',
        'birthday' => '1990-06-15',
    ]);

    expect($member->gender)->toBe(Gender::Mann)
        ->and($member->birthday)->toBeInstanceOf(CarbonInterface::class)
        ->and($member->fullName())->toBe($member->first_name.' '.$member->surname);
});

it('computes age against the key date', function () {
    $member = Member::factory()->ofClub($this->club)->create(['birthday' => '1990-01-01']);

    Member::setKeyDate(Carbon\Carbon::parse('2020-06-01'));

    expect($member->age)->toBe(30);
});

it('uses the death day as the key date once a member has died', function () {
    $member = Member::factory()->ofClub($this->club)->create([
        'birthday' => '1950-01-01',
        'death_day' => '2000-01-01',
    ]);

    Member::setKeyDate(Carbon\Carbon::parse('2020-06-01'));

    expect($member->gone())->toBeTrue()
        ->and($member->alive())->toBeFalse()
        ->and($member->age)->toBe(50);
});

it('scopes members to the current club', function () {
    $other = Club::factory()->create();
    Member::factory()->ofClub($this->club)->create();
    Member::factory()->ofClub($other)->create();

    expect(Member::count())->toBe(1)
        ->and(Member::withoutGlobalScopes()->count())->toBe(2);
});

it('keeps every club scoped model to the club being worked in', function () {
    $other = Club::factory()->create();

    Role::factory()->create(['club_id' => $this->club->id, 'name' => 'Mine']);
    Role::factory()->create(['club_id' => $other->id, 'name' => 'Theirs']);

    // Roles, events and sections let `club_id IS NULL` rows through as well
    // until 2026-08-30; all three columns are NOT NULL now and one plain
    // ClubScope answers for every table.
    $names = Role::whereIn('name', ['Mine', 'Theirs'])->pluck('name')->all();

    expect($names)->toBe(['Mine']);
});

it('keeps the club scope from leaking past other conditions', function () {
    $other = Club::factory()->create();
    Role::factory()->create(['club_id' => $other->id, 'name' => 'Duplicate']);
    Role::factory()->create(['club_id' => $this->club->id, 'name' => 'Duplicate']);

    expect(Role::where('name', 'Duplicate')->count())->toBe(1);
});

it('resolves membership through the club_member pivot', function () {
    $member = Member::factory()->ofClub($this->club)->create(['birthday' => '1980-01-01']);
    $member->memberships()->attach($this->club->id, ['from' => '2000-01-01', 'to' => null]);

    Member::setKeyDate(Carbon\Carbon::parse('2020-06-01'));
    $member->refresh();

    expect($member->isMember())->toBeTrue()
        ->and($member->entry()->toDateString())->toBe('2000-01-01')
        ->and($member->membershipYears())->toBe(20)
        ->and(Member::members()->pluck('id')->all())->toBe([$member->id]);
});

it('copies the key date in and out, so a caller cannot move it by accident', function () {
    $date = Carbon\Carbon::parse('2020-06-01');
    Member::setKeyDate($date);

    // Carbon is mutable and Club::buildBlsvStatistic() keeps using the very
    // instance it sets, so the setter has to copy — otherwise the caller's
    // own arithmetic would move what the whole app reads.
    $date->addYear();

    expect(Member::getKeyDate()->toDateString())->toBe('2020-06-01');

    Member::getKeyDate()->addYear();

    expect(Member::getKeyDate()->toDateString())->toBe('2020-06-01');

    // Null puts it back to today, which is how every test resets it.
    Member::setKeyDate(null);

    expect(Member::getKeyDate()->toDateString())->toBe(now()->toDateString());
});

it('reads the membership period that started last, open or closed', function () {
    $rejoined = Member::factory()->ofClub($this->club)->create();
    $rejoined->memberships()->attach($this->club->id, ['from' => '2000-01-01', 'to' => '2010-12-31']);
    $rejoined->memberships()->attach($this->club->id, ['from' => '2015-01-01', 'to' => null]);

    $left = Member::factory()->ofClub($this->club)->create();
    $left->memberships()->attach($this->club->id, ['from' => '2000-01-01', 'to' => '2010-12-31']);

    // Ordered by `from`: an open period has no `to` to sort on, and it is the
    // one the duplicate warning has to report.
    $never = Member::factory()->ofClub($this->club)->create();

    expect($rejoined->latestMembership()->from->toDateString())->toBe('2015-01-01')
        ->and($rejoined->latestMembership()->to)->toBeNull()
        ->and($left->latestMembership()->to->toDateString())->toBe('2010-12-31')
        // A record entered but never joined up has none at all.
        ->and($never->latestMembership())->toBeNull()
        // latestMembershipEnd() reads that row's `to`, which only holds while
        // periods do not overlap — the floor MemberRejoinRequest enforces.
        ->and($rejoined->latestMembershipEnd())->toBeNull()
        ->and($left->latestMembershipEnd()->toDateString())->toBe('2010-12-31')
        ->and($never->latestMembershipEnd())->toBeNull();
});

it('excludes members who left before the key date', function () {
    $member = Member::factory()->ofClub($this->club)->create(['birthday' => '1980-01-01']);
    $member->memberships()->attach($this->club->id, ['from' => '2000-01-01', 'to' => '2010-01-01']);

    Member::setKeyDate(Carbon\Carbon::parse('2020-06-01'));

    expect(Member::members()->count())->toBe(0)
        ->and(Member::noMembers()->count())->toBe(1);
});

it('formats a pivot range', function () {
    $member = Member::factory()->ofClub($this->club)->create();
    $member->memberships()->attach($this->club->id, ['from' => '2000-03-01', 'to' => '2010-07-01']);

    expect($member->memberships->first()->pivot->range())->toBe('03.2000-07.2010');
});

it('relates members to sections, roles, events and items', function () {
    $member = Member::factory()->ofClub($this->club)->create();
    $section = Section::factory()->create(['club_id' => $this->club->id]);
    $role = Role::factory()->create(['club_id' => $this->club->id]);
    $event = Event::factory()->create(['club_id' => $this->club->id]);
    $item = Item::factory()->create(['club_id' => $this->club->id]);

    $member->sections()->attach($section->id, ['from' => '2020-01-01']);
    $member->roles()->attach($role->id, ['from' => '2020-01-01']);
    $member->events()->attach($event->id, ['date' => '2021-05-01']);
    $member->items()->attach($item->id, ['from' => '2020-01-01']);

    Member::setKeyDate(Carbon\Carbon::parse('2022-01-01'));
    $member->refresh();

    expect($member->currentSections())->toBe($section->name)
        ->and($member->currentRoles())->toBe($role->name)
        ->and($member->latestHonorName())->toBe($event->name)
        ->and($section->isUsed())->toBeTrue()
        ->and($role->isUsed())->toBeTrue()
        ->and($event->isUsed())->toBeTrue()
        ->and($item->isUsed())->toBeTrue();
});

it('reports unused lookup rows as unused', function () {
    expect(Role::factory()->create(['club_id' => $this->club->id])->isUsed())->toBeFalse();
});

it('finds members in a section', function () {
    $section = Section::factory()->create(['club_id' => $this->club->id]);
    $inSection = Member::factory()->ofClub($this->club)->create();
    Member::factory()->ofClub($this->club)->create();

    $inSection->sections()->attach($section->id, ['from' => '2020-01-01']);

    Member::setKeyDate(Carbon\Carbon::parse('2022-01-01'));

    expect(Member::inSections($section->id)->pluck('id')->all())->toBe([$inSection->id]);
});

it('filters members by age range', function () {
    Member::setKeyDate(Carbon\Carbon::parse('2020-06-01'));
    $child = Member::factory()->ofClub($this->club)->create(['birthday' => '2012-01-01']);
    $adult = Member::factory()->ofClub($this->club)->create(['birthday' => '1980-01-01']);

    expect(Member::ageRange(null, 13)->pluck('id')->all())->toBe([$child->id])
        ->and(Member::ageRange(18, null)->pluck('id')->all())->toBe([$adult->id]);
});

it('renders a subscription as a string', function () {
    $subscription = Subscription::factory()->create([
        'club_id' => $this->club->id,
        'name' => 'Jahresbeitrag',
        'amount' => 42.5,
    ]);

    expect((string) $subscription)->toBe('Jahresbeitrag (42,50 €)');
});

it('reads the club role through the club_user pivot', function () {
    $user = User::factory()->create(['club_id' => $this->club->id]);
    ClubUser::factory()->create([
        'club_id' => $this->club->id,
        'user_id' => $user->id,
        'role' => ClubRole::Admin->value,
    ]);

    expect($user->clubRole())->toBe(ClubRole::Admin->value)
        ->and($user->hasAdminRights())->toBeTrue()
        ->and($user->hasAdvancedRights())->toBeTrue()
        ->and($user->hasClubRole(ClubRole::Admin))->toBeTrue();
});

it('returns -1 for a user with no membership in the club', function () {
    expect(User::factory()->create()->clubRole())->toBe(-1);
});

it('tracks the last login through tracings', function () {
    $user = User::factory()->create(['club_id' => $this->club->id]);
    Tracing::factory()->create([
        'user_id' => $user->id,
        'action_type' => ActionType::Login,
        'at' => '2024-01-01 10:00:00',
    ]);

    expect($user->lastLogin()->toDateTimeString())->toBe('2024-01-01 10:00:00')
        ->and(User::withLastLoginAt()->find($user->id)->last_login_at->toDateString())->toBe('2024-01-01');
});

it('switches a user to a club they belong to', function () {
    $other = Club::factory()->create();
    $user = User::factory()->create(['club_id' => $this->club->id]);
    $user->clubs()->attach($other->id, ['role' => ClubRole::Basic->value]);

    expect($user->switchClub($other->id))->toBeTrue()
        ->and($user->fresh()->club_id)->toBe($other->id)
        ->and($user->switchClub(9999))->toBeFalse();
});

it('exposes the helper functions', function () {
    expect(currentClubId())->toBe(1)
        ->and(currentClub()->is($this->club))->toBeTrue()
        ->and(formatDate('2020-03-01'))->toBe('01.03.2020')
        ->and(getRange('2020-03-01', null, 'm.Y'))->toBe('03.2020-')
        ->and(getRange('2020-03-01', '2021-04-01', 'm.Y'))->toBe('03.2020-04.2021')
        ->and(checkIban('DE89 3704 0044 0532 0130 00'))->toBeTrue()
        ->and(checkIban('DE89 3704 0044 0532 0130 01'))->toBeFalse()
        ->and(normalizeIban('de89370400440532013000'))->toBe('DE89 3704 0044 0532 0130 00');
});

/**
 * dueHonor() uses MySQL's YEAR()/LEAST(), which SQLite cannot execute. The driver
 * still reports the prepared statement, so the exception is the way to inspect it.
 *
 * @return array{sql: string, bindings: array<int, mixed>}
 */
function captureDueHonorQuery(): array
{
    try {
        Member::query()->dueHonor()->get();
    } catch (QueryException $e) {
        return ['sql' => $e->getSql(), 'bindings' => $e->getBindings()];
    }

    throw new RuntimeException('Expected SQLite to reject the MySQL-only YEAR() call.');
}

it('binds the honour years instead of interpolating them', function () {
    $this->club->update(['honor_years' => '25,40']);
    Member::setKeyDate(Carbon\Carbon::parse('2024-06-01'));

    $captured = captureDueHonorQuery();

    expect($captured['sql'])->toContain('FIND_IN_SET')
        ->and($captured['bindings'])->toContain('25,40');
});

it('neutralises a malformed honor_years value', function () {
    // honor_years is club-editable free text and used to be interpolated into the SQL
    $this->club->update(['honor_years' => '25) OR 1=1 --']);
    Member::setKeyDate(Carbon\Carbon::parse('2024-06-01'));

    $captured = captureDueHonorQuery();

    expect($captured['sql'])->not->toContain('OR 1=1')
        ->and($captured['bindings'])->toContain('25');
});

it('ignores an empty honor_years value', function () {
    $this->club->update(['honor_years' => null]);
    Member::factory()->ofClub($this->club)->create();

    expect(Member::query()->dueHonor()->count())->toBe(1);
});

it('allowlists mass assignment instead of leaving models unguarded', function () {
    $member = new Member;

    expect($member->isFillable('surname'))->toBeTrue()
        ->and($member->isFillable('club_id'))->toBeTrue()
        ->and($member->isFillable('id'))->toBeFalse()
        ->and($member->isFillable('created_at'))->toBeFalse()
        ->and($member->isFillable('not_a_column'))->toBeFalse();

    // an injected id is dropped rather than silently applied
    $member->fill(['id' => 999, 'surname' => 'Muster']);

    expect($member->id)->toBeNull()
        ->and($member->surname)->toBe('Muster');
});

it('keeps the framework-managed user columns out of mass assignment', function () {
    $user = new User;

    expect($user->isFillable('password'))->toBeTrue()
        ->and($user->isFillable('club_id'))->toBeTrue()
        ->and($user->isFillable('remember_token'))->toBeFalse()
        ->and($user->getHidden())->toBe(['password', 'remember_token']);
});

it('still lets the framework write the remember token', function () {
    $user = User::factory()->create(['club_id' => $this->club->id]);

    $user->setRememberToken('abc123');
    $user->save();

    expect($user->fresh()->getRememberToken())->toBe('abc123');
});

it('exposes attribute scopes without the scope prefix', function () {
    $member = Member::factory()->ofClub($this->club)->payingByAccount()->create();
    Member::factory()->ofClub($this->club)->create();

    // The scope takes PaymentMethod cases, never a raw 'k'. There is no column
    // behind it since 2026-08-28 — the bank details are what it reads.
    expect(Member::paymentMethods(PaymentMethod::Account)->pluck('id')->all())->toBe([$member->id])
        // resolved through __callStatic and on an existing builder
        ->and(Member::query()->paymentMethods(PaymentMethod::Account)->count())->toBe(1)
        ->and($member->payment_method)->toBe(PaymentMethod::Account);
});

it('derives the payment method from the bank details, with no column behind it', function () {
    $paying = Member::factory()->ofClub($this->club)->payingByAccount()->create();
    $billed = Member::factory()->ofClub($this->club)->create();

    expect($paying->payment_method)->toBe(PaymentMethod::Account)
        ->and($billed->payment_method)->toBe(PaymentMethod::Invoice)
        // An empty string counts as no account, the same way hasAccount() reads
        // it — production carried both null and '' in that column.
        ->and($billed->fill(['iban' => ''])->payment_method)->toBe(PaymentMethod::Invoice)
        ->and(Schema::hasColumn('members', 'payment_method'))->toBeFalse();

    // Both cases together restrict nothing rather than colliding.
    expect(Member::query()->paymentMethods(PaymentMethod::cases())->count())->toBe(2)
        ->and(Member::query()->paymentMethods(PaymentMethod::Invoice)->pluck('id')->all())
        ->toBe([$billed->id]);

    // Writing it is impossible: not fillable, and there is nothing to write to.
    $billed->fill(['payment_method' => 'k']);
    expect($billed->getAttributes())->not->toHaveKey('payment_method');
});

it('keeps the scope methods off the public surface', function () {
    // protected, so a static call routes through __callStatic instead of
    // invoking the instance method statically
    $reflection = new ReflectionMethod(Member::class, 'members');

    expect($reflection->isProtected())->toBeTrue()
        ->and($reflection->getAttributes(Scope::class))->not->toBeEmpty();
});

it('separates the dueHonor scope from the honorYearReached accessor', function () {
    $this->club->update(['honor_years' => '25,40']);
    $member = Member::factory()->ofClub($this->club)->create();
    $member->memberships()->attach($this->club->id, ['from' => '1999-01-01', 'to' => null]);

    Member::setKeyDate(Carbon\Carbon::parse('2024-06-01'));

    // the scope of the same name is exercised in the captureDueHonorQuery tests
    expect($member->honorYearReached())->toBe(25);
});
