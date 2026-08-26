<?php

namespace App;

use App\Models\Member;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The member counts shown beside a section, role, honour or item, each built
 * to match exactly the member selection its number links to.
 *
 * Subqueries rather than `withCount()`, because they have to count DISTINCT
 * members: every pivot allows the same pair twice, and production holds four
 * such rows in `member_role` — one with both ranges open at once, which
 * `count(*)` reported as two people — plus one in `event_member`.
 *
 * Each argument is `literal-string`: they are spliced into raw SQL, so nothing
 * that could come from a request may reach them.
 */
class AssignedMemberCount
{
    /**
     * Members assigned right now: a living member of the club with an open
     * membership (`Member::memberIds()`, the set the `members()` scope uses)
     * whose range is open at the key date.
     *
     * The operators differ per relation and must mirror the matching Member
     * scope — sections are inclusive at both ends, roles and items strict.
     *
     * @param  literal-string  $table
     * @param  literal-string  $pivot
     * @param  literal-string  $foreignKey
     * @param  literal-string  $startOperator
     * @param  literal-string  $endOperator
     */
    public static function current(
        string $table,
        string $pivot,
        string $foreignKey,
        string $startOperator,
        string $endOperator,
    ): Builder {
        $keyDate = Member::getKeyDate();

        return self::base($table, $pivot, $foreignKey)
            ->where($pivot.'.from', $startOperator, $keyDate)
            ->whereIn('members.id', Member::memberIds())
            ->where(fn (Builder $range) => $range
                ->whereNull($pivot.'.to')
                ->orWhere($pivot.'.to', $endOperator, $keyDate));
    }

    /**
     * Members ever assigned: only that the assignment started before the key
     * date, former members included.
     *
     * Deliberately without the `members()` restriction — these numbers link to
     * the "jemals" selections, which exist to show the people who have left.
     * `$startColumn` is `date` for honours, whose pivot has no range at all:
     * an honour is given on a day and kept.
     *
     * @param  literal-string  $table
     * @param  literal-string  $pivot
     * @param  literal-string  $foreignKey
     * @param  literal-string  $startOperator
     * @param  literal-string  $startColumn
     */
    public static function ever(
        string $table,
        string $pivot,
        string $foreignKey,
        string $startOperator,
        string $startColumn = 'from',
    ): Builder {
        return self::base($table, $pivot, $foreignKey)
            ->where($pivot.'.'.$startColumn, $startOperator, Member::getKeyDate());
    }

    /**
     * The current members of a club, counted for a row of `clubs`.
     *
     * Its own method rather than `current()`, because that one leans on
     * `Member::memberIds()` and `currentClubId()` — both pinned to the club the
     * viewer is working in, while the club list shows the whole installation.
     * The conditions are `memberIds()`'s, spelled out and correlated to
     * `clubs.id` instead: a living member of that club with an open
     * `club_member` row.
     */
    public static function ofClub(): Builder
    {
        $keyDate = Member::getKeyDate();

        return DB::table('club_member')
            ->join('members', 'members.id', '=', 'club_member.member_id')
            ->selectRaw('count(distinct club_member.member_id)')
            ->whereColumn('members.club_id', 'clubs.id')
            ->where(fn (Builder $alive) => $alive
                ->whereNull('members.death_day')
                ->orWhere('members.death_day', '>', $keyDate))
            ->where('club_member.from', '<=', $keyDate)
            ->where(fn (Builder $range) => $range
                ->whereNull('club_member.to')
                ->orWhere('club_member.to', '>=', $keyDate));
    }

    /**
     * Members who currently hold an assignment that carries no dates at all.
     *
     * `member_subscription` is the only such pivot: a subscription is held or
     * it is not, there is no from/to. "Current" can therefore only mean a
     * current member — which is exactly what the `subscription_X` selection
     * asks (`members()->hasSubscription()`), and why the member list greys out
     * its year picker for that selection.
     *
     * @param  literal-string  $table
     * @param  literal-string  $pivot
     * @param  literal-string  $foreignKey
     */
    public static function held(string $table, string $pivot, string $foreignKey): Builder
    {
        return self::base($table, $pivot, $foreignKey)
            ->whereIn('members.id', Member::memberIds());
    }

    /**
     * Distinct members of the club assigned to a row of `$table`, with no
     * condition on when — each caller adds the one its selection uses.
     *
     * @param  literal-string  $table
     * @param  literal-string  $pivot
     * @param  literal-string  $foreignKey
     */
    private static function base(string $table, string $pivot, string $foreignKey): Builder
    {
        return DB::table($pivot)
            ->join('members', 'members.id', '=', $pivot.'.member_id')
            ->selectRaw("count(distinct {$pivot}.member_id)")
            ->whereColumn($pivot.'.'.$foreignKey, $table.'.id')
            ->where('members.club_id', currentClubId());
    }
}
