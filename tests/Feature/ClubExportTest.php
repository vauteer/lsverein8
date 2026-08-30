<?php

use App\ClubExport;
use App\Enums\ClubRole;
use App\Models\Club;
use App\Models\Debit;
use App\Models\Member;
use App\Models\Section;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * currentClubId() resolves to 1 on the CLI, so every request is read as though
 * the acting user were working in club 1.
 */
beforeEach(function () {
    $this->club = Club::factory()->create(['id' => 1, 'name' => 'Sportverein']);
    Member::setKeyDate(null);
});

afterEach(fn () => Member::setKeyDate(null));

function clubExportUser(ClubRole $role = ClubRole::Admin, ?Club $club = null, array $attributes = []): User
{
    $club ??= Club::find(1) ?? Club::factory()->create(['id' => 1]);

    $user = User::factory()->create([...$attributes, 'club_id' => $club->id]);
    $user->clubs()->attach($club->id, ['role' => $role->value]);

    return $user;
}

/**
 * The `users` rows of an export, each as column => literal.
 *
 * Parsed rather than matched positionally: the live schema and the SQLite one
 * the tests build order the columns differently, so an assertion on "the tenth
 * value" passes in one and not the other.
 *
 * @return Collection<int, array<string, string>>
 */
function exportedUsers(string $sql): Collection
{
    preg_match('/INSERT INTO `users` \((.*?)\) VALUES\n(.*?);\n/s', $sql, $matches);

    $columns = array_map(fn (string $column): string => trim($column, '` '), explode(',', $matches[1]));

    return collect(explode(PHP_EOL, $matches[2]))
        ->filter()
        // ' is the string delimiter, so a name holding a comma stays one value.
        ->map(fn (string $row): array => array_combine(
            $columns,
            array_map(trim(...), str_getcsv(trim(trim($row, ','), '()'), ',', "'", '\\'))
        ))
        ->values();
}

/** A member of the given club with something recorded against them. */
function furnishedMember(Club $club, string $surname): Member
{
    $member = Member::factory()->ofClub($club->id)->create(['surname' => $surname]);
    $member->memberships()->attach($club->id, ['from' => '2016-01-01']);
    $member->sections()->attach(
        Section::factory()->create(['club_id' => $club->id, 'name' => "Sparte {$surname}"])->id,
        ['from' => '2016-01-01']
    );
    $member->subscriptions()->attach(
        Subscription::factory()->create(['club_id' => $club->id, 'name' => "Beitrag {$surname}"])->id
    );
    Debit::factory()->create(['member_id' => $member->id, 'transfer_text' => "Lastschrift {$surname}"]);

    return $member;
}

test('guests are redirected to the login page', function () {
    $this->get(route('clubs.export', $this->club))->assertRedirect(route('login'));
});

test('a club admin exports their own club but not another', function () {
    $other = Club::factory()->create(['name' => 'Feuerwehr']);

    $this->actingAs(clubExportUser());

    $this->get(route('clubs.export', $this->club))->assertOk();
    // update() lets a club admin at the club they are working in and no other.
    $this->get(route('clubs.export', $other))->assertForbidden();
});

test('root exports any club, and gets the one in the url rather than the current one', function () {
    $other = Club::factory()->create(['name' => 'Feuerwehr']);
    furnishedMember($this->club, 'Eigen');
    furnishedMember($other, 'Fremd');

    $response = $this->actingAs(clubExportUser(attributes: ['admin' => true]))
        ->get(route('clubs.export', $other));

    $response->assertOk();

    // Root is working in club 1 but looking at the other club's page; the
    // route decides, not currentClub().
    expect($response->getContent())->toContain('Fremd')
        ->not->toContain('Eigen');
});

test('a non-admin may not export at all', function () {
    $this->actingAs(clubExportUser(ClubRole::Advanced))
        ->get(route('clubs.export', $this->club))
        ->assertForbidden();
});

test('the export carries the club slice and nothing of another club', function () {
    $other = Club::factory()->create(['name' => 'Feuerwehr']);

    furnishedMember($this->club, 'Eigen');
    furnishedMember($other, 'Fremd');

    // Both accounts get a fixed name. The faker default bit here: a name
    // carrying an apostrophe ("Mr. Carmelo O'Kon") is escaped as O''Kon in the
    // SQL, so an assertion on the raw name failed about one run in a hundred.
    $mine = clubExportUser(attributes: ['name' => 'Eigenbenutzer']);
    clubExportUser(club: $other, attributes: ['name' => 'Fremdbenutzer']);

    $sql = $this->actingAs($mine)
        ->get(route('clubs.export', $this->club))
        ->getContent();

    expect($sql)->toContain('Eigen')
        ->toContain('Sparte Eigen')
        ->toContain('Beitrag Eigen')
        ->toContain('Lastschrift Eigen')
        ->toContain('Eigenbenutzer')
        // Nothing of the other club, its members, or the accounts that reach it.
        ->not->toContain('Fremd');
});

test('every club table is present, even when empty', function () {
    $sql = $this->actingAs(clubExportUser())
        ->get(route('clubs.export', $this->club))
        ->getContent();

    // An empty table still gets its TRUNCATE, so a restore clears whatever the
    // target held rather than leaving it behind.
    foreach ([
        'users', 'clubs', 'club_user', 'members', 'club_member', 'sections',
        'member_section', 'events', 'event_member', 'roles', 'member_role',
        'items', 'item_member', 'subscriptions', 'member_subscription', 'debits',
    ] as $table) {
        expect($sql)->toContain("TRUNCATE `{$table}`;");
    }

    // The audit log has no club of its own, so it is left out entirely.
    expect($sql)->not->toContain('tracings');
});

test('no credentials leave in the export', function () {
    // A club admin may export their own club, and a user of two clubs appears
    // in both exports — so without this the admin of one club could read the
    // hash of an account that also works in another, the root account among
    // them.
    $exporter = clubExportUser(attributes: ['password' => bcrypt('geheim'), 'remember_token' => 'abcdefghij']);

    $sql = (new ClubExport($this->club))->toSql();

    expect($sql)->toContain($exporter->email)
        ->and($sql)->not->toContain($exporter->getAuthPassword())
        ->and($sql)->not->toContain('abcdefghij')
        ->and($sql)->toContain('-- Passwords are not exported.');
});

test('an exported user works in the exported club, whatever they were working in', function () {
    $other = Club::factory()->create(['name' => 'Feuerwehr']);

    // In both clubs, currently working in the other one. Exported as it stood,
    // the restored database would have them pointing at a club it does not
    // contain: currentClub() returns null and the first ->name is fatal.
    $both = clubExportUser();
    $both->clubs()->attach($other->id, ['role' => ClubRole::Admin->value]);
    $both->update(['club_id' => $other->id]);

    // And a creator the file does not contain has to be forgotten.
    $created = clubExportUser(attributes: ['created_by' => User::factory()->create()->id]);

    $sql = (new ClubExport($this->club))->toSql();

    $users = exportedUsers($sql);

    expect($users)->toHaveCount(2)
        ->and($users->pluck('club_id')->unique()->all())->toBe([(string) $this->club->id])
        ->and($users->firstWhere('id', (string) $created->id)['created_by'])->toBe('NULL')
        ->and($users->pluck('id')->all())->not->toContain((string) $created->created_by);
});

test('the script hands the session back with its foreign key checks on', function () {
    $sql = (new ClubExport($this->club))->toSql();

    expect($sql)->toContain('SET foreign_key_checks = 0;')
        ->and(trim($sql))->toEndWith('SET foreign_key_checks = 1;')
        // Appended, not assigned: a bare assignment drops STRICT_TRANS_TABLES
        // and the import would truncate a bad value instead of refusing it.
        ->and($sql)->toContain("SET sql_mode = CONCAT(@@sql_mode, ',NO_AUTO_VALUE_ON_ZERO');");
});

test('the script warns that it empties the tables it fills', function () {
    $sql = $this->actingAs(clubExportUser())
        ->get(route('clubs.export', $this->club))
        ->getContent();

    expect($sql)->toContain('WARNING')
        ->toContain('empty database only')
        ->toContain('SET foreign_key_checks = 0;');
});

test('a value containing a quote or a backslash survives the round trip', function () {
    // lsverein7 escaped the quote but not the backslash, so a value ending in
    // one closed the literal early and shifted every column after it.
    $member = Member::factory()->ofClub(1)->create([
        'surname' => "O'Brien",
        'memo' => 'Pfad C:\\Ordner\\',
    ]);
    $member->memberships()->attach(1, ['from' => '2016-01-01']);

    $sql = (new ClubExport($this->club))->toSql();

    $insert = collect(explode(PHP_EOL, $sql))
        ->first(fn (string $line): bool => str_contains($line, 'Brien'));

    // Balanced quoting: the row is one complete tuple, not a truncated one.
    //
    // PDO::quote is driver-aware, so what this proves on SQLite is that the
    // literal is closed properly, not the exact escaping — SQLite has no
    // backslash escape, MySQL does. That is the point of using PDO::quote
    // rather than a hand-rolled str_replace: each driver quotes its own way.
    expect(substr_count((string) $insert, "'") % 2)->toBe(0)
        ->and($insert)->toStartWith('(')
        ->and(rtrim((string) $insert, ';'))->toEndWith(')')
        ->and($insert)->toContain("O''Brien");
});

test('the filename names the club and the day', function () {
    $this->actingAs(clubExportUser())
        ->get(route('clubs.export', $this->club))
        ->assertDownload('sportverein-'.now()->format('Y-m-d').'.sql');
});
