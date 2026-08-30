<?php

namespace App\Models;

use App\AssignedMemberCount;
use App\Models\Scopes\ClubScope;
use Carbon\CarbonInterface;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $club_id
 * @property string $name
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read MemberRole|null $pivot the pivot row when loaded through Member::roles()
 */
#[Fillable(['club_id', 'name'])]
#[ScopedBy([ClubScope::class])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    /**
     * What a new club starts with, seeded by ClubController::store().
     *
     * insert_roles_defaults put seven of these in once per installation
     * with a null `club_id`, so every club saw the same ones. That column is
     * NOT NULL since 2026-08-30 and a club owns its roles, free to rename or
     * delete them without touching anybody else's.
     *
     * Deliberately shorter than what was seeded. The seeded set minus Ehrenamtsbeauftragter: a club that wants it adds it.
     *
     * @var list<string>
     */
    public const DEFAULTS = [
        '1. Vorstand',
        '2. Vorstand',
        'Kassier',
        'Schriftführer',
        'Beisitzer',
        'Kassenprüfer',
    ];

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return BelongsToMany<Member, $this, MemberRole>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class)
            ->withPivot(['from', 'to', 'memo'])
            ->withTimestamps()
            ->using(MemberRole::class);
    }

    /**
     * The two member counts the list shows, each matching the selection its
     * number links to.
     *
     * `members_count` mirrors `Member::hasRole()` — whoever holds the role right now.
     * `ever_members_count` mirrors `Member::everRole()`, which asks only that the
     * assignment started before the key date, former members included.
     *
     * A plain `withCount('members')` answered neither: it counted every row
     * the pivot ever held, and counted a member twice where the pivot holds
     * the pair twice.
     *
     * Note the strict `<` and `>`, which is what those scopes use — unlike
     * `Member::inSections()`, which is inclusive at both ends. The difference
     * comes from lsverein7 and is mirrored rather than fixed: changing it here
     * alone would put the counts back out of step with the selections.
     *
     * @param  Builder<Role>  $query
     */
    #[Scope]
    protected function withMemberCounts(Builder $query): void
    {
        $query->addSelect([
            'members_count' => AssignedMemberCount::current(
                'roles', 'member_role', 'role_id', '<', '>'
            ),
            'ever_members_count' => AssignedMemberCount::ever(
                'roles', 'member_role', 'role_id', '<'
            ),
        ]);
    }

    public function isUsed(): bool
    {
        return DB::table('member_role')->where('role_id', $this->id)->exists();
    }
}
