<?php

use App\Enums\LandingPage;
use App\Enums\Locale;
use App\Models\Club;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'landing_page' => LandingPage::Dashboard->value,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors(['password' => 'Das Passwort ist falsch.'])
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});

test('a profile photo can be uploaded', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'landing_page' => LandingPage::Dashboard->value,
            'profile_image' => UploadedFile::fake()->image('avatar.jpg'),
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $filename = $user->refresh()->profile_image;

    expect($filename)->not->toBeNull();
    Storage::disk('public')->assertExists(User::profileStoragePath($filename));
});

test('replacing the photo deletes the file it replaced', function () {
    Storage::fake('public');
    Storage::disk('public')->put(User::profileStoragePath('old.jpg'), 'fake image contents');
    $user = User::factory()->create(['profile_image' => 'old.jpg']);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'landing_page' => LandingPage::Dashboard->value,
            'profile_image' => UploadedFile::fake()->image('avatar.jpg'),
        ])
        ->assertSessionHasNoErrors();

    $filename = $user->refresh()->profile_image;

    expect($filename)->not->toBe('old.jpg');
    Storage::disk('public')->assertMissing(User::profileStoragePath('old.jpg'));
    Storage::disk('public')->assertExists(User::profileStoragePath($filename));
});

test('the photo can be removed again', function () {
    Storage::fake('public');
    Storage::disk('public')->put(User::profileStoragePath('old.jpg'), 'fake image contents');
    $user = User::factory()->create(['profile_image' => 'old.jpg']);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'landing_page' => LandingPage::Dashboard->value,
            'remove_profile_image' => '1',
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->profile_image)->toBeNull();
    Storage::disk('public')->assertMissing(User::profileStoragePath('old.jpg'));
});

test('removing wins over a file sent in the same request', function () {
    Storage::fake('public');
    $user = User::factory()->create(['profile_image' => 'old.jpg']);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'landing_page' => LandingPage::Dashboard->value,
            'remove_profile_image' => '1',
            'profile_image' => UploadedFile::fake()->image('avatar.jpg'),
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->profile_image)->toBeNull();
});

test('the profile photo must be an image and stay under 2 MB', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'landing_page' => LandingPage::Dashboard->value,
            'profile_image' => UploadedFile::fake()->create('document.pdf', 100),
        ])
        ->assertSessionHasErrors('profile_image');

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'landing_page' => LandingPage::Dashboard->value,
            'profile_image' => UploadedFile::fake()->image('huge.jpg')->size(3000),
        ])
        ->assertSessionHasErrors('profile_image');

    expect($user->refresh()->profile_image)->toBeNull();
});

test('an update without a photo leaves the existing one alone', function () {
    Storage::fake('public');
    Storage::disk('public')->put(User::profileStoragePath('keep.jpg'), 'fake image contents');
    $user = User::factory()->create(['profile_image' => 'keep.jpg']);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Neuer Name',
            'email' => $user->email,
            'landing_page' => LandingPage::Dashboard->value,
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->profile_image)->toBe('keep.jpg')
        ->and($user->name)->toBe('Neuer Name');
    Storage::disk('public')->assertExists(User::profileStoragePath('keep.jpg'));
});

test('the edit page reports whether a custom photo exists', function () {
    $without = User::factory()->create();

    $this->actingAs($without)
        ->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page->where('hasProfileImage', false));

    $with = User::factory()->create(['profile_image' => 'own.jpg']);

    $this->actingAs($with)
        ->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page->where('hasProfileImage', true));
});

test('the orphan sweep leaves the directory .gitignore alone', function () {
    // storage/app/public/profile/.gitignore is what keeps the directory in the
    // repository. Nothing references it, so an unfiltered sweep deletes it —
    // which is exactly what happened before the dotfile guard.
    Storage::disk('public')->put(User::profileStoragePath('.gitignore'), "*\n!.gitignore\n");
    Storage::disk('public')->put(User::profileStoragePath('orphan.jpg'), 'nobody points here');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Neuer Name',
            'email' => $user->email,
            'landing_page' => LandingPage::Dashboard->value,
        ])
        ->assertSessionHasNoErrors();

    Storage::disk('public')->assertExists(User::profileStoragePath('.gitignore'));
    Storage::disk('public')->assertMissing(User::profileStoragePath('orphan.jpg'));
});

test('the profile page offers the language with the club default', function () {
    $club = Club::factory()->create(['id' => 1, 'locale' => Locale::German]);
    $user = User::factory()->create(['locale' => null, 'club_id' => $club->id]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page
            ->where('locale', null)
            ->has('locales', 2));

    $user->update(['locale' => Locale::English]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page->where('locale', 'en'));
});

test('a user sets and clears their own language', function () {
    $club = Club::factory()->create(['id' => 1, 'locale' => Locale::German]);
    $user = User::factory()->create(['locale' => null, 'club_id' => $club->id]);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'landing_page' => LandingPage::Dashboard->value,
            'locale' => 'en',
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->locale)->toBe(Locale::English)
        ->and($user->effectiveLocale())->toBe(Locale::English);

    // Empty string is turned into null by ConvertEmptyStringsToNull, which is
    // what the "(club language)" option submits.
    $this->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => $user->email,
        'landing_page' => LandingPage::Dashboard->value,
        'locale' => '',
    ])->assertSessionHasNoErrors();

    expect($user->refresh()->locale)->toBeNull()
        ->and($user->effectiveLocale())->toBe(Locale::German);
});

test('the profile rejects a language that does not exist', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'landing_page' => LandingPage::Dashboard->value,
            'locale' => 'fr',
        ])
        ->assertSessionHasErrors('locale');
});

test('a user chooses the screen they land on', function () {
    $user = User::factory()->create(['landing_page' => LandingPage::Dashboard]);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page
            ->where('landingPage', 'dashboard')
            ->has('landingPages', 2)
        );

    $this->patch(route('profile.update'), [
        'name' => $user->name,
        'email' => $user->email,
        'landing_page' => LandingPage::Members->value,
    ])->assertSessionHasNoErrors();

    expect($user->refresh()->landingPage())->toBe(LandingPage::Members);
});

test('the profile rejects a start page that does not exist', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'landing_page' => 'sections',
        ])
        ->assertSessionHasErrors('landing_page');

    expect($user->refresh()->landingPage())->toBe(LandingPage::Dashboard);
});
