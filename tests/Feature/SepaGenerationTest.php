<?php

use App\Models\Club;
use App\Models\Debit;
use App\Models\Member;
use App\Models\Section;
use App\Models\Subscription;

beforeEach(function () {
    $this->club = Club::factory()->create(['id' => 1, 'sepa_mandate_date' => '2015-01-01']);
    Member::setKeyDate(null);
    File::ensureDirectoryExists(storage_path('downloads'));
});

afterEach(fn () => Member::setKeyDate(null));

function memberWithDebit(Club $club): Member
{
    $member = Member::factory()->ofClub($club)->payingByAccount()->create();
    $member->memberships()->attach($club->id, ['from' => '2016-01-01', 'to' => null]);

    return $member;
}

it('generates the SEPA xml and the pdf cover sheet', function () {
    $member = memberWithDebit($this->club);

    $downloads = Subscription::generateSepa([[
        'member_id' => $member->id,
        'amount' => 42.50,
        'transfer_text' => 'Beitrag <AJ> <VN> <NN>',
    ]], Carbon\Carbon::parse('2024-03-01'));

    expect($downloads)->toHaveCount(2);

    $xml = file_get_contents(storage_path("downloads/{$this->club->id}_sepa.xml"));
    $pdf = file_get_contents(storage_path("downloads/{$this->club->id}_Abbuchungen.pdf"));

    expect($xml)->toContain('<?xml version="1.0" encoding="utf-8"?>')
        // pain.008.001.02 wird am 14.11.2026 abgeschaltet; die Banken nehmen dann
        // nur noch .08 an, wo der BIC BICFI heisst.
        ->and($xml)->toContain('urn:iso:std:iso:20022:tech:xsd:pain.008.001.08')
        ->and($xml)->not->toContain('pain.008.001.02')
        ->and($xml)->toContain('<BICFI>COBADEFFXXX</BICFI>')
        ->and($xml)->not->toContain('<BIC>')
        ->and($xml)->toContain('DE89370400440532013000')
        ->and($xml)->toContain('42.50')
        // <AJ>/<VN>/<NN> are replaced with year, first name and surname
        ->and($xml)->toContain("Beitrag 2024 {$member->first_name} {$member->surname}")
        ->and($xml)->toContain('2024-03-01')
        ->and($pdf)->toStartWith('%PDF-');

    // The sum sat in Footer(), which Fpdf renders at every page break while
    // the list is still being totted up — page one of a two-page collection
    // showed part of it, looking like the whole. It is one line under the
    // last row now, in the app's own number format.
    expect(pdfText($pdf))->toContain('Summe')
        ->and(pdfText($pdf))->toContain('42,50 EUR');
});

it('splits subscription debits from outstanding payments', function () {
    // explicit surnames: faker's pool is small enough that two members can collide
    $paying = memberWithDebit($this->club);
    $paying->update(['surname' => 'Zahlerin']);
    // No bank details, so the club has to bill this one by hand.
    $invoiced = Member::factory()->ofClub($this->club)->create([
        'surname' => 'Rechnungsempfaenger',
    ]);
    $invoiced->memberships()->attach($this->club->id, ['from' => '2016-01-01', 'to' => null]);

    $subscription = Subscription::factory()->create([
        'club_id' => $this->club->id,
        'name' => 'Jahresbeitrag',
        'amount' => 30,
    ]);
    $paying->subscriptions()->attach($subscription->id);
    $invoiced->subscriptions()->attach($subscription->id);

    Member::setKeyDate(Carbon\Carbon::parse('2024-06-01'));

    $result = Subscription::debit([$subscription->id], Carbon\Carbon::parse('2024-03-01'));

    expect($result['outStandings'])->toHaveCount(1)
        ->and($result['outStandings'][0]['name'])->toBe($invoiced->first_name.' '.$invoiced->surname)
        ->and($result['outStandings'][0]['paymentMethod'])->toBe('Rechnung')
        ->and($result['downloads'])->toHaveCount(2);

    // only the direct-debit member reaches the SEPA file
    $xml = file_get_contents(storage_path("downloads/{$this->club->id}_sepa.xml"));
    expect($xml)->toContain($paying->surname)
        ->and($xml)->not->toContain($invoiced->surname);
});

it('clears the debits it has collected', function () {
    $member = memberWithDebit($this->club);
    Debit::factory()->create(['member_id' => $member->id, 'due_at' => now()->subDay(), 'amount' => 10]);
    Debit::factory()->create(['member_id' => $member->id, 'due_at' => now()->addMonth(), 'amount' => 20]);

    $result = Debit::debit(Carbon\Carbon::parse(now()->toDateString()));

    expect($result['downloads'])->toHaveCount(2)
        ->and(Debit::count())->toBe(1)
        ->and(Debit::first()->amount)->toEqual(20);
});

it('builds the blsv statistic with csv files and a pdf', function () {
    $section = Section::factory()->create(['club_id' => $this->club->id, 'blsv_id' => 9, 'name' => 'Fussball']);
    $member = Member::factory()->ofClub($this->club)->create(['birthday' => '1990-01-01', 'gender' => 'm']);
    $member->memberships()->attach($this->club->id, ['from' => '2016-01-01', 'to' => null]);
    $member->sections()->attach($section->id, ['from' => '2016-01-01']);

    $files = $this->club->buildBlsvStatistic();

    expect($files)->not->toBeEmpty();

    $year = now()->startOfYear()->year;
    $sectionCsv = file_get_contents(storage_path("downloads/{$this->club->id}_BE{$year}_Fussball.csv"));
    $totalCsv = file_get_contents(storage_path("downloads/{$this->club->id}_BE{$year}_Gesamt.csv"));
    $pdf = file_get_contents(storage_path("downloads/{$this->club->id}_blsv_stat.pdf"));

    expect($sectionCsv)->toContain($member->surname)
        ->and($sectionCsv)->toContain(';m;')
        ->and($totalCsv)->toContain('Spartenkennzeichen')
        ->and($pdf)->toStartWith('%PDF-');
});

it('lists a section by surname and counts a member of it once', function () {
    $section = Section::factory()->create(['club_id' => $this->club->id, 'blsv_id' => 9, 'name' => 'Fussball']);
    $second = Section::factory()->create(['club_id' => $this->club->id, 'blsv_id' => 9, 'name' => 'Fussball II']);

    foreach (['Zenz', 'Aigner'] as $surname) {
        $member = Member::factory()->ofClub($this->club)->create([
            'surname' => $surname, 'birthday' => '1990-01-01', 'gender' => 'm',
        ]);
        $member->memberships()->attach($this->club->id, ['from' => '2016-01-01', 'to' => null]);
        // Two spells in one section and a second section under the same
        // blsv_id: the Meldung still knows one member of section 9.
        $member->sections()->attach($section->id, ['from' => '2016-01-01', 'to' => '2018-12-31']);
        $member->sections()->attach($section->id, ['from' => '2019-01-01']);
        $member->sections()->attach($second->id, ['from' => '2019-01-01']);
    }

    $this->club->buildBlsvStatistic();

    $year = now()->startOfYear()->year;
    $lines = array_values(array_filter(explode("\n", str_replace("\r", '',
        file_get_contents(storage_path("downloads/{$this->club->id}_BE{$year}_Fussball.csv"))
    ))));

    expect($lines)->toHaveCount(2)
        ->and($lines[0])->toContain('Aigner')
        ->and($lines[1])->toContain('Zenz');
});

it('escapes a section name that would break the export path', function () {
    $section = Section::factory()->create([
        'club_id' => $this->club->id, 'blsv_id' => 9, 'name' => 'Turnen/Leichtathletik',
    ]);
    $member = Member::factory()->ofClub($this->club)->create(['birthday' => '1990-01-01']);
    $member->memberships()->attach($this->club->id, ['from' => '2016-01-01', 'to' => null]);
    $member->sections()->attach($section->id, ['from' => '2016-01-01']);

    $files = $this->club->buildBlsvStatistic();

    $year = now()->startOfYear()->year;
    $written = "{$this->club->id}_BE{$year}_Turnen-Leichtathletik.csv";

    // The slash became a dash instead of a directory, and the download link
    // names the same file the writer produced.
    expect(storage_path("downloads/{$written}"))->toBeFile()
        ->and(glob(storage_path("downloads/{$this->club->id}_BE{$year}_Turnen")))->toBeEmpty()
        ->and(collect($files)->pluck('href'))->toContain(
            route('downloads.show', "BE{$year}_Turnen-Leichtathletik.csv", absolute: false)
        );
});

it('keeps two sections apart when their names escape to the same file', function () {
    foreach ([['Turnen/Leichtathletik', 9], ['Turnen-Leichtathletik', 12]] as [$name, $blsvId]) {
        $section = Section::factory()->create([
            'club_id' => $this->club->id, 'blsv_id' => $blsvId, 'name' => $name,
        ]);
        $member = Member::factory()->ofClub($this->club)->create(['birthday' => '1990-01-01']);
        $member->memberships()->attach($this->club->id, ['from' => '2016-01-01', 'to' => null]);
        $member->sections()->attach($section->id, ['from' => '2016-01-01']);
    }

    $this->club->buildBlsvStatistic();

    $year = now()->startOfYear()->year;

    // Without the blsv_id suffix the second file would overwrite the first and
    // the club would submit one section short.
    expect(storage_path("downloads/{$this->club->id}_BE{$year}_Turnen-Leichtathletik.csv"))->toBeFile()
        ->and(storage_path("downloads/{$this->club->id}_BE{$year}_Turnen-Leichtathletik_12.csv"))->toBeFile();
});

it('leaves the divers column out of the statistic until a member needs it', function () {
    $section = Section::factory()->create(['club_id' => $this->club->id, 'blsv_id' => 9, 'name' => 'Fussball']);
    $member = Member::factory()->ofClub($this->club)->create(['birthday' => '1990-01-01', 'gender' => 'm']);
    $member->memberships()->attach($this->club->id, ['from' => '2016-01-01', 'to' => null]);
    $member->sections()->attach($section->id, ['from' => '2016-01-01']);

    $this->club->buildBlsvStatistic();
    $pdf = pdfText(file_get_contents(storage_path("downloads/{$this->club->id}_blsv_stat.pdf")));

    // The club has submitted a two-column sheet for years; a third one that is
    // always zero would change the form for nothing.
    expect($pdf)->toContain('Altersgruppe')
        ->and($pdf)->toContain('Weiblich')
        ->and($pdf)->not->toContain('Divers');
});

it('cuts in the divers column and reports d once a member is diverse', function () {
    $section = Section::factory()->create(['club_id' => $this->club->id, 'blsv_id' => 9, 'name' => 'Fussball']);
    $member = Member::factory()->ofClub($this->club)->create(['birthday' => '1990-01-01', 'gender' => 'd']);
    $member->memberships()->attach($this->club->id, ['from' => '2016-01-01', 'to' => null]);
    $member->sections()->attach($section->id, ['from' => '2016-01-01']);

    $this->club->buildBlsvStatistic();

    $year = now()->startOfYear()->year;
    $sectionCsv = file_get_contents(storage_path("downloads/{$this->club->id}_BE{$year}_Fussball.csv"));
    $pdf = pdfText(file_get_contents(storage_path("downloads/{$this->club->id}_blsv_stat.pdf")));

    expect($sectionCsv)->toContain(';d;')
        ->and($pdf)->toContain('Divers')
        // The member is counted in its own column, not among the women.
        ->and($pdf)->toContain('Weiblich');
});
