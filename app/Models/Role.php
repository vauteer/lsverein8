<?php

namespace App\Models;

use App\Models\Scopes\SharedClubScope;
use Carbon\CarbonInterface;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int|null $club_id
 * @property string $name
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read MemberRole|null $pivot the pivot row when loaded through Member::roles()
 */
#[Fillable(['club_id', 'name'])]
#[ScopedBy([SharedClubScope::class])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

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

    public function isUsed(): bool
    {
        return DB::table('member_role')->where('role_id', $this->id)->exists();
    }
}
