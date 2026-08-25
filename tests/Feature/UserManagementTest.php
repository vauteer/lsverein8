<?php

use App\Enums\ActionType;
use App\Enums\ClubRole;
use App\Models\Club;
use App\Models\ClubUser;
use App\Models\Tracing;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * currentClubId() resolves to 1 on the CLI, so the whole user index is read
 * as though the acting user were working in club 1.
 */
beforeEach(function () {
    $this->club = Club::factory()->create(['id' => 1]);
});

/**
 * Create a user belonging to the given club (defaulting to club 1) with the
 * given role in it.
 */
function clubUser(ClubRole $role = ClubRole::Admin, ?Club $club = null, array $attributes = []): User
{
    $club ??= Club::find(1) ?? Club::factory()->create(['id' => 1]);

    $user = User::factory()->create([...$attributes, 'club_id' => $club->id]);
    $user->clubs()->attach($club->id, ['role' => $role->value]);

    return $user;
}

test('guests are redirected to the login page', function () {
    $this->get(route('users.index'))->assertRedirect(route('login'));
});

test('users without admin rights may not manage users', function () {
    $this->actingAs(clubUser(ClubRole::Advanced));

    $this->get(route('users.index'))->assertForbidden();
    $this->get(route('users.create'))->assertForbidden();
});

test('the index lists only the users of the current club', function () {
    // The admin's name is pinned to sort last: the listing is ordered by name
    // and the assertion below expects the colleague first, so a random faker
    // name beginning with "Ada..." made this fail at random.
    $admin = clubUser(attributes: ['name' => 'Zora Admin']);
    $colleague = clubUser(ClubRole::Basic, attributes: ['name' => 'Anna Beispiel']);

    $otherClub = Club::factory()->create();
    $outsider = clubUser(ClubRole::Admin, $otherClub, ['name' => 'Fremder Nutzer']);

    $this->actingAs($admin)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('users/Index')
            ->has('users.data', 2)
            ->where('users.data.0.name', $colleague->name)
            ->where('users.data.0.role_label', ClubRole::Basic->label())
            ->whereNot('users.data.0.id', $outsider->id)
            ->whereNot('users.data.1.id', $outsider->id)
        );
});

test('the index reports the last login and can be searched', function () {
    $admin = clubUser(attributes: ['name' => 'Zora Admin']);
    $target = clubUser(ClubRole::Basic, attributes: ['name' => 'Anna Beispiel']);

    Tracing::create([
        'at' => now()->subDay(),
        'user_id' => $target->id,
        'action_type' => ActionType::Login->value,
    ]);

    $this->actingAs($admin)
        ->get(route('users.index', ['search' => 'Anna']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.id', $target->id)
            ->whereNot('users.data.0.last_login', null)
            ->where('filters.search', 'Anna')
        );
});

test('an admin creates a user who is emailed a password reset link', function () {
    Notification::fake();

    $admin = clubUser();

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Neue Person',
            'email' => 'neu@example.test',
            'phone' => '0123 456',
            'locale' => 'de',
            'role' => ClubRole::Advanced->value,
        ])
        ->assertRedirect();

    $created = User::where('email', 'neu@example.test')->firstOrFail();

    expect($created->name)->toBe('Neue Person')
        ->and($created->phone)->toBe('0123 456')
        ->and($created->locale)->toBe('de')
        ->and($created->club_id)->toBe(1)
        ->and($created->created_by)->toBe($admin->id)
        ->and($created->admin)->toBeFalse()
        ->and($created->clubRole())->toBe(ClubRole::Advanced->value);

    Notification::assertSentTo($created, ResetPassword::class);
});

test('creating a user cannot grant the global superuser flag', function () {
    $this->actingAs(clubUser())
        ->post(route('users.store'), [
            'name' => 'Neue Person',
            'email' => 'neu@example.test',
            'locale' => 'de',
            'role' => ClubRole::Basic->value,
            'admin' => true,
        ])
        ->assertRedirect();

    expect(User::where('email', 'neu@example.test')->firstOrFail()->admin)->toBeFalse();
});

test('creating a user with a known email adds that account to the club', function () {
    Notification::fake();

    $admin = clubUser();
    $otherClub = Club::factory()->create();
    $existing = clubUser(ClubRole::Basic, $otherClub, ['name' => 'Bereits Da']);

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'name' => 'Anderer Name',
            'email' => $existing->email,
            'locale' => 'en',
            'role' => ClubRole::Admin->value,
        ])
        ->assertRedirect();

    $existing->refresh();

    expect(User::where('email', $existing->email)->count())->toBe(1)
        ->and($existing->name)->toBe('Bereits Da')
        ->and($existing->clubRole(1))->toBe(ClubRole::Admin->value);

    Notification::assertNothingSent();
});

test('creating a user rejects an email that already belongs to the club', function () {
    $admin = clubUser();
    $colleague = clubUser(ClubRole::Basic);

    $this->actingAs($admin)
        ->post(route('users.store'), [
            'email' => $colleague->email,
            'role' => ClubRole::Basic->value,
        ])
        ->assertSessionHasErrors('email');
});

test('creating a user validates the submitted fields', function () {
    $this->actingAs(clubUser())
        ->post(route('users.store'), [
            'name' => '',
            'email' => 'kein-email',
            'locale' => 'fr',
            'role' => 999,
        ])
        ->assertSessionHasErrors(['name', 'email', 'locale', 'role']);
});

test('an admin updates a user and their club role', function () {
    $admin = clubUser();
    $target = clubUser(ClubRole::Basic);

    $this->actingAs($admin)
        ->put(route('users.update', $target), [
            'name' => 'Geänderter Name',
            'email' => 'geaendert@example.test',
            'phone' => null,
            'locale' => 'en',
            'role' => ClubRole::Admin->value,
        ])
        ->assertRedirect();

    $target->refresh();

    expect($target->name)->toBe('Geänderter Name')
        ->and($target->email)->toBe('geaendert@example.test')
        ->and($target->locale)->toBe('en')
        ->and($target->clubRole())->toBe(ClubRole::Admin->value);
});

test('the edit form exposes the club role and the delete permission', function () {
    $admin = clubUser();
    $target = clubUser(ClubRole::Advanced);

    $this->actingAs($admin)
        ->get(route('users.edit', $target))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('users/Edit')
            ->where('user.id', $target->id)
            ->where('user.role', ClubRole::Advanced->value)
            ->where('deletable', true)
            ->has('roles', 3)
            ->has('locales', 2)
        );
});

test('a user of another club cannot be reached', function () {
    $admin = clubUser();
    $outsider = clubUser(ClubRole::Admin, Club::factory()->create());

    $this->actingAs($admin)->get(route('users.edit', $outsider))->assertNotFound();
    $this->actingAs($admin)->delete(route('users.destroy', $outsider))->assertNotFound();
});

test('a superuser account cannot be edited or deleted by a club admin', function () {
    $admin = clubUser();
    $root = clubUser(ClubRole::Admin, attributes: ['admin' => true]);

    $this->actingAs($admin)->get(route('users.edit', $root))->assertForbidden();
    $this->actingAs($admin)->delete(route('users.destroy', $root))->assertForbidden();
});

test('an admin cannot delete their own account from the user list', function () {
    $admin = clubUser();

    $this->actingAs($admin)->delete(route('users.destroy', $admin))->assertForbidden();
});

test('deleting a user who belongs to no other club removes the account', function () {
    $admin = clubUser();
    $target = clubUser(ClubRole::Basic);

    $this->actingAs($admin)
        ->delete(route('users.destroy', $target))
        ->assertRedirect();

    $this->assertDatabaseMissing('users', ['id' => $target->id]);
    $this->assertDatabaseMissing('club_user', ['user_id' => $target->id]);
});

test('deleting a user who belongs to another club only detaches them', function () {
    $admin = clubUser();
    $otherClub = Club::factory()->create();

    $target = clubUser(ClubRole::Basic);
    $target->clubs()->attach($otherClub->id, ['role' => ClubRole::Basic->value]);

    $this->actingAs($admin)
        ->delete(route('users.destroy', $target))
        ->assertRedirect();

    $target->refresh();

    expect($target->exists)->toBeTrue()
        ->and($target->club_id)->toBe($otherClub->id);

    $this->assertDatabaseMissing('club_user', ['user_id' => $target->id, 'club_id' => 1]);
    $this->assertDatabaseHas('club_user', ['user_id' => $target->id, 'club_id' => $otherClub->id]);
});

test('a superuser can log in as another user and back again', function () {
    $root = clubUser(ClubRole::Admin, attributes: ['admin' => true]);
    $target = clubUser(ClubRole::Basic);

    $this->actingAs($root)
        ->post(route('users.impersonate', $target))
        ->assertRedirect(route('dashboard'));

    expect(auth()->id())->toBe($target->id)
        ->and(session('impersonator_id'))->toBe($root->id);

    // The banner names the impersonator, so the shared prop carries them.
    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('auth.impersonator.id', $root->id)
            ->where('auth.impersonator.name', $root->name)
        );

    $this->delete(route('impersonate.destroy'))->assertRedirect(route('users.index'));

    expect(auth()->id())->toBe($root->id)
        ->and(session()->has('impersonator_id'))->toBeFalse();

    $this->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('auth.impersonator', null));
});

test('a club admin who is not a superuser cannot log in as another user', function () {
    $admin = clubUser();
    $target = clubUser(ClubRole::Basic);

    $this->actingAs($admin)
        ->post(route('users.impersonate', $target))
        ->assertForbidden();

    expect(auth()->id())->toBe($admin->id);
});

test('a superuser cannot log in as another superuser', function () {
    $root = clubUser(ClubRole::Admin, attributes: ['admin' => true]);
    $otherRoot = clubUser(ClubRole::Admin, attributes: ['admin' => true]);

    $this->actingAs($root)
        ->post(route('users.impersonate', $otherRoot))
        ->assertForbidden();
});

test('returning from impersonation fails without an impersonator in the session', function () {
    $this->actingAs(clubUser(ClubRole::Basic))
        ->delete(route('impersonate.destroy'))
        ->assertForbidden();
});

test('the club role pivot is what drives admin rights', function () {
    $user = clubUser(ClubRole::Advanced);

    expect($user->hasAdvancedRights())->toBeTrue()
        ->and($user->hasAdminRights())->toBeFalse();

    ClubUser::where('user_id', $user->id)->update(['role' => ClubRole::Admin->value]);

    expect(User::find($user->id)->hasAdminRights())->toBeTrue();
});

test('the avatar falls back to gravatar when no profile image is set', function () {
    $user = clubUser(attributes: ['email' => 'gerald@example.test', 'profile_image' => null]);

    expect($user->avatar)->toBe(
        'https://www.gravatar.com/avatar/'.md5('gerald@example.test').'?d=mp&s=40'
    );
});

test('the avatar resolves to the stored profile image', function () {
    Storage::fake('public');
    Storage::disk('public')->put('profile/foto.png', 'binary');

    $user = clubUser(attributes: ['profile_image' => 'foto.png']);

    expect($user->avatar)->toBe(Storage::disk('public')->url('profile/foto.png'));
});

test('a profile image whose file is gone is cleared and falls back', function () {
    Storage::fake('public');

    $user = clubUser(attributes: ['profile_image' => 'weg.png']);

    expect($user->avatar)->toContain('gravatar.com')
        ->and($user->fresh()->profile_image)->toBeNull();
});

test('the avatar is serialized to the frontend on every user payload', function () {
    Storage::fake('public');
    Storage::disk('public')->put('profile/foto.png', 'binary');

    $admin = clubUser(attributes: ['profile_image' => 'foto.png']);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('auth.user.avatar', Storage::disk('public')->url('profile/foto.png'))
        );
});

test('orphaned profile images are removed', function () {
    Storage::fake('public');
    Storage::disk('public')->put('profile/behalten.png', 'binary');
    Storage::disk('public')->put('profile/verwaist.png', 'binary');

    clubUser(attributes: ['profile_image' => 'behalten.png']);

    expect(User::removeOrphanProfileImages())->toBe(1);

    Storage::disk('public')->assertExists('profile/behalten.png');
    Storage::disk('public')->assertMissing('profile/verwaist.png');
});

test('the current club is shared with its logo url', function () {
    Storage::fake('public');
    Storage::disk('public')->put('logo/wappen.png', 'binary');

    $this->club->update(['name' => 'SSV Brand e.V.', 'logo' => 'wappen.png']);

    $this->actingAs(clubUser())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page
            ->where('currentClub.name', 'SSV Brand e.V.')
            ->where('currentClub.logo_url', Storage::disk('public')->url('logo/wappen.png'))
        );
});

test('a club without a logo shares a null url so the sidebar falls back', function () {
    Storage::fake('public');

    $this->club->update(['logo' => null]);

    $this->actingAs(clubUser())
        ->get(route('dashboard'))
        ->assertInertia(fn ($page) => $page->where('currentClub.logo_url', null));
});

test('a logo whose file is missing shares a null url', function () {
    Storage::fake('public');

    $this->club->update(['logo' => 'weg.png']);

    expect($this->club->logoURL())->toBeNull();
});

test('guests do not resolve a current club', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('currentClub', null));
});

test('orphaned club logos are removed', function () {
    Storage::fake('public');
    Storage::disk('public')->put('logo/behalten.png', 'binary');
    Storage::disk('public')->put('logo/verwaist.png', 'binary');

    $this->club->update(['logo' => 'behalten.png']);

    expect(Club::removeOrphanLogos())->toBe(1);

    Storage::disk('public')->assertExists('logo/behalten.png');
    Storage::disk('public')->assertMissing('logo/verwaist.png');
});
