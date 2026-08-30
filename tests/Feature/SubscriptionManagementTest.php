<?php

use App\Enums\ClubRole;
use App\Models\Club;
use App\Models\Member;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\File;

/**
 * currentClubId() resolves to 1 on the CLI, so every request is read as though
 * the acting user were working in club 1.
 */
beforeEach(function () {
    $this->club = Club::factory()->create(['id' => 1]);
    Member::setKeyDate(null);
});

afterEach(fn () => Member::setKeyDate(null));

/**
 * Create a user belonging to the given club (defaulting to club 1) with the
 * given role in it.
 */
function subscriptionUser(ClubRole $role = ClubRole::Admin, ?Club $club = null, array $attributes = []): User
{
    $club ??= Club::find(1) ?? Club::factory()->create(['id' => 1]);

    $user = User::factory()->create([...$attributes, 'club_id' => $club->id]);
    $user->clubs()->attach($club->id, ['role' => $role->value]);

    return $user;
}

/**
 * A valid payload; individual tests override the field they are exercising.
 *
 * @return array<string, mixed>
 */
function subscriptionPayload(array $overrides = []): array
{
    return [
        'name' => 'Jahresbeitrag',
        'amount' => 42.5,
        'transfer_text' => 'Beitrag <AJ> <VN> <NN>',
        'memo' => null,
        ...$overrides,
    ];
}

test('guests are redirected to the login page', function () {
    $this->get(route('subscriptions.index'))->assertRedirect(route('login'));
});

test('the index lists the club subscriptions but no other club', function () {
    $own = Subscription::factory()->create(['club_id' => 1, 'name' => 'Jahresbeitrag']);
    $foreign = Subscription::factory()->create(['name' => 'Fremder Beitrag']);

    $this->actingAs(subscriptionUser(ClubRole::Basic))
        ->get(route('subscriptions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('subscriptions/Index')
            ->has('subscriptions.data', 1)
            ->where('subscriptions.data.0.id', $own->id)
            ->whereNot('subscriptions.data.0.id', $foreign->id)
            // Everybody may look, only an admin may change.
            ->where('subscriptions.data.0.modifiable', false)
            ->where('canCreate', false)
        );
});

test('the index formats the amount and counts only the current club members', function () {
    $subscription = Subscription::factory()->create([
        'club_id' => 1,
        'name' => 'Familienbeitrag',
        'amount' => 1234.5,
    ]);

    $holder = Member::factory()->ofClub(1)->create();
    $holder->memberships()->attach(1, ['from' => '2016-01-01', 'to' => null]);
    $subscription->members()->attach($holder->id);

    $this->actingAs(subscriptionUser())
        ->get(route('subscriptions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('subscriptions/Index')
            ->where('subscriptions.data.0.amount', 1234.5)
            ->where('subscriptions.data.0.amount_label', '1.234,50 €')
            ->where('subscriptions.data.0.members_count', 1)
        );
});

test('the count is who holds the subscription now, matching what it links to', function () {
    $subscription = Subscription::factory()->create([
        'club_id' => 1, 'name' => 'Erwachsen', 'amount' => 60,
    ]);

    // Holds it and is in the club: counted.
    $current = Member::factory()->ofClub(1)->create(['surname' => 'Zahlerin']);
    $current->memberships()->attach(1, ['from' => '2016-01-01', 'to' => null]);
    $current->subscriptions()->attach($subscription->id);

    // Left the club, but member_subscription has no dates so the row stayed.
    // That is exactly what inflated the number: Erwachsen read 242 in
    // production where the selection shows 140.
    $gone = Member::factory()->ofClub(1)->create(['surname' => 'Ausgetreten']);
    $gone->memberships()->attach(1, ['from' => '2005-01-01', 'to' => '2009-12-31']);
    $gone->subscriptions()->attach($subscription->id);

    $this->actingAs(subscriptionUser());

    $this->get(route('subscriptions.index', ['search' => 'Erwachsen']))
        ->assertInertia(fn ($page) => $page
            ->has('subscriptions.data', 1)
            ->where('subscriptions.data.0.members_count', 1)
        );

    // Exactly what the number links to.
    $this->get(route('members.index', ['filter' => "subscription_{$subscription->id}"]))
        ->assertInertia(fn ($page) => $page
            ->has('members.data', 1)
            ->where('members.data.0.surname', 'Zahlerin')
        );
});

test('the index can be searched by name', function () {
    Subscription::factory()->create(['club_id' => 1, 'name' => 'Jugendbeitrag']);
    Subscription::factory()->create(['club_id' => 1, 'name' => 'Familienbeitrag']);

    $this->actingAs(subscriptionUser())
        ->get(route('subscriptions.index', ['search' => 'Jugend']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('subscriptions.data', 1)
            ->where('subscriptions.data.0.name', 'Jugendbeitrag')
            ->where('filters.search', 'Jugend')
        );
});

test('the index is ordered by amount, then by name', function () {
    Subscription::factory()->create(['club_id' => 1, 'name' => 'Ehrenmitglied', 'amount' => 0]);
    Subscription::factory()->create(['club_id' => 1, 'name' => 'Familienbeitrag', 'amount' => 60]);
    // Same amount as the family fee: the name breaks the tie, not the id.
    Subscription::factory()->create(['club_id' => 1, 'name' => 'Aktivenbeitrag', 'amount' => 60]);
    Subscription::factory()->create(['club_id' => 1, 'name' => 'Jugendbeitrag', 'amount' => 30]);

    $this->actingAs(subscriptionUser())
        ->get(route('subscriptions.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('subscriptions.data.0.name', 'Ehrenmitglied')
            ->where('subscriptions.data.1.name', 'Jugendbeitrag')
            ->where('subscriptions.data.2.name', 'Aktivenbeitrag')
            ->where('subscriptions.data.3.name', 'Familienbeitrag')
        );
});

test('only an admin reaches the create form and stores a subscription', function () {
    $this->actingAs(subscriptionUser(ClubRole::Advanced))
        ->get(route('subscriptions.create'))
        ->assertForbidden();

    $this->actingAs(subscriptionUser())
        ->get(route('subscriptions.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('subscriptions/Create'));

    $this->post(route('subscriptions.store'), subscriptionPayload(['memo' => 'ab 18']))
        ->assertRedirect();

    $subscription = Subscription::firstOrFail();

    expect($subscription->name)->toBe('Jahresbeitrag')
        ->and($subscription->amount)->toBe(42.5)
        ->and($subscription->transfer_text)->toBe('Beitrag <AJ> <VN> <NN>')
        ->and($subscription->memo)->toBe('ab 18')
        // Never taken from the request, always the current club.
        ->and($subscription->club_id)->toBe(1);
});

test('club_id from the request is ignored, so a subscription cannot be planted elsewhere', function () {
    $other = Club::factory()->create();

    $this->actingAs(subscriptionUser())
        ->post(route('subscriptions.store'), subscriptionPayload(['club_id' => $other->id]))
        ->assertRedirect();

    expect(Subscription::withoutGlobalScopes()->firstOrFail()->club_id)->toBe(1);
});

test('the name is unique within the club but not across clubs', function () {
    Subscription::factory()->create(['club_id' => 1, 'name' => 'Jahresbeitrag']);

    $this->actingAs(subscriptionUser());

    $this->post(route('subscriptions.store'), subscriptionPayload())
        ->assertSessionHasErrors('name');

    // The same name in another club is fine — unique(club_id, name).
    Subscription::factory()->create(['name' => 'Jahresbeitrag']);

    expect(Subscription::withoutGlobalScopes()->where('name', 'Jahresbeitrag')->count())->toBe(2);
});

test('the transfer text rejects umlauts but keeps the placeholder brackets', function () {
    $this->actingAs(subscriptionUser());

    $this->post(route('subscriptions.store'), subscriptionPayload(['transfer_text' => 'Jahresbeiträge <AJ>']))
        ->assertSessionHasErrors('transfer_text');

    $this->post(route('subscriptions.store'), subscriptionPayload(['transfer_text' => 'Beitrag <AJ> <VN> <NN>']))
        ->assertSessionHasNoErrors();
});

test('the amount must be a number within the column range', function () {
    $this->actingAs(subscriptionUser());

    $this->post(route('subscriptions.store'), subscriptionPayload(['amount' => 'viel']))
        ->assertSessionHasErrors('amount');

    // decimal(8,2) cannot hold this, so validation refuses it first.
    $this->post(route('subscriptions.store'), subscriptionPayload(['amount' => 1000000]))
        ->assertSessionHasErrors('amount');

    $this->post(route('subscriptions.store'), subscriptionPayload(['amount' => 0]))
        ->assertSessionHasNoErrors();
});

test('an admin edits a subscription', function () {
    $subscription = Subscription::factory()->create([
        'club_id' => 1,
        'name' => 'Jahresbeitrag',
        'amount' => 39.5,
    ]);

    $this->actingAs(subscriptionUser())
        ->get(route('subscriptions.edit', $subscription))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('subscriptions/Edit')
            ->where('subscription.name', 'Jahresbeitrag')
            ->where('subscription.amount', 39.5)
            ->where('deletable', true)
        );

    $this->put(route('subscriptions.update', $subscription), subscriptionPayload([
        'name' => 'Jahresbeitrag Erwachsene',
        'amount' => 55,
    ]))->assertRedirect();

    $subscription->refresh();

    expect($subscription->name)->toBe('Jahresbeitrag Erwachsene')
        ->and($subscription->amount)->toBe(55.0);
});

test('a non-admin may not edit a subscription', function () {
    $subscription = Subscription::factory()->create(['club_id' => 1]);

    $this->actingAs(subscriptionUser(ClubRole::Advanced))
        ->get(route('subscriptions.edit', $subscription))
        ->assertForbidden();
});

test('an unused subscription is deleted and one a member holds is kept', function () {
    $unused = Subscription::factory()->create(['club_id' => 1]);
    $used = Subscription::factory()->create(['club_id' => 1]);

    $used->members()->attach(Member::factory()->ofClub(1)->create()->id);

    $this->actingAs(subscriptionUser());

    $this->delete(route('subscriptions.destroy', $unused))->assertRedirect();
    expect(Subscription::find($unused->id))->toBeNull();

    // member_subscription is ON DELETE CASCADE, so the policy is the only
    // thing keeping the assignments from being dropped silently.
    $this->delete(route('subscriptions.destroy', $used))->assertForbidden();
    expect(Subscription::find($used->id))->not->toBeNull();

    $this->get(route('subscriptions.edit', $used))
        ->assertInertia(fn ($page) => $page->where('deletable', false));
});

test('a subscription of another club cannot be reached by guessing its id', function () {
    $foreign = Subscription::factory()->create();

    $this->actingAs(subscriptionUser())
        ->get(route('subscriptions.edit', $foreign))
        ->assertNotFound();
});

/**
 * A member of club 1 who pays by direct debit and holds the given
 * subscription. `Member::members()` only sees members with a running
 * membership, so the pivot row is what makes them countable.
 */
function debitableMember(Subscription $subscription, string $surname): Member
{
    $member = Member::factory()->ofClub(1)->payingByAccount()->create(['surname' => $surname]);
    $member->memberships()->attach(1, ['from' => '2016-01-01', 'to' => null]);
    $member->subscriptions()->attach($subscription->id);

    return $member;
}

test('the index offers the collection to an admin only', function () {
    Subscription::factory()->create(['club_id' => 1, 'amount' => 30]);

    $this->actingAs(subscriptionUser(ClubRole::Advanced))
        ->get(route('subscriptions.index'))
        ->assertInertia(fn ($page) => $page
            ->where('canDebit', false)
            ->where('debitable', [])
            ->where('sepaDate', null)
        );

    $this->actingAs(subscriptionUser())
        ->get(route('subscriptions.index'))
        ->assertInertia(fn ($page) => $page
            ->where('canDebit', true)
            ->has('debitable', 1)
            // SEPA needs lead time, so the picker does not default to today.
            ->where('sepaDate', now()->addDays(8)->format('Y-m-d'))
        );
});

test('the collection dialogs suggest a date the club itself decides', function () {
    Subscription::factory()->create(['club_id' => 1, 'amount' => 30]);

    // The lead time is the bank's, so it sits on the club rather than in the
    // controllers, where it was the same 8 written twice.
    Club::query()->whereKey(1)->update(['sepa_lead_days' => 14]);

    $this->actingAs(subscriptionUser(ClubRole::Admin));

    $expected = now()->addDays(14)->format('Y-m-d');

    $this->get(route('subscriptions.index'))
        ->assertInertia(fn ($page) => $page->where('sepaDate', $expected));

    // Both dialogs read the one column, so they cannot drift apart.
    $this->get(route('debits.index'))
        ->assertInertia(fn ($page) => $page->where('sepaDate', $expected));
});

test('the collection dialog offers every fee of the club, not just the page', function () {
    // 16 rows: one more than the index shows per page.
    Subscription::factory()->count(16)->sequence(fn ($sequence) => [
        'club_id' => 1,
        'name' => 'Beitrag '.$sequence->index,
        'amount' => $sequence->index + 1,
    ])->create();

    $this->actingAs(subscriptionUser())
        ->get(route('subscriptions.index'))
        ->assertInertia(fn ($page) => $page
            ->has('subscriptions.data', 15)
            ->has('debitable', 16)
            ->where('debitable.0.name', 'Beitrag 0')
            ->where('debitable.0.amount_label', '1,00 €')
        );
});

test('a 0 euro fee is neither offered nor accepted for collection', function () {
    $free = Subscription::factory()->create(['club_id' => 1, 'name' => 'Ehrenmitglied', 'amount' => 0]);
    $paid = Subscription::factory()->create(['club_id' => 1, 'name' => 'Jahresbeitrag', 'amount' => 30]);

    $this->actingAs(subscriptionUser())
        ->get(route('subscriptions.index'))
        ->assertInertia(fn ($page) => $page
            // Still listed in the table, just not collectible.
            ->has('subscriptions.data', 2)
            ->has('debitable', 1)
            ->where('debitable.0.id', $paid->id)
            // Drives the "0 € fees are not listed" note in the dialog.
            ->where('freeCount', 1)
        );

    $this->post(route('subscriptions.debit'), [
        'subscriptions' => [$free->id],
        'date' => '2026-09-03',
    ])->assertSessionHasErrors('subscriptions.0');
});

test('the amount label groups thousands with a dot, not another comma', function () {
    $subscription = Subscription::factory()->create(['club_id' => 1, 'amount' => 1234.5]);

    expect($subscription->amountLabel())->toBe('1.234,50 €')
        ->and((string) $subscription)->toContain('(1.234,50 €)');
});

test('a non-admin may not collect the fees', function () {
    $subscription = Subscription::factory()->create(['club_id' => 1]);

    $this->actingAs(subscriptionUser(ClubRole::Advanced))
        ->post(route('subscriptions.debit'), [
            'subscriptions' => [$subscription->id],
            'date' => now()->addDays(8)->format('Y-m-d'),
        ])
        ->assertForbidden();
});

test('an admin collects the fees and gets the files plus the outstanding list', function () {
    $subscription = Subscription::factory()->create([
        'club_id' => 1,
        'name' => 'Jahresbeitrag',
        'amount' => 42.5,
        'transfer_text' => 'Beitrag <AJ> <VN> <NN>',
    ]);

    $paying = debitableMember($subscription, 'Zahlerin');

    // No bank details, so the club has to bill this one by hand.
    $invoiced = Member::factory()->ofClub(1)->create([
        'surname' => 'Rechnungsempfaengerin',
    ]);
    $invoiced->memberships()->attach(1, ['from' => '2016-01-01', 'to' => null]);
    $invoiced->subscriptions()->attach($subscription->id);

    $this->actingAs(subscriptionUser())
        ->post(route('subscriptions.debit'), [
            'subscriptions' => [$subscription->id],
            'date' => '2026-09-03',
        ])
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('subscriptions/Debit')
            ->has('downloads', 2)
            ->where('downloads.0.href', '/downloads/sepa.xml')
            ->where('downloads.1.href', '/downloads/Abbuchungen.pdf')
            ->where('executionDate', '03.09.2026')
            // Only the member who does not pay by direct debit.
            ->has('outStandings', 1)
            ->where('outStandings.0.name', $invoiced->first_name.' '.$invoiced->surname)
            ->where('outStandings.0.paymentMethod', 'Rechnung')
        );

    $xml = file_get_contents(storage_path('downloads/1_sepa.xml'));

    expect($xml)->toContain('42.50')
        ->and($xml)->toContain("Beitrag 2026 {$paying->first_name} {$paying->surname}")
        ->and($xml)->toContain('2026-09-03')
        ->and($xml)->not->toContain($invoiced->surname);
});

test('the collection refuses an empty selection, a bad date and another club subscription', function () {
    $own = Subscription::factory()->create(['club_id' => 1]);
    $foreign = Subscription::factory()->create();

    $this->actingAs(subscriptionUser());

    $this->post(route('subscriptions.debit'), ['subscriptions' => [], 'date' => '2026-09-03'])
        ->assertSessionHasErrors('subscriptions');

    $this->post(route('subscriptions.debit'), ['subscriptions' => [$own->id], 'date' => 'irgendwann'])
        ->assertSessionHasErrors('date');

    // `exists` runs a plain query and does not pick up the ClubScope, so the
    // rule has to scope by club by hand — this is what proves it does.
    $this->post(route('subscriptions.debit'), [
        'subscriptions' => [$foreign->id],
        'date' => '2026-09-03',
    ])->assertSessionHasErrors('subscriptions.0');
});

test('a generated file is served from the current club and nothing else is reachable', function () {
    File::ensureDirectoryExists(storage_path('downloads'));
    file_put_contents(storage_path('downloads/1_sepa.xml'), '<xml/>');
    // 999 rather than a real club id: this directory is deliberately not
    // faked (see the storage rule), so the fixture must not be able to
    // overwrite a file another club really generated.
    file_put_contents(storage_path('downloads/999_sepa.xml'), '<other-club/>');

    $this->actingAs(subscriptionUser());

    // currentClubId() is 1 on the CLI, so the bare name resolves to 1_sepa.xml
    // and the other club's identically named file stays out of reach.
    $response = $this->get(route('downloads.show', ['filename' => 'sepa.xml']));
    $response->assertOk();
    expect($response->streamedContent())->toBe('<xml/>');

    $this->get(route('downloads.show', ['filename' => 'nichts.xml']))->assertNotFound();
    $this->get(route('downloads.show', ['filename' => '..']))->assertNotFound();
});

test('a non-admin may not download the generated files', function () {
    File::ensureDirectoryExists(storage_path('downloads'));
    file_put_contents(storage_path('downloads/1_sepa.xml'), '<xml/>');

    $this->actingAs(subscriptionUser(ClubRole::Advanced))
        ->get(route('downloads.show', ['filename' => 'sepa.xml']))
        ->assertForbidden();
});
