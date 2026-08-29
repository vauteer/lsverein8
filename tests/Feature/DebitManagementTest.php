<?php

use App\Enums\ClubRole;
use App\Models\Club;
use App\Models\Debit;
use App\Models\Member;
use App\Models\User;

/**
 * currentClubId() resolves to 1 on the CLI, so every request is read as though
 * the acting user were working in club 1.
 */
beforeEach(function () {
    $this->club = Club::factory()->create(['id' => 1, 'sepa_mandate_date' => '2015-01-01']);
    Member::$_keyDate = null;
});

afterEach(fn () => Member::$_keyDate = null);

/**
 * Create a user belonging to the given club (defaulting to club 1) with the
 * given role in it.
 */
function debitUser(ClubRole $role = ClubRole::Admin, ?Club $club = null, array $attributes = []): User
{
    $club ??= Club::find(1) ?? Club::factory()->create(['id' => 1]);

    $user = User::factory()->create([...$attributes, 'club_id' => $club->id]);
    $user->clubs()->attach($club->id, ['role' => $role->value]);

    return $user;
}

/**
 * A member of the given club who can actually be debited: a bank account on
 * file and a membership, so the SEPA generator finds an entry date.
 */
function debitableMemberOf(Club|int $club = 1, array $attributes = []): Member
{
    $clubId = $club instanceof Club ? $club->id : $club;

    $member = Member::factory()->ofClub($clubId)->payingByAccount()->create($attributes);
    $member->memberships()->attach($clubId, ['from' => '2016-01-01', 'to' => null]);

    return $member;
}

/**
 * A valid payload; individual tests override the field they are exercising.
 *
 * @return array<string, mixed>
 */
function debitPayload(Member $member, array $overrides = []): array
{
    return [
        'member_id' => $member->id,
        'amount' => 42.5,
        'transfer_text' => 'Nachzahlung <AJ> <VN> <NN>',
        'due_at' => '2026-09-03',
        ...$overrides,
    ];
}

test('guests are redirected to the login page', function () {
    $this->get(route('debits.index'))->assertRedirect(route('login'));
});

test('the whole screen is admin only, unlike the fee list', function () {
    $member = debitableMemberOf();
    $debit = Debit::factory()->create(['member_id' => $member->id]);

    $this->actingAs(debitUser(ClubRole::Advanced));

    $this->get(route('debits.index'))->assertForbidden();
    $this->get(route('debits.create'))->assertForbidden();
    $this->post(route('debits.store'), debitPayload($member))->assertForbidden();
    $this->get(route('debits.edit', $debit))->assertForbidden();
    $this->put(route('debits.update', $debit), debitPayload($member))->assertForbidden();
    $this->delete(route('debits.destroy', $debit))->assertForbidden();
    $this->post(route('debits.collect'), ['date' => '2026-09-03'])->assertForbidden();
});

test('the sidebar entry follows the same policy', function () {
    $this->actingAs(debitUser())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.canManageDebits', true));

    $this->actingAs(debitUser(ClubRole::Advanced))
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.canManageDebits', false));
});

test('the index lists the club debits but no other club', function () {
    $own = Debit::factory()->create([
        'member_id' => debitableMemberOf(attributes: ['surname' => 'Eigen'])->id,
        'amount' => 12.5,
        'due_at' => '2026-09-03',
    ]);

    // `debits` has no club_id of its own; the club comes from the member, and
    // the factory's default member belongs to a club of its own.
    $foreign = Debit::factory()->create();

    $this->actingAs(debitUser())
        ->get(route('debits.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('debits/Index')
            ->has('debits.data', 1)
            ->where('debits.data.0.id', $own->id)
            ->where('debits.data.0.member_name', fn ($name) => str_contains($name, 'Eigen'))
            ->where('debits.data.0.amount_label', '12,50 €')
            ->where('debits.data.0.due_at_label', '03.09.2026')
            ->whereNot('debits.data.0.id', $foreign->id)
        );
});

test('the index can be searched by transfer text and by member name', function () {
    $meier = debitableMemberOf(attributes: ['surname' => 'Meier', 'first_name' => 'Anna']);
    $huber = debitableMemberOf(attributes: ['surname' => 'Huber', 'first_name' => 'Bert']);

    Debit::factory()->create(['member_id' => $meier->id, 'transfer_text' => 'Nachzahlung 2026']);
    Debit::factory()->create(['member_id' => $huber->id, 'transfer_text' => 'Trikot']);

    $this->actingAs(debitUser());

    $this->get(route('debits.index', ['search' => 'Nachzahlung']))
        ->assertInertia(fn ($page) => $page
            ->has('debits.data', 1)
            ->where('debits.data.0.transfer_text', 'Nachzahlung 2026')
            ->where('filters.search', 'Nachzahlung')
        );

    // The member's name is searched through the relation, which carries the
    // ClubScope, so the search cannot reach out of the club either.
    $this->get(route('debits.index', ['search' => 'Huber']))
        ->assertInertia(fn ($page) => $page
            ->has('debits.data', 1)
            ->where('debits.data.0.transfer_text', 'Trikot')
        );
});

test('the index marks what a collection started today would take along', function () {
    $member = debitableMemberOf();

    Debit::factory()->create(['member_id' => $member->id, 'due_at' => now()->subDay()]);
    Debit::factory()->create(['member_id' => $member->id, 'due_at' => now()->addMonth()]);

    $this->actingAs(debitUser())
        ->get(route('debits.index'))
        ->assertInertia(fn ($page) => $page
            // Ordered by due date, so the overdue one comes first.
            ->where('debits.data.0.due', true)
            ->where('debits.data.1.due', false)
            ->where('hasDebits', true)
            ->where('canCollect', true)
        );
});

test('an admin stores a debit and the picker only offers members with an account', function () {
    $withAccount = debitableMemberOf(attributes: ['surname' => 'Kontoinhaberin']);
    // No IBAN, so nothing could be collected from them.
    Member::factory()->ofClub(1)->create(['surname' => 'Barzahler']);

    $this->actingAs(debitUser())
        ->get(route('debits.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('debits/Create')
            ->has('members', 1)
            ->where('members.0.id', $withAccount->id)
            // The full IBAN, grouped in fours, not the short account number.
            ->where('members.0.name', "Kontoinhaberin {$withAccount->first_name} (DE89 3704 0044 0532 0130 00)")
        );

    $this->post(route('debits.store'), debitPayload($withAccount))->assertRedirect();

    $debit = Debit::firstOrFail();

    expect($debit->member_id)->toBe($withAccount->id)
        ->and($debit->amount)->toEqual(42.5)
        ->and($debit->due_at->format('Y-m-d'))->toBe('2026-09-03');
});

test('a debit cannot be booked on another club member or on one without an account', function () {
    $foreign = Member::factory()->payingByAccount()->create();
    $cash = Member::factory()->ofClub(1)->create();

    $this->actingAs(debitUser());

    // `exists` runs a plain query and does not pick up Member's ClubScope, so
    // the rule scopes by club by hand — this is what proves it does.
    $this->post(route('debits.store'), debitPayload($foreign))
        ->assertSessionHasErrors('member_id');

    $this->post(route('debits.store'), debitPayload($cash))
        ->assertSessionHasErrors('member_id');

    expect(Debit::withoutGlobalScopes()->count())->toBe(0);
});

test('the amount must be positive and the transfer text SEPA clean', function () {
    $member = debitableMemberOf();

    $this->actingAs(debitUser());

    // Unlike a 0 € subscription for an honorary member, a debit of 0 is an
    // instruction to move nothing.
    $this->post(route('debits.store'), debitPayload($member, ['amount' => 0]))
        ->assertSessionHasErrors('amount');

    $this->post(route('debits.store'), debitPayload($member, ['transfer_text' => 'Beitrag für Jänner']))
        ->assertSessionHasErrors('transfer_text');

    // The angle brackets of the placeholders are allowed even though the bare
    // SEPA character set forbids them: generateSepa() substitutes them away.
    $this->post(route('debits.store'), debitPayload($member, ['transfer_text' => 'Beitrag <AJ> <VN> <NN>']))
        ->assertSessionHasNoErrors();

    $this->post(route('debits.store'), debitPayload($member, ['due_at' => 'irgendwann']))
        ->assertSessionHasErrors('due_at');
});

test('an admin edits a debit', function () {
    $member = debitableMemberOf(attributes: ['surname' => 'Meier', 'first_name' => 'Anna']);
    $debit = Debit::factory()->create([
        'member_id' => $member->id,
        'amount' => 10,
        'transfer_text' => 'Trikot',
        'due_at' => '2026-09-03',
    ]);

    $this->actingAs(debitUser())
        ->get(route('debits.edit', $debit))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('debits/Edit')
            ->where('debit.transfer_text', 'Trikot')
            ->where('debit.due_at', '2026-09-03')
            ->where('debit.member_name', 'Anna Meier')
            ->where('deletable', true)
        );

    $this->put(route('debits.update', $debit), debitPayload($member, ['amount' => 99]))
        ->assertRedirect();

    expect($debit->refresh()->amount)->toEqual(99);
});

test('a debit is always deletable, nothing hangs off it', function () {
    $debit = Debit::factory()->create(['member_id' => debitableMemberOf()->id]);

    $this->actingAs(debitUser())
        ->delete(route('debits.destroy', $debit))
        ->assertRedirect();

    expect(Debit::find($debit->id))->toBeNull();
});

test('a debit of another club cannot be reached by guessing its id', function () {
    $foreign = Debit::factory()->create();

    $this->actingAs(debitUser())
        ->get(route('debits.edit', $foreign))
        ->assertNotFound();
});

test('the edit form keeps a member who has lost their bank details', function () {
    $member = debitableMemberOf(attributes: ['surname' => 'Meier', 'first_name' => 'Anna']);
    $debit = Debit::factory()->create(['member_id' => $member->id]);

    $member->update(['iban' => '']);

    // The picker no longer offers them, but the form still names them so the
    // debit can be saved without being rebooked on somebody else.
    $this->actingAs(debitUser())
        ->get(route('debits.edit', $debit))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('members', 0)
            ->where('debit.member_name', 'Anna Meier')
        );
});

test('the collection builds the SEPA files, clears what it took and leaves the rest', function () {
    $member = debitableMemberOf(attributes: ['surname' => 'Meier', 'first_name' => 'Anna']);

    Debit::factory()->create([
        'member_id' => $member->id,
        'amount' => 42.5,
        'transfer_text' => 'Nachzahlung <AJ> <VN> <NN>',
        'due_at' => '2026-09-01',
    ]);
    $later = Debit::factory()->create(['member_id' => $member->id, 'due_at' => '2027-01-01']);

    $this->actingAs(debitUser())
        ->post(route('debits.collect'), ['date' => '2026-09-03'])
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('debits/Collect')
            ->has('downloads', 2)
            ->where('downloads.0.href', '/downloads/sepa.xml')
            ->where('downloads.1.href', '/downloads/Abbuchungen.pdf')
            ->where('collected', 1)
            ->where('executionDate', '03.09.2026')
        );

    expect(Debit::pluck('id')->all())->toBe([$later->id]);

    $xml = file_get_contents(storage_path('downloads/1_sepa.xml'));

    expect($xml)->toContain('42.50')
        ->and($xml)->toContain('Nachzahlung 2026 Anna Meier')
        ->and($xml)->toContain('2026-09-03');
});

test('the collection never reaches another club, not even to delete', function () {
    $foreign = Debit::factory()->create(['due_at' => '2026-09-01']);
    Debit::factory()->create(['member_id' => debitableMemberOf()->id, 'due_at' => '2026-09-01']);

    $this->actingAs(debitUser())
        ->post(route('debits.collect'), ['date' => '2026-09-03'])
        ->assertOk();

    // lsverein7 had no scope here at all, so a collection swept up and then
    // deleted every club's debits at once.
    expect(Debit::withoutGlobalScopes()->pluck('id')->all())->toBe([$foreign->id]);
});

test('a collection with nothing due is turned away instead of writing an empty file', function () {
    Debit::factory()->create(['member_id' => debitableMemberOf()->id, 'due_at' => '2027-01-01']);

    $this->actingAs(debitUser())
        ->post(route('debits.collect'), ['date' => '2026-09-03'])
        ->assertRedirect(route('debits.index'));

    expect(Debit::count())->toBe(1);

    $this->post(route('debits.collect'), ['date' => 'irgendwann'])
        ->assertSessionHasErrors('date');
});

test('the amount label groups thousands with a dot, not another comma', function () {
    $debit = Debit::factory()->create([
        'member_id' => debitableMemberOf()->id,
        'amount' => 1234.5,
        'transfer_text' => 'Trikot',
    ]);

    expect($debit->amountLabel())->toBe('1.234,50 €')
        ->and((string) $debit)->toBe('Trikot (1.234,50 €)');
});
