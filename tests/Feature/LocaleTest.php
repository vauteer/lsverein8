<?php

use App\Enums\ClubRole;
use App\Enums\Locale;
use App\Models\Club;
use App\Models\User;
use Illuminate\Support\Facades\App;

beforeEach(function () {
    $this->club = Club::factory()->create(['id' => 1, 'locale' => Locale::German]);
});

function localeUser(?Locale $locale, ?Club $club = null): User
{
    $club ??= Club::find(1);

    $user = User::factory()->create(['locale' => $locale, 'club_id' => $club->id]);
    $user->clubs()->attach($club->id, ['role' => ClubRole::Admin->value]);

    return $user;
}

test('a user without their own language follows the club', function () {
    $this->club->update(['locale' => Locale::English]);
    $user = localeUser(null);

    expect($user->locale)->toBeNull()
        ->and($user->effectiveLocale())->toBe(Locale::English);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    expect(App::getLocale())->toBe('en');
});

test('a user with their own language overrides the club', function () {
    $this->club->update(['locale' => Locale::German]);
    $user = localeUser(Locale::English);

    expect($user->effectiveLocale())->toBe(Locale::English);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    expect(App::getLocale())->toBe('en');
});

test('changing the club language moves every inheriting user with it', function () {
    $inheriting = localeUser(null);
    $deviating = localeUser(Locale::English);

    $this->club->update(['locale' => Locale::English]);

    expect($inheriting->refresh()->effectiveLocale())->toBe(Locale::English)
        ->and($deviating->refresh()->effectiveLocale())->toBe(Locale::English);

    $this->club->update(['locale' => Locale::German]);

    expect($inheriting->refresh()->effectiveLocale())->toBe(Locale::German)
        // The deviating user keeps English: that is what the column is for.
        ->and($deviating->refresh()->effectiveLocale())->toBe(Locale::English);
});

test('a guest and a user without a club fall back to the app default', function () {
    $this->get(route('login'))->assertOk();
    expect(App::getLocale())->toBe(config('app.locale'));

    $orphan = User::factory()->create(['locale' => null, 'club_id' => null]);

    expect($orphan->effectiveLocale())->toBeNull();
});

test('an admin can clear a users language back to the club default', function () {
    $admin = localeUser(null);
    $target = localeUser(Locale::English);

    $this->actingAs($admin)
        ->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'phone' => null,
            'locale' => '',
            'role' => ClubRole::Basic->value,
        ])
        ->assertSessionHasNoErrors();

    expect($target->refresh()->locale)->toBeNull();
});

test('an invalid language is still rejected', function () {
    $admin = localeUser(null);

    $this->actingAs($admin)
        ->put(route('users.update', $admin), [
            'name' => $admin->name,
            'email' => $admin->email,
            'locale' => 'fr',
            'role' => ClubRole::Admin->value,
        ])
        ->assertSessionHasErrors('locale');
});

test('the club language is required and validated', function () {
    expect(Locale::from('de'))->toBe(Locale::German)
        ->and(Locale::German->label())->toBe(__('German'))
        ->and(Locale::options())->toHaveCount(2)
        ->and(Locale::options()[0])->toBe(['id' => 'de', 'name' => __('German')]);
});

test('users.locale is nullable in the schema', function () {
    // The migration is what makes "follow the club" expressible at all.
    User::factory()->create(['locale' => null]);

    expect(User::whereNull('locale')->exists())->toBeTrue();
});
