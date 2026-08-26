<?php

namespace App\Models;

use App\Models\Scopes\ClubScope;
use Carbon\CarbonInterface;
use Database\Factories\ItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
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

    public function isUsed(): bool
    {
        return DB::table('item_member')->where('item_id', $this->id)->exists();
    }
}
