<?php

use App\Enums\ClubIdentityDisplay;
use App\Enums\ClubRole;
use App\Models\Club;
use App\Models\Event;
use App\Models\Member;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\UploadedFile;
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
        'identity_display' => 1,
        'locale' => 'de',
        'sepa_lead_days' => 8,
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

test('the member count is the current members of each club', function () {
    $other = Club::factory()->create(['name' => 'FF Andersdorf']);

    // Own club: one current member, one who left, one who died. Only the
    // first is counted, matching the member list's default selection.
    Member::factory()->ofClub(1)->create()->memberships()->attach(1, ['from' => '2016-01-01']);
    Member::factory()->ofClub(1)->create()->memberships()->attach(1, ['from' => '2005-01-01', 'to' => '2009-12-31']);
    Member::factory()->ofClub(1)->deceased()->create()->memberships()->attach(1, ['from' => '2010-01-01']);

    // Counted per club, not for whoever is looking: currentClubId() is 1, so a
    // count leaning on Member::memberIds() would report 0 here.
    Member::factory()->ofClub($other->id)->create()
        ->memberships()->attach($other->id, ['from' => '2016-01-01']);
    Member::factory()->ofClub($other->id)->create()
        ->memberships()->attach($other->id, ['from' => '2016-01-01']);

    $this->actingAs(clubManagementUser(attributes: ['admin' => true]))
        ->get(route('clubs.index'))
        ->assertInertia(fn ($page) => $page
            ->where('clubs.data.0.id', $other->id)
            ->where('clubs.data.0.members_count', 2)
            ->where('clubs.data.1.id', 1)
            ->where('clubs.data.1.members_count', 1)
        );

    // And it agrees with what the number links to, for the club being worked in.
    $this->get(route('members.index'))
        ->assertInertia(fn ($page) => $page->has('members.data', 1));
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

    // And without a subscription it could not take its first member: one is
    // required, and subscriptions have no installation-wide rows to fall back
    // on the way sections do. 0 €, so it bills nobody until the admin edits it.
    $seeded = Subscription::withoutGlobalScopes()->where('club_id', $created->id)->get();

    expect($seeded)->toHaveCount(1)
        ->and($seeded->first()->name)->toBe('Beitragsfrei')
        ->and($seeded->first()->amount)->toBe(0.0);

    // Roles and honours used to be inherited: one unowned copy per
    // installation, listed in every club. Both columns are NOT NULL since
    // 2026-08-30, so the club gets its own to rename or delete.
    // sorted: the models order by name, the constants read in the order the
    // club would want them offered.
    $roles = Role::withoutGlobalScopes()->where('club_id', $created->id)->pluck('name')->sort()->values();
    $events = Event::withoutGlobalScopes()->where('club_id', $created->id)->pluck('name')->sort()->values();

    expect($roles->all())->toBe(collect(Role::DEFAULTS)->sort()->values()->all())
        ->and($events->all())->toBe(collect(Event::DEFAULTS)->sort()->values()->all());
});

test('the club sets its own SEPA lead time', function () {
    $this->actingAs(clubManagementUser());

    $this->put(route('clubs.update', $this->club), clubPayload(['sepa_lead_days' => 14]))
        ->assertRedirect(route('clubs.edit', $this->club));

    expect($this->club->refresh()->sepa_lead_days)->toBe(14);

    // A tinyint column, and a lead time of half a year is a typo rather than
    // an instruction.
    $this->put(route('clubs.update', $this->club), clubPayload(['sepa_lead_days' => 400]))
        ->assertSessionHasErrors('sepa_lead_days');

    $this->put(route('clubs.update', $this->club), clubPayload(['sepa_lead_days' => 'bald']))
        ->assertSessionHasErrors('sepa_lead_days');

    expect($this->club->refresh()->sepa_lead_days)->toBe(14);
});

test('the checkbox fields accept "on" and clear when absent', function () {
    $this->actingAs(clubManagementUser());
    $this->club->update(['blsv_member' => true, 'use_items' => true]);

    // reka-ui's Checkbox submits "on", which the boolean rule would reject.
    $this->put(route('clubs.update', $this->club), clubPayload([
        'blsv_member' => 'on',
        'use_items' => 'on',
    ]))->assertSessionHasNoErrors();

    expect($this->club->refresh()->blsv_member)->toBeTrue()
        ->and($this->club->use_items)->toBeTrue();

    // An unchecked box is simply absent from the request, and has to clear
    // the flag rather than leave the old value standing.
    $this->put(route('clubs.update', $this->club), clubPayload())
        ->assertSessionHasNoErrors();

    expect($this->club->refresh()->blsv_member)->toBeFalse()
        ->and($this->club->use_items)->toBeFalse();
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

test('an update without a logo leaves the existing one alone', function () {
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
    // upload lives in the controller, so this pins the helper directly.
    Storage::disk('public')->put(Club::logoStoragePath('.gitignore'), "*\n!.gitignore\n");
    Storage::disk('public')->put(Club::logoStoragePath('orphan.png'), 'nobody points here');
    Club::factory()->create(['logo' => 'wappen.png']);
    Storage::disk('public')->put(Club::logoStoragePath('wappen.png'), 'referenced');

    expect(Club::removeOrphanLogos())->toBe(1);

    Storage::disk('public')->assertExists(Club::logoStoragePath('.gitignore'));
    Storage::disk('public')->assertExists(Club::logoStoragePath('wappen.png'));
    Storage::disk('public')->assertMissing(Club::logoStoragePath('orphan.png'));
});

test('a logo can be uploaded when creating a club', function () {
    $this->actingAs(clubManagementUser(attributes: ['admin' => true]))
        ->post(route('clubs.store'), clubPayload([
            'logo' => UploadedFile::fake()->image('wappen.png'),
        ]))
        ->assertSessionHasNoErrors();

    $filename = Club::where('name', 'FF Musterdorf')->value('logo');

    expect($filename)->not->toBeNull();
    Storage::disk('public')->assertExists(Club::logoStoragePath($filename));
});

test('replacing the logo deletes the file it replaced', function () {
    Storage::disk('public')->put(Club::logoStoragePath('old.png'), 'fake image contents');
    $this->club->update(['logo' => 'old.png']);

    $this->actingAs(clubManagementUser())
        ->put(route('clubs.update', $this->club), clubPayload([
            'name' => 'TSV Musterstadt',
            'logo' => UploadedFile::fake()->image('neu.png'),
        ]))
        ->assertSessionHasNoErrors();

    $filename = $this->club->refresh()->logo;

    expect($filename)->not->toBe('old.png');
    Storage::disk('public')->assertMissing(Club::logoStoragePath('old.png'));
    Storage::disk('public')->assertExists(Club::logoStoragePath($filename));
});

test('the logo can be removed again', function () {
    Storage::disk('public')->put(Club::logoStoragePath('old.png'), 'fake image contents');
    $this->club->update(['logo' => 'old.png']);

    $this->actingAs(clubManagementUser())
        ->put(route('clubs.update', $this->club), clubPayload([
            'name' => 'TSV Musterstadt',
            'remove_logo' => '1',
        ]))
        ->assertSessionHasNoErrors();

    expect($this->club->refresh()->logo)->toBeNull();
    Storage::disk('public')->assertMissing(Club::logoStoragePath('old.png'));
});

test('removing wins over a logo sent in the same request', function () {
    $this->club->update(['logo' => 'old.png']);

    $this->actingAs(clubManagementUser())
        ->put(route('clubs.update', $this->club), clubPayload([
            'name' => 'TSV Musterstadt',
            'remove_logo' => '1',
            'logo' => UploadedFile::fake()->image('neu.png'),
        ]))
        ->assertSessionHasNoErrors();

    expect($this->club->refresh()->logo)->toBeNull();
});

test('the logo must be an image and stay under 2 MB', function () {
    $this->actingAs(clubManagementUser());

    $this->put(route('clubs.update', $this->club), clubPayload([
        'name' => 'TSV Musterstadt',
        'logo' => UploadedFile::fake()->create('satzung.pdf', 100),
    ]))->assertSessionHasErrors('logo');

    $this->put(route('clubs.update', $this->club), clubPayload([
        'name' => 'TSV Musterstadt',
        'logo' => UploadedFile::fake()->image('riesig.png')->size(3000),
    ]))->assertSessionHasErrors('logo');

    expect($this->club->refresh()->logo)->toBeNull();
});

test('the edit page reports whether a logo is set', function () {
    $this->actingAs(clubManagementUser())
        ->get(route('clubs.edit', $this->club))
        ->assertInertia(fn ($page) => $page->where('club.has_logo', false));

    $this->club->update(['logo' => 'wappen.png']);

    $this->actingAs(clubManagementUser())
        ->get(route('clubs.edit', $this->club))
        ->assertInertia(fn ($page) => $page->where('club.has_logo', true));
});

test('the display setting is cast to the enum and drives the two flags', function () {
    expect($this->club->identity_display)->toBe(ClubIdentityDisplay::LogoAndName);

    // Tuples, not an enum-keyed array: PHP array keys cannot be enums.
    $cases = [
        [ClubIdentityDisplay::LogoAndName, true, true],
        [ClubIdentityDisplay::LogoOnly, true, false],
        [ClubIdentityDisplay::NameOnly, false, true],
    ];

    foreach ($cases as [$display, $showsLogo, $showsName]) {
        expect($display->showsLogo())->toBe($showsLogo)
            ->and($display->showsName())->toBe($showsName);
    }
});

test('the sidebar receives the display flags for the current club', function () {
    $this->actingAs(clubManagementUser());

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('currentClub.show_logo', true)
            ->where('currentClub.show_name', true));

    $this->club->update(['identity_display' => ClubIdentityDisplay::LogoOnly]);

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('currentClub.show_logo', true)
            // A wordmark logo already carries the name.
            ->where('currentClub.show_name', false));

    $this->club->update(['identity_display' => ClubIdentityDisplay::NameOnly]);

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('currentClub.show_logo', false)
            ->where('currentClub.show_name', true));
});

test('the display setting can be changed and is validated', function () {
    $this->actingAs(clubManagementUser())
        ->put(route('clubs.update', $this->club), clubPayload([
            'name' => 'TSV Musterstadt',
            'identity_display' => ClubIdentityDisplay::NameOnly->value,
        ]))
        ->assertSessionHasNoErrors();

    expect($this->club->refresh()->identity_display)->toBe(ClubIdentityDisplay::NameOnly);

    $this->put(route('clubs.update', $this->club), clubPayload([
        'name' => 'TSV Musterstadt',
        'identity_display' => 9,
    ]))->assertSessionHasErrors('identity_display');
});

test('the form offers the styles with translated labels', function () {
    $this->actingAs(clubManagementUser(attributes: ['admin' => true]))
        ->get(route('clubs.create'))
        ->assertInertia(fn ($page) => $page
            ->has('identityDisplays', 3)
            ->where('identityDisplays.0.id', ClubIdentityDisplay::LogoAndName->value)
            // Was a hardcoded German string before the enum; it now goes
            // through __() like every other label.
            ->where('identityDisplays.0.name', __('Logo and name')));
});
