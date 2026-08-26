<?php

use App\Enums\ClubRole;
use App\Enums\MemberExport;
use App\Models\Club;
use App\Models\Member;
use App\Models\Role;
use App\Models\Section;
use App\Models\User;

/**
 * currentClubId() resolves to 1 on the CLI, so every request is read as though
 * the acting user were working in club 1.
 */
beforeEach(function () {
    $this->club = Club::factory()->create(['id' => 1, 'name' => 'Sportverein']);
    Member::$_keyDate = null;
});

afterEach(fn () => Member::$_keyDate = null);

function exportUser(ClubRole $role = ClubRole::Admin): User
{
    $user = User::factory()->create(['club_id' => 1]);
    $user->clubs()->attach(1, ['role' => $role->value]);

    return $user;
}

function exportedMember(array $attributes = [], string $from = '2016-01-01'): Member
{
    $member = Member::factory()->ofClub(1)->create($attributes);
    $member->memberships()->attach(1, ['from' => $from, 'to' => null]);

    return $member;
}

test('guests are redirected to the login page', function () {
    $this->get(route('members.export', ['format' => 'csv']))->assertRedirect(route('login'));
});

test('every format is offered on the index', function () {
    $this->actingAs(exportUser())
        ->get(route('members.index'))
        ->assertInertia(fn ($page) => $page
            ->has('exportFormats', 4)
            ->where('exportFormats.0.id', 'pdf')
        );
});

test('an unknown format is a 404 rather than an empty file', function () {
    $this->actingAs(exportUser())
        ->get(route('members.export', ['format' => 'docx']))
        ->assertNotFound();
});

test('the csv carries the current selection and nothing from another club', function () {
    $section = Section::factory()->create(['club_id' => 1, 'name' => 'Fussball']);
    $role = Role::factory()->create(['club_id' => 1, 'name' => 'Kassier']);

    $member = exportedMember(['surname' => 'Meier', 'first_name' => 'Anna', 'city' => 'Noerdlingen']);
    $member->sections()->attach($section->id, ['from' => '2016-01-01']);
    $member->roles()->attach($role->id, ['from' => '2016-01-01']);

    // Another club's member must not appear, and a former member is outside
    // the default selection.
    Member::factory()->create(['surname' => 'Fremd']);
    exportedMember(['surname' => 'Ehemalig'], from: '2000-01-01')
        ->memberships()->updateExistingPivot(1, ['to' => '2005-01-01']);

    $response = $this->actingAs(exportUser())
        ->get(route('members.export', ['format' => 'csv']));

    $response->assertOk()
        ->assertHeader('content-type', 'text/comma-separated-values; charset=UTF-8');

    $csv = mb_convert_encoding($response->getContent(), 'UTF-8', 'ISO-8859-1');

    expect($csv)->toContain('Meier')
        ->toContain('Fussball')
        ->toContain('Kassier')
        ->not->toContain('Fremd')
        ->not->toContain('Ehemalig');
});

test('the csv is latin-1, as the spreadsheets on the other end expect', function () {
    exportedMember(['surname' => 'Grün', 'first_name' => 'Jörg']);

    $csv = $this->actingAs(exportUser())
        ->get(route('members.export', ['format' => 'csv']))
        ->getContent();

    // Not valid UTF-8 on the wire, but decodes cleanly as Latin-1 — the same
    // encoding Club::getBLSVStatistic() writes.
    expect(mb_check_encoding($csv, 'UTF-8'))->toBeFalse()
        ->and(mb_convert_encoding($csv, 'UTF-8', 'ISO-8859-1'))->toContain('Grün');
});

test('the filename names the selection and the year', function () {
    exportedMember();

    $response = $this->actingAs(exportUser())
        ->get(route('members.export', ['format' => 'csv', 'filter' => 'former', 'year' => 2020]));

    $response->assertDownload('ex-mitglieder-2020.csv');
});

test('both pdfs are produced for the selection', function () {
    $role = Role::factory()->create(['club_id' => 1, 'name' => 'Kassier']);
    $member = exportedMember(['surname' => 'Meier']);
    $member->roles()->attach($role->id, ['from' => '2016-01-01']);

    $this->actingAs(exportUser());

    foreach ([MemberExport::Addresses, MemberExport::Roles] as $format) {
        $response = $this->get(route('members.export', ['format' => $format->value]));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');

        expect($response->getContent())->toStartWith('%PDF-');
    }
});

test('the vcard export carries one card per member of the selection', function () {
    exportedMember(['surname' => 'Meier', 'first_name' => 'Anna', 'email' => 'anna@example.test']);
    exportedMember(['surname' => 'Huber', 'first_name' => 'Bert', 'email' => null, 'phone' => null]);

    $response = $this->actingAs(exportUser())
        ->get(route('members.export', ['format' => 'vcf']));

    $response->assertOk()->assertHeader('content-type', 'text/vcard; charset=UTF-8');

    $vcf = $response->getContent();

    expect(substr_count($vcf, 'BEGIN:VCARD'))->toBe(2)
        ->and(substr_count($vcf, 'END:VCARD'))->toBe(2)
        ->and($vcf)->toContain('FN:Anna Meier')
        ->and($vcf)->toContain('EMAIL;type=INTERNET;type=HOME;type=pref:anna@example.test')
        // An empty field is left out rather than exported blank, which some
        // address books import as an empty contact entry.
        ->and($vcf)->not->toContain('EMAIL;type=INTERNET;type=HOME;type=pref:'."\n");
});

test('the year in the request moves the key date for the export too', function () {
    exportedMember(['surname' => 'Meier', 'birthday' => '1980-06-01'], from: '2000-01-01');

    $this->actingAs(exportUser());

    $now = mb_convert_encoding(
        $this->get(route('members.export', ['format' => 'csv']))->getContent(),
        'UTF-8', 'ISO-8859-1'
    );
    $then = mb_convert_encoding(
        $this->get(route('members.export', ['format' => 'csv', 'year' => now()->year - 5]))->getContent(),
        'UTF-8', 'ISO-8859-1'
    );

    // Age and membership years are computed against the chosen key date, the
    // same way the screen computes them.
    $age = fn (string $csv): int => (int) explode(',', explode("\n", trim($csv))[1])[7];

    expect($age($then))->toBe($age($now) - 5);
});

test('a read-only account may export what it may already read', function () {
    exportedMember();

    $this->actingAs(exportUser(ClubRole::Basic))
        ->get(route('members.export', ['format' => 'csv']))
        ->assertOk();
});
