<?php

namespace App\Enums;

use App\Models\Member;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * The fixed selections above the member list, carried over from lsverein7 —
 * which addressed them by the bare integers 0..13 in both the URL and the
 * controller's match, so a bookmarked list said `?filter=10` and nothing else.
 *
 * The selections that name a single section, role, honour, item, subscription
 * or payment method are not in here: they are built from the club's own rows
 * at request time. See MemberController::dynamicFilters().
 */
enum MemberFilter: string
{
    case All = 'all';
    case Members = 'members';
    case Former = 'former';
    case MilestoneBirthdays = 'milestone_birthdays';
    case Deaths = 'deaths';
    case Joined = 'joined';
    case Retired = 'retired';
    case Children = 'children';
    case Youths = 'youths';
    case Adults = 'adults';
    case DueHonours = 'due_honours';
    case HasRole = 'has_role';
    case HadRole = 'had_role';
    case NoSubscription = 'no_subscription';

    public function label(): string
    {
        return match ($this) {
            self::All => __('Including former members'),
            self::Members => __('Members'),
            self::Former => __('Former members'),
            self::MilestoneBirthdays => __('Milestone birthdays'),
            self::Deaths => __('Deaths'),
            self::Joined => __('Joined'),
            self::Retired => __('Left'),
            self::Children => __('Children (up to 13)'),
            self::Youths => __('Youths (14 to 17)'),
            self::Adults => __('Adults (18 and over)'),
            self::DueHonours => __('Honours due'),
            self::HasRole => __('Holds a role'),
            self::HadRole => __('Ever held a role'),
            self::NoSubscription => __('Without a subscription'),
        };
    }

    /**
     * Who owes the club nothing is a treasurer's question, so that one
     * selection is admin-only — as in lsverein7.
     */
    public function isVisibleTo(User $user): bool
    {
        return $this !== self::NoSubscription || $user->hasAdminRights();
    }

    /**
     * Narrow the member query to this selection.
     *
     * @param  Builder<Member>  $query
     */
    public function apply(Builder $query): void
    {
        match ($this) {
            self::All => $query,
            self::Members => $query->members(),
            self::Former => $query->noMembers(),
            self::MilestoneBirthdays => $query->members()->milestoneBirthdays(),
            self::Deaths => $query->dead(),
            self::Joined => $query->joined(),
            self::Retired => $query->retired(),
            self::Children => $query->members()->ageRange(null, 13),
            self::Youths => $query->members()->ageRange(14, 17),
            self::Adults => $query->members()->ageRange(18, null),
            self::DueHonours => $query->members()->dueHonor(),
            self::HasRole => $query->members()->hasRole(),
            self::HadRole => $query->everRole(),
            self::NoSubscription => $query->members()->noSubscription(),
        };
    }

    /**
     * The selections this user may pick, as {id, name} options.
     *
     * @return list<array{id: string, name: string}>
     */
    public static function optionsFor(User $user): array
    {
        return array_values(array_map(
            fn (self $filter): array => ['id' => $filter->value, 'name' => $filter->label()],
            array_filter(self::cases(), fn (self $filter): bool => $filter->isVisibleTo($user))
        ));
    }
}
