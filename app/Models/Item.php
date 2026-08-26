<?php

namespace App\Models;

use App\AssignedMemberCount;
use App\Models\Scopes\ClubScope;
use Carbon\CarbonInterface;
use Database\Factories\ItemFactory;
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
 * @property-read ItemMember|null $pivot the pivot row when loaded through Member::items()
 */
#[Fillable(['club_id', 'name'])]
#[ScopedBy([ClubScope::class])]
class Item extends Model
{
    /** @use HasFactory<ItemFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return BelongsToMany<Member, $this, ItemMember>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class)
            ->withPivot(['memo', 'from', 'to'])
            ->withTimestamps()
            ->using(ItemMember::class);
    }

    /**
     * The two member counts the list shows, each matching the selection its
     * number links to.
     *
     * `members_count` mirrors `Member::hasItem()` — whoever still has the item out.
     * `ever_members_count` mirrors `Member::everItem()`, which asks only that the
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
     * @param  Builder<Item>  $query
     */
    #[Scope]
    protected function withMemberCounts(Builder $query): void
    {
        $query->addSelect([
            'members_count' => AssignedMemberCount::current(
                'items', 'item_member', 'item_id', '<', '>'
            ),
            'ever_members_count' => AssignedMemberCount::ever(
                'items', 'item_member', 'item_id', '<'
            ),
        ]);
    }

    public function isUsed(): bool
    {
        return DB::table('item_member')->where('item_id', $this->id)->exists();
    }
}
