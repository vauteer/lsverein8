<?php

use App\Enums\ClubRole;
use App\Models\Club;
use App\Models\Member;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * currentClubId() resolves to 1 on the CLI, so every request is read as though
 * the acting user were working in club 1.
 */
beforeEach(function () {
    $this->club = Club::factory()->create(['id' => 1, 'name' => 'TSV Musterstadt']);
});

function clubManagementUser(ClubRole $role = ClubRole::Admin, ?Club $club = null, array $attributes = []): User
{
    $club ??= Club::find(1) ?? Club::factory()->create(['id' => 1]);

    $user = User::factory()->create([...$attributes, 'club_id' => $club->id]);
    $user->clubs()->attach($club->id, ['role' => $role->value]);

    return $user;
}

/**
 * @return array<string, mixed>
 */
function clubPayload(array $overrides = []): array
{
    return [
        'name' => 'FF Musterdorf',
        'street' => 'Hauptstr. 1',
        'zipcode' => '12345',
        'city' => 'Musterdorf',
        'bank' => 'Sparkasse Musterdorf',
        'account_owner' => 'FF Musterdorf',
        'iban' => 'DE02120300000000202051',
        'bic' => 'BYLADEM1001',
        'display' => 1,
        'locale' => 'de',
        ...$overrides,
    ];
}

test('guests are redirected to the login page', function () {
    $this->get(route('clubs.index'))->assertRedirect(route('login'));
});

test('only a root account sees the club list', function () {
    $this->actingAs(clubManagementUser())->get(route('clubs.index'))->assertForbidden();
    $this->actingAs(clubManagementUser(ClubRole::Advanced))->get(route('clubs.index'))->assertForbidden();

    $this->actingAs(clubManagementUser(attributes: ['admin' => true]))
        ->get(route('clubs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/Index')
            ->where('canCreate', true)
        );
});

test('the list shows every club with its member and user counts', function () {
    $other = Club::factory()->create(['name' => 'FF Andersdorf']);
    Member::factory()->ofClub(1)->create()->memberships()->attach(1, ['from' => now()->subYear()]);

    $this->actingAs(clubManagementUser(attributes: ['admin' => true]))
        ->get(route('clubs.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('clubs.data', 2)
            // Ordered by name: "FF Andersdorf" before "TSV Musterstadt".
            ->where('clubs.data.0.id', $other->id)
            ->where('clubs.data.0.current', false)
            ->where('clubs.data.1.id', 1)
            ->where('clubs.data.1.current', true)
            ->where('clubs.data.1.members_count', 1)
            ->where('clubs.data.1.users_count', 1)
        );
});

test('a club admin may edit the current club but not another one', function () {
    $other = Club::factory()->create();

    $this->actingAs(clubManagementUser());

    $this->get(route('clubs.edit', $this->club))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('clubs/Edit')
            ->where('club.name', 'TSV Musterstadt')
            // No list to go back to, and no delete button.
            ->where('listable', false)
            ->where('deletable', false)
        );

    $this->get(route('clubs.edit', $other))->assertForbidden();
    $this->put(route('clubs.update', $other), clubPayload())->assertForbidden();
});

test('a member without admin rights may not edit the club at all', function () {
    $this->actingAs(clubManagementUser(ClubRole::Advanced))
        ->get(route('clubs.edit', $this->club))
        ->assertForbidden();
});

test('a root account may edit any club', function () {
    $other = Club::factory()->create();

    $this->actingAs(clubManagementUser(attributes: ['admin' => true]))
        ->get(route('clubs.edit', $other))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('listable', true));
});

test('a club admin updates the current club', function () {
    $this->actingAs(clubManagementUser())
        ->put(route('clubs.update', $this->club), clubPayload(['name' => 'TSV Musterstadt 1900']))
        ->assertRedirect(route('clubs.edit', $this->club));

    expect($this->club->refresh()->name)->toBe('TSV Musterstadt 1900');
});

test('only a root account creates a club, and is attached to it as admin', function () {
    $this->actingAs(clubManagementUser())->post(route('clubs.store'), clubPayload())->assertForbidden();

    $root = clubManagementUser(attributes: ['admin' => true]);

    $this->actingAs($root)
        ->post(route('clubs.store'), clubPayload())
        ->assertRedirect(route('clubs.index'));

    $created = Club::where('name', 'FF Musterdorf')->firstOrFail();

    // Without the attach nobody but a root account could ever reach it.
    expect($root->clubs()->whereKey($created->id)->exists())->toBeTrue()
        ->and($root->clubRole($created->id))->toBe(ClubRole::Admin->value);
});

test('the iban is normalized and checksum validated', function () {
    $this->actingAs(clubManagementUser(attributes: ['admin' => true]));

    $this->post(route('clubs.store'), clubPayload(['iban' => 'DE02120300000000202099']))
        ->assertSessionHasErrors('iban');

    $this->post(route('clubs.store'), clubPayload())->assertSessionHasNoErrors();

    expect(Club::where('name', 'FF Musterdorf')->value('iban'))
        ->toBe('DE02 1203 0000 0000 2020 51');
});

test('the club name must be unique and SEPA-safe', function () {
    $this->actingAs(clubManagementUser(attributes: ['admin' => true]));

    $this->post(route('clubs.store'), clubPayload(['name' => 'TSV Musterstadt']))
        ->assertSessionHasErrors('name');

    // & is outside the SEPA character set the creditor name is written with.
    $this->post(route('clubs.store'), clubPayload(['name' => 'Turn & Sport']))
        ->assertSessionHasErrors('name');
});

test('honor_years must be a comma separated list of years', function () {
    $this->actingAs(clubManagementUser(attributes: ['admin' => true]));

    $this->post(route('clubs.store'), clubPayload(['honor_years' => '25,40,50']))
        ->assertSessionHasNoErrors();

    $this->post(route('clubs.store'), clubPayload([
        'name' => 'FF Zweitdorf', 'honor_years' => '25; 40',
    ]))->assertSessionHasErrors('honor_years');
});

test('the logo column survives an update, since there is no upload yet', function () {
    $this->club->update(['logo' => 'wappen.png']);

    $this->actingAs(clubManagementUser())
        ->put(route('clubs.update', $this->club), clubPayload(['name' => 'TSV Musterstadt']))
        ->assertRedirect();

    expect($this->club->refresh()->logo)->toBe('wappen.png');
});

test('an empty club is deleted, a populated one is kept', function () {
    $empty = Club::factory()->create();
    $populated = Club::factory()->create();
    Member::factory()->create(['club_id' => $populated->id])
        ->memberships()->attach($populated->id, ['from' => now()->subYear()]);

    $this->actingAs(clubManagementUser(attributes: ['admin' => true]));

    $this->delete(route('clubs.destroy', $empty))->assertRedirect(route('clubs.index'));
    expect(Club::find($empty->id))->toBeNull();

    $this->delete(route('clubs.destroy', $populated))->assertForbidden();
    expect(Club::find($populated->id))->not->toBeNull();
});

test('a club with subscriptions is kept even without members', function () {
    $club = Club::factory()->create();
    Subscription::factory()->create(['club_id' => $club->id]);

    $this->actingAs(clubManagementUser(attributes: ['admin' => true]))
        ->delete(route('clubs.destroy', $club))
        ->assertForbidden();
});

test('the current club is never deletable, even when empty', function () {
    // currentClubId() is hardcoded to 1 on the CLI, so "the current club" is
    // always club 1 in tests. Attaching the root account to a second club
    // instead leaves club 1 with no users, no members and no subscriptions,
    // which isolates the current-club guard from the isUsed() one.
    $other = Club::factory()->create();
    $root = clubManagementUser(attributes: ['admin' => true], club: $other);

    expect($this->club->isUsed())->toBeFalse();

    $this->actingAs($root)
        ->delete(route('clubs.destroy', $this->club))
        ->assertForbidden();

    expect(Club::find(1))->not->toBeNull();
});

test('a club admin may not delete any club', function () {
    $empty = Club::factory()->create();

    $this->actingAs(clubManagementUser())
        ->delete(route('clubs.destroy', $empty))
        ->assertForbidden();
});

test('the logo sweep leaves the directory .gitignore alone', function () {
    // Same dotfile guard as User::removeOrphanProfileImages(); the club logo
    // upload does not exist yet, so this pins the helper directly.
    Storage::fake('public');
    Storage::disk('public')->put(Club::logoStoragePath('.gitignore'), "*\n!.gitignore\n");
    Storage::disk('public')->put(Club::logoStoragePath('orphan.png'), 'nobody points here');
    Club::factory()->create(['logo' => 'wappen.png']);
    Storage::disk('public')->put(Club::logoStoragePath('wappen.png'), 'referenced');

    expect(Club::removeOrphanLogos())->toBe(1);

    Storage::disk('public')->assertExists(Club::logoStoragePath('.gitignore'));
    Storage::disk('public')->assertExists(Club::logoStoragePath('wappen.png'));
    Storage::disk('public')->assertMissing(Club::logoStoragePath('orphan.png'));
});
