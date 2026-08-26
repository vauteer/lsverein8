<?php

use App\Enums\ClubRole;
use App\Models\Club;
use App\Models\Member;
use App\Models\Section;
use App\Models\User;

/**
 * currentClubId() resolves to 1 on the CLI, so every request is read as though
 * the acting user were working in club 1.
 */
beforeEach(function () {
    $this->club = Club::factory()->create(['id' => 1]);
});

/**
 * Create a user belonging to the given club (defaulting to club 1) with the
 * given role in it.
 */
function sectionUser(ClubRole $role = ClubRole::Admin, ?Club $club = null, array $attributes = []): User
{
    $club ??= Club::find(1) ?? Club::factory()->create(['id' => 1]);

    $user = User::factory()->create([...$attributes, 'club_id' => $club->id]);
    $user->clubs()->attach($club->id, ['role' => $role->value]);

    return $user;
}

test('guests are redirected to the login page', function () {
    $this->get(route('sections.index'))->assertRedirect(route('login'));
});

test('the index lists the club sections and the shared ones, but no other club', function () {
    $own = Section::factory()->create(['club_id' => 1, 'name' => 'Turnen']);
    $shared = Section::factory()->create(['club_id' => null, 'name' => 'Schach']);
    $foreign = Section::factory()->create(['name' => 'Fremde Abteilung']);

    $this->actingAs(sectionUser(ClubRole::Basic))
        ->get(route('sections.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sections/Index')
            ->has('sections.data', 2)
            ->where('sections.data.0.id', $shared->id)
            ->where('sections.data.0.shared', true)
            ->where('sections.data.1.id', $own->id)
            ->where('sections.data.1.shared', false)
            ->whereNot('sections.data.0.id', $foreign->id)
            ->whereNot('sections.data.1.id', $foreign->id)
            ->where('blsv', false)
        );
});

test('the index counts only the current club members and can be searched', function () {
    $section = Section::factory()->create(['club_id' => 1, 'name' => 'Tennis']);
    Section::factory()->create(['club_id' => 1, 'name' => 'Fussball']);

    $member = Member::factory()->ofClub(1)->create();
    $member->memberships()->attach(1, ['from' => '2016-01-01', 'to' => null]);

    // Another club's member holds it too, but must not be counted here.
    $foreignMember = Member::factory()->create();

    $section->members()->attach([$member->id, $foreignMember->id], ['from' => now()->subYear()]);

    $this->actingAs(sectionUser())
        ->get(route('sections.index', ['search' => 'Tenn']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('sections.data', 1)
            ->where('sections.data.0.id', $section->id)
            ->where('sections.data.0.members_count', 1)
            ->where('filters.search', 'Tenn')
        );
});

test('the count is the members in the section now, matching what it links to', function () {
    $section = Section::factory()->create(['club_id' => 1, 'name' => 'Tennis']);

    // In the section and in the club: counted.
    $current = Member::factory()->ofClub(1)->create(['surname' => 'Aktiv']);
    $current->memberships()->attach(1, ['from' => '2016-01-01', 'to' => null]);
    $current->sections()->attach($section->id, ['from' => '2016-01-01', 'to' => null]);

    // Left the section but still in the club.
    $leftSection = Member::factory()->ofClub(1)->create(['surname' => 'Sparte']);
    $leftSection->memberships()->attach(1, ['from' => '2016-01-01', 'to' => null]);
    $leftSection->sections()->attach($section->id, ['from' => '2016-01-01', 'to' => '2020-01-01']);

    // Left the club but the section row was never closed.
    $leftClub = Member::factory()->ofClub(1)->create(['surname' => 'Verein']);
    $leftClub->memberships()->attach(1, ['from' => '2010-01-01', 'to' => '2015-01-01']);
    $leftClub->sections()->attach($section->id, ['from' => '2010-01-01', 'to' => null]);

    $this->actingAs(sectionUser());

    // A plain withCount('members') read 3 here; Fussball in production read
    // 222 where the selection shows 103, so the number linked to half the
    // people it promised.
    $this->get(route('sections.index'))
        ->assertInertia(fn ($page) => $page->where('sections.data.0.members_count', 1));

    // Exactly what the selection the number links to returns.
    $this->get(route('members.index', ['filter' => "section_{$section->id}"]))
        ->assertInertia(fn ($page) => $page
            ->has('members.data', 1)
            ->where('members.data.0.surname', 'Aktiv')
        );
});

test('a member without admin rights may view but not change sections', function () {
    $section = Section::factory()->create(['club_id' => 1]);

    $this->actingAs(sectionUser(ClubRole::Advanced));

    $this->get(route('sections.index'))->assertOk()
        ->assertInertia(fn ($page) => $page->where('canCreate', false));
    $this->get(route('sections.create'))->assertForbidden();
    $this->get(route('sections.edit', $section))->assertForbidden();
    $this->delete(route('sections.destroy', $section))->assertForbidden();
});

test('an admin creates a section for the current club', function () {
    $this->actingAs(sectionUser())
        ->post(route('sections.store'), ['name' => 'Turnen'])
        ->assertRedirect();

    $section = Section::firstOrFail();

    expect($section->name)->toBe('Turnen')
        ->and($section->club_id)->toBe(1)
        ->and($section->blsv_id)->toBeNull();
});

test('a section name must be unique among the club and shared sections', function () {
    Section::factory()->create(['club_id' => 1, 'name' => 'Turnen']);
    Section::factory()->create(['club_id' => null, 'name' => 'Schach']);
    Section::factory()->create(['name' => 'Fremde Abteilung']);

    $this->actingAs(sectionUser());

    $this->post(route('sections.store'), ['name' => 'Turnen'])->assertSessionHasErrors('name');
    $this->post(route('sections.store'), ['name' => 'Schach'])->assertSessionHasErrors('name');

    // Another club's name is free: it is never listed alongside these.
    $this->post(route('sections.store'), ['name' => 'Fremde Abteilung'])->assertSessionHasNoErrors();
});

test('a section name may not contain characters that break the BLSV export filename', function () {
    $this->actingAs(sectionUser())
        ->post(route('sections.store'), ['name' => 'Turnen/Leichtathletik'])
        ->assertSessionHasErrors('name');

    expect(Section::count())->toBe(0);
});

test('the BLSV assignment is rejected for a club that is not a BLSV member', function () {
    $this->actingAs(sectionUser())
        ->post(route('sections.store'), ['name' => 'Turnen', 'blsv_id' => 34])
        ->assertSessionHasErrors('blsv_id');
});

test('a BLSV club assigns an official section number', function () {
    $this->club->update(['blsv_member' => true]);

    $this->actingAs(sectionUser())
        ->get(route('sections.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sections/Create')
            ->has('blsvSections')
        );

    $this->post(route('sections.store'), ['name' => 'Turnen', 'blsv_id' => 34])
        ->assertSessionHasNoErrors();

    expect(Section::firstOrFail()->blsv_id)->toBe(34);

    $this->post(route('sections.store'), ['name' => 'Fantasie', 'blsv_id' => 999])
        ->assertSessionHasErrors('blsv_id');
});

test('an admin renames a section', function () {
    $section = Section::factory()->create(['club_id' => 1, 'name' => 'Turnen', 'blsv_id' => null]);

    $this->actingAs(sectionUser())
        ->get(route('sections.edit', $section))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('sections/Edit')
            ->where('section.name', 'Turnen')
            ->where('deletable', true)
        );

    $this->put(route('sections.update', $section), ['name' => 'Turnen und Gymnastik'])
        ->assertRedirect();

    expect($section->refresh()->name)->toBe('Turnen und Gymnastik');
});

test('an unused section is deleted and a used one is kept', function () {
    $unused = Section::factory()->create(['club_id' => 1]);
    $used = Section::factory()->create(['club_id' => 1]);

    $used->members()->attach(Member::factory()->ofClub(1)->create()->id, ['from' => now()->subYear()]);

    $this->actingAs(sectionUser());

    $this->delete(route('sections.destroy', $unused))->assertRedirect();
    expect(Section::find($unused->id))->toBeNull();

    $this->delete(route('sections.destroy', $used))->assertForbidden();
    expect(Section::find($used->id))->not->toBeNull();

    $this->get(route('sections.edit', $used))
        ->assertInertia(fn ($page) => $page->where('deletable', false));
});

test('a club admin may not change a shared section, but a root account may', function () {
    $shared = Section::factory()->create(['club_id' => null, 'name' => 'Schach']);

    $this->actingAs(sectionUser())
        ->get(route('sections.edit', $shared))
        ->assertForbidden();

    $this->actingAs(sectionUser(attributes: ['admin' => true]))
        ->put(route('sections.update', $shared), ['name' => 'Schach und Go'])
        ->assertRedirect();

    expect($shared->refresh()->name)->toBe('Schach und Go');
});

test('a section of another club cannot be reached by guessing its id', function () {
    $foreign = Section::factory()->create();

    $this->actingAs(sectionUser())
        ->get(route('sections.edit', $foreign))
        ->assertNotFound();
});
