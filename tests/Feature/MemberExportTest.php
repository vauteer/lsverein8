<?php

use App\Enums\ClubRole;
use App\Enums\MemberExport;
use App\Models\Club;
use App\Models\Member;
use App\Models\Role;
use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * currentClubId() resolves to 1 on the CLI, so every request is read as though
 * the acting user were working in club 1.
 */
beforeEach(function () {
    $this->club = Club::factory()->create(['id' => 1, 'name' => 'Sportverein']);
    Member::setKeyDate(null);
});

afterEach(fn () => Member::setKeyDate(null));

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
            ->where('exportFormats.0.id', 'addresses-pdf')
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

/**
 * The BLSV member report. Only a club that reports to the association is
 * offered it, and only for the current membership — see
 * MemberExport::isAvailableFor().
 */
function reportingClub(): void
{
    Club::query()->whereKey(1)->update(['blsv_member' => true]);
}

test('the blsv formats are only offered to a club that reports to the association', function () {
    $this->actingAs(exportUser())
        ->get(route('members.index'))
        ->assertInertia(fn ($page) => $page->has('exportFormats', 4));

    reportingClub();

    $this->get(route('members.index'))
        ->assertInertia(fn ($page) => $page
            ->has('exportFormats', 6)
            ->where('exportFormats.4.id', 'blsv-xlsx')
            ->where('exportFormats.5.id', 'blsv')
        );
});

test('the blsv formats are only offered for the members selection', function () {
    reportingClub();

    $this->actingAs(exportUser())
        ->get(route('members.index', ['filter' => 'former']))
        ->assertInertia(fn ($page) => $page->has('exportFormats', 4));
});

test('a hand-typed blsv url is a 404 wherever the menu would hide it', function () {
    $this->actingAs(exportUser());

    // Not a reporting club.
    $this->get(route('members.export', ['format' => 'blsv']))->assertNotFound();

    reportingClub();

    // A reporting club, but a selection the report cannot describe.
    $this->get(route('members.export', ['format' => 'blsv', 'filter' => 'former']))->assertNotFound();
    $this->get(route('members.export', ['format' => 'blsv']))->assertOk();
});

test('the blsv file has the columns of BE{year}_Gesamt, one line per section', function () {
    reportingClub();

    $fussball = Section::factory()->create(['club_id' => 1, 'name' => 'Fussball', 'blsv_id' => 9]);
    $tennis = Section::factory()->create(['club_id' => 1, 'name' => 'Tennis', 'blsv_id' => 32]);

    $member = exportedMember([
        'surname' => 'Grün', 'first_name' => 'Jörg',
        'gender' => 'm', 'birthday' => '1962-07-01',
    ]);
    // Two sections, and the higher number is attached first: the file is
    // written in BLSV order regardless.
    $member->sections()->attach($tennis->id, ['from' => '2016-01-01']);
    $member->sections()->attach($fussball->id, ['from' => '2016-01-01']);
    // The same section a second time with an earlier period — must not be
    // reported twice.
    $member->sections()->attach($fussball->id, ['from' => '2010-01-01', 'to' => '2012-12-31']);

    $response = $this->actingAs(exportUser())
        ->get(route('members.export', ['format' => 'blsv']));

    $response->assertOk()
        ->assertHeader('content-type', 'text/comma-separated-values; charset=UTF-8')
        ->assertDownload('BE'.now()->format('Y').'_Nachmeldung_'.now()->format('dm').'.csv');

    $csv = mb_convert_encoding($response->getContent(), 'UTF-8', 'ISO-8859-1');

    expect($csv)->toBe(
        "Titel;Name;Vorname;Namenszusatz;Geschlecht;Geburtsdatum;Spartenkennzeichen\r\n".
        ";Grün;Jörg;;m;\"01.07.62\";9\r\n".
        ";Grün;Jörg;;m;\"01.07.62\";32\r\n"
    );

    // Latin-1 like every other BLSV file.
    expect(mb_check_encoding($response->getContent(), 'UTF-8'))->toBeFalse();
});

test('a member in no numbered section produces no line', function () {
    reportingClub();

    $unnumbered = Section::factory()->create(['club_id' => 1, 'name' => 'Wandern', 'blsv_id' => null]);
    $numbered = Section::factory()->create(['club_id' => 1, 'name' => 'Fussball', 'blsv_id' => 9]);

    exportedMember(['surname' => 'Ohne'])->sections()->attach($unnumbered->id, ['from' => '2016-01-01']);
    exportedMember(['surname' => 'Gemeldet'])->sections()->attach($numbered->id, ['from' => '2016-01-01']);

    $csv = mb_convert_encoding(
        $this->actingAs(exportUser())->get(route('members.export', ['format' => 'blsv']))->getContent(),
        'UTF-8', 'ISO-8859-1'
    );

    expect($csv)->toContain('Gemeldet')->not->toContain('Ohne');
});

test('a section the member has already left is not reported', function () {
    reportingClub();

    $section = Section::factory()->create(['club_id' => 1, 'name' => 'Fussball', 'blsv_id' => 9]);
    $member = exportedMember(['surname' => 'Meier']);
    $member->sections()->attach($section->id, ['from' => '2010-01-01', 'to' => '2012-12-31']);

    $csv = mb_convert_encoding(
        $this->actingAs(exportUser())->get(route('members.export', ['format' => 'blsv']))->getContent(),
        'UTF-8', 'ISO-8859-1'
    );

    // Header only.
    expect(trim($csv))->toBe('Titel;Name;Vorname;Namenszusatz;Geschlecht;Geburtsdatum;Spartenkennzeichen');
});

test('the excel file is laid out like the blsv template', function () {
    reportingClub();

    $fussball = Section::factory()->create(['club_id' => 1, 'name' => 'Fussball', 'blsv_id' => 9]);
    $tennis = Section::factory()->create(['club_id' => 1, 'name' => 'Tennis', 'blsv_id' => 32]);

    $member = exportedMember([
        'surname' => 'Grün', 'first_name' => 'Jörg',
        'gender' => 'm', 'birthday' => '1993-11-20',
    ]);
    $member->sections()->attach($tennis->id, ['from' => '2016-01-01']);
    $member->sections()->attach($fussball->id, ['from' => '2016-01-01']);

    $response = $this->actingAs(exportUser())
        ->get(route('members.export', ['format' => 'blsv-xlsx']));

    $response->assertOk()
        // A binary type, so Laravel appends no charset.
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertDownload('BE'.now()->format('Y').'_Nachmeldung_'.now()->format('dm').'.xlsx');

    [$sheet, $styles] = xlsxParts($response->getContent());

    // Header in the BLSV's column order, and the two columns that are not
    // text: a date serial (20.11.1993 is 34293, the value the template
    // carries for that birthday) and a bare number for the section.
    expect(xlsxRows($sheet))->toBe([
        ['Titel', 'Name', 'Vorname', 'Namenszusatz', 'Geschlecht', 'Geburtsdatum', 'Spartenkennzeichen'],
        ['', 'Grün', 'Jörg', '', 'm', '34293', '9'],
        ['', 'Grün', 'Jörg', '', 'm', '34293', '32'],
    ]);

    // The built-in short date format: numFmtId 14 with no custom format
    // registered alongside it, exactly what the template carries. German
    // Excel renders that as TT.MM.JJJJ.
    expect($styles)->toContain('numFmtId="14"')
        ->toContain('<numFmts count="0">');

    // Titel and Namenszusatz are absent cells, not empty strings: the
    // template writes no <c> for them at all.
    expect($sheet)->not->toContain('<c r="A2"')
        ->and($sheet)->not->toContain('<c r="D2"');
});

test('the excel file and the blsv csv describe the club identically', function () {
    reportingClub();

    $section = Section::factory()->create(['club_id' => 1, 'name' => 'Fussball', 'blsv_id' => 9]);
    $other = Section::factory()->create(['club_id' => 1, 'name' => 'Tennis', 'blsv_id' => 32]);

    // 'f' is the stored value; the BLSV column says 'w' — see Gender::blsvValue().
    foreach ([['Meier', 'Anna', 'f'], ['Huber', 'Bert', 'm'], ['Groß', 'Cäcilia', 'f']] as [$surname, $first, $gender]) {
        $member = exportedMember(['surname' => $surname, 'first_name' => $first, 'gender' => $gender]);
        $member->sections()->attach($section->id, ['from' => '2016-01-01']);
    }
    // One of them in a second section, so the row counts are not simply the
    // member count on both sides.
    Member::query()->where('surname', 'Meier')->firstOrFail()
        ->sections()->attach($other->id, ['from' => '2016-01-01']);

    $this->actingAs(exportUser());

    $csv = mb_convert_encoding(
        $this->get(route('members.export', ['format' => 'blsv']))->getContent(),
        'UTF-8', 'ISO-8859-1'
    );
    [$sheet] = xlsxParts($this->get(route('members.export', ['format' => 'blsv-xlsx']))->getContent());

    // Same rows, same order — only the container and the two typed columns
    // differ, so the CSV is rebuilt from the sheet and compared verbatim.
    $rebuilt = collect(xlsxRows($sheet))
        ->map(fn (array $row, int $i): string => $i === 0
            ? implode(';', $row)
            : ';'.$row[1].';'.$row[2].';;'.$row[4].';"'
                .Carbon::create(1899, 12, 30)->addDays((int) $row[5])->format('d.m.y').'";'.$row[6])
        ->implode("\r\n")."\r\n";

    expect($rebuilt)->toBe($csv);
});
