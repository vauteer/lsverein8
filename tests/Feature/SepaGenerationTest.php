<?php

use App\Models\Club;
use App\Models\Debit;
use App\Models\Member;
use App\Models\Section;
use App\Models\Subscription;

beforeEach(function () {
    $this->club = Club::factory()->create(['id' => 1, 'sepa_mandate_date' => '2015-01-01']);
    Member::$_keyDate = null;
    File::ensureDirectoryExists(storage_path('downloads'));
});

afterEach(fn () => Member::$_keyDate = null);

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
        ->and($xml)->toContain('DE89370400440532013000')
        ->and($xml)->toContain('42.50')
        // <AJ>/<VN>/<NN> are replaced with year, first name and surname
        ->and($xml)->toContain("Beitrag 2024 {$member->first_name} {$member->surname}")
        ->and($xml)->toContain('2024-03-01')
        ->and($pdf)->toStartWith('%PDF-');
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

    Member::$_keyDate = Carbon\Carbon::parse('2024-06-01');

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

    $files = $this->club->getBLSVStatistic();

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

it('escapes a section name that would break the export path', function () {
    $section = Section::factory()->create([
        'club_id' => $this->club->id, 'blsv_id' => 9, 'name' => 'Turnen/Leichtathletik',
    ]);
    $member = Member::factory()->ofClub($this->club)->create(['birthday' => '1990-01-01']);
    $member->memberships()->attach($this->club->id, ['from' => '2016-01-01', 'to' => null]);
    $member->sections()->attach($section->id, ['from' => '2016-01-01']);

    $files = $this->club->getBLSVStatistic();

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

    $this->club->getBLSVStatistic();

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

    $this->club->getBLSVStatistic();
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

    $this->club->getBLSVStatistic();

    $year = now()->startOfYear()->year;
    $sectionCsv = file_get_contents(storage_path("downloads/{$this->club->id}_BE{$year}_Fussball.csv"));
    $pdf = pdfText(file_get_contents(storage_path("downloads/{$this->club->id}_blsv_stat.pdf")));

    expect($sectionCsv)->toContain(';d;')
        ->and($pdf)->toContain('Divers')
        // The member is counted in its own column, not among the women.
        ->and($pdf)->toContain('Weiblich');
});

it('calculates the blsv debit from the age bands', function () {
    Member::$_keyDate = Carbon\Carbon::parse('2024-01-01');

    foreach (['2012-01-01', '2008-01-01', '1980-01-01'] as $birthday) {
        $member = Member::factory()->ofClub($this->club)->create(['birthday' => $birthday]);
        $member->memberships()->attach($this->club->id, ['from' => '2016-01-01', 'to' => null]);
    }

    // one child (12), one teen (16), one adult (44)
    expect($this->club->calcBlsvDebit(1.0, 2.0, 3.0))->toBe(6.0);
});
