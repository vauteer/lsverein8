<?php

use App\Enums\ClubRole;
use App\Models\Club;
use App\Models\Member;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Facades\File;

/**
 * currentClubId() resolves to 1 on the CLI, so every request is read as though
 * the acting user were working in club 1.
 */
beforeEach(function () {
    $this->club = Club::factory()->create([
        'id' => 1,
        'name' => 'Sportverein',
        'blsv_member' => true,
    ]);
    Member::$_keyDate = null;
    File::ensureDirectoryExists(storage_path('downloads'));
});

afterEach(fn () => Member::$_keyDate = null);

function blsvUser(ClubRole $role = ClubRole::Admin, ?Club $club = null): User
{
    $club ??= Club::findOrFail(1);

    $user = User::factory()->create(['club_id' => $club->id]);
    $user->clubs()->attach($club->id, ['role' => $role->value]);

    return $user;
}

/** A current member of the club, assigned to the given BLSV section. */
function blsvMember(Club $club, Section $section, string $surname, string $birthday, string $gender): Member
{
    $member = Member::factory()->ofClub($club->id)->create([
        'surname' => $surname,
        'birthday' => $birthday,
        'gender' => $gender,
    ]);
    $member->memberships()->attach($club->id, ['from' => '2016-01-01']);
    $member->sections()->attach($section->id, ['from' => '2016-01-01']);

    return $member;
}

test('guests are redirected to the login page', function () {
    $this->get(route('clubs.blsv-statistic', $this->club))->assertRedirect(route('login'));
});

test('the page lists the pdf, the total csv and one csv per populated section', function () {
    $football = Section::factory()->create(['club_id' => 1, 'blsv_id' => 9, 'name' => 'Fussball']);
    $tennis = Section::factory()->create(['club_id' => 1, 'blsv_id' => 32, 'name' => 'Tennis']);
    // No blsv_id, so it belongs to no BLSV section and gets no file of its own.
    $internal = Section::factory()->create(['club_id' => 1, 'blsv_id' => null, 'name' => 'Vorstand']);

    blsvMember($this->club, $football, 'Kicker', '1990-01-01', 'm');
    blsvMember($this->club, $internal, 'Vorsitzende', '1975-01-01', 'f');

    $this->actingAs(blsvUser());

    $this->get(route('clubs.blsv-statistic', $this->club))
        ->assertInertia(fn ($page) => $page
            ->component('clubs/BlsvStatistic')
            ->where('clubName', 'Sportverein')
            ->where('keyDate', now()->startOfYear()->format('d.m.Y'))
            // The two summaries first, then only the section that has members;
            // Tennis is empty and so is left out entirely.
            ->where('downloads.0.name', 'Alters-Statistik')
            ->where('downloads.1.name', 'Alle Sparten')
            ->where('downloads.2.name', 'Fussball')
            ->has('downloads', 3));

    expect($tennis->fresh())->not->toBeNull();
});

test('the csv holds the section members and the total holds every member', function () {
    $football = Section::factory()->create(['club_id' => 1, 'blsv_id' => 9, 'name' => 'Fussball']);
    $kicker = blsvMember($this->club, $football, 'Kicker', '1990-01-01', 'm');

    $this->actingAs(blsvUser());
    $this->get(route('clubs.blsv-statistic', $this->club))->assertOk();

    $year = now()->startOfYear()->year;
    $sectionCsv = file_get_contents(storage_path("downloads/1_BE{$year}_Fussball.csv"));
    $totalCsv = file_get_contents(storage_path("downloads/1_BE{$year}_Gesamt.csv"));

    expect($sectionCsv)->toContain($kicker->surname)
        // Name;Vorname;Namenszusatz;Geschlecht;"Geburtstag";Spartenkennzeichen
        ->and($sectionCsv)->toContain(';m;"01.01.90";9')
        ->and($totalCsv)->toStartWith('Titel;Name;Vorname;Namenszusatz;Geschlecht;Geburtsdatum;Spartenkennzeichen')
        ->and($totalCsv)->toContain($kicker->surname);
});

test('the generated files are downloadable through the download route', function () {
    $football = Section::factory()->create(['club_id' => 1, 'blsv_id' => 9, 'name' => 'Fussball']);
    blsvMember($this->club, $football, 'Kicker', '1990-01-01', 'm');

    $this->actingAs(blsvUser());

    $downloads = $this->get(route('clubs.blsv-statistic', $this->club))
        ->viewData('page')['props']['downloads'];

    foreach ($downloads as $download) {
        $this->get($download['href'])->assertOk();
    }
});

test('a section name with a space survives the href', function () {
    $section = Section::factory()->create(['club_id' => 1, 'blsv_id' => 34, 'name' => 'Fitness und Turnen']);
    blsvMember($this->club, $section, 'Turnerin', '1990-01-01', 'f');

    $this->actingAs(blsvUser());

    $downloads = $this->get(route('clubs.blsv-statistic', $this->club))
        ->viewData('page')['props']['downloads'];

    $year = now()->startOfYear()->year;

    expect($downloads[2]['href'])->toBe("/downloads/BE{$year}_Fitness%20und%20Turnen.csv");

    $this->get($downloads[2]['href'])->assertOk();
});

test('only an admin of the club may build it', function () {
    $this->actingAs(blsvUser(ClubRole::Advanced));

    $this->get(route('clubs.blsv-statistic', $this->club))->assertForbidden();
});

test('a club that is not in the BLSV has no statistic', function () {
    $this->club->update(['blsv_member' => false]);

    $this->actingAs(blsvUser());

    $this->get(route('clubs.blsv-statistic', $this->club))->assertForbidden();
});

test('another club is refused even for a root account', function () {
    // Member and Section are club-scoped, so building from another club's page
    // would file this club's members under that club's name.
    $other = Club::factory()->create(['name' => 'Feuerwehr', 'blsv_member' => true]);

    $root = blsvUser();
    $root->update(['admin' => true]);
    $root->clubs()->attach($other->id, ['role' => ClubRole::Admin->value]);

    $this->actingAs($root);

    $this->get(route('clubs.blsv-statistic', $this->club))->assertOk();
    $this->get(route('clubs.blsv-statistic', $other))->assertForbidden();
});

test('the club page offers the statistic only where it may be built', function () {
    $this->actingAs(blsvUser());

    $this->get(route('clubs.edit', $this->club))
        ->assertInertia(fn ($page) => $page->where('blsvStatistic', true));

    $this->club->update(['blsv_member' => false]);

    $this->get(route('clubs.edit', $this->club))
        ->assertInertia(fn ($page) => $page->where('blsvStatistic', false));
});
