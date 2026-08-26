<?php

namespace App\Models;

use App\AssignedMemberCount;
use App\Models\Scopes\ClubWithSharedScope;
use Carbon\CarbonInterface;
use Database\Factories\EventFactory;
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
 * @property int|null $club_id
 * @property string $name
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 * @property-read EventMember|null $pivot the pivot row when loaded through Member::events()
 */
#[Fillable(['club_id', 'name'])]
#[ScopedBy([ClubWithSharedScope::class])]
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Club, $this>
     */
    public function club(): BelongsTo
    {
        return $this->belongsTo(Club::class);
    }

    /**
     * @return BelongsToMany<Member, $this, EventMember>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Member::class)
            ->withPivot(['date', 'memo'])
            ->withTimestamps()
            ->using(EventMember::class);
    }

    /**
     * Count the members who have been given this honour.
     *
     * Mirrors `Member::hadEvent()`, which the `event_X` selection uses, so the
     * number and what it links to cannot disagree: members of the club — former
     * ones included, an honour is kept — with an `event_member` row dated
     * before the key date.
     *
     * Two reasons a plain `withCount('members')` was wrong here, both live in
     * production: it counted rows rather than members, and `event_member` holds
     * one duplicate pair; and it counted honours dated today or later, of which
     * there are six, while `hadEvent()` asks for `date < keyDate`.
     *
     * There is no "current" counterpart, unlike roles and items: an honour has
     * a date, not a range.
     *
     * @param  Builder<Event>  $query
     */
    #[Scope]
    protected function withMemberCount(Builder $query): void
    {
        $query->addSelect([
            'members_count' => AssignedMemberCount::ever(
                'events', 'event_member', 'event_id', '<', 'date'
            ),
        ]);
    }

    public function isUsed(): bool
    {
        return DB::table('event_member')->where('event_id', $this->id)->exists();
    }
}
