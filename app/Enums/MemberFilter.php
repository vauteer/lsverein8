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
    case Left = 'left';
    case Children = 'children';
    case Youths = 'youths';
    case Adults = 'adults';
    case DueHonours = 'due_honours';
    case HasRole = 'has_role';
    case HadRole = 'had_role';
    case NoSubscription = 'no_subscription';
    case NoSection = 'no_section';
    case PossibleDuplicates = 'possible_duplicates';

    public function label(): string
    {
        return match ($this) {
            self::All => __('All'),
            self::Members => __('Members'),
            self::Former => __('Former members'),
            self::MilestoneBirthdays => __('Milestone birthdays'),
            self::Deaths => __('Deaths'),
            self::Joined => __('Joined'),
            self::Left => __('Left'),
            self::Children => __('Children (up to 13)'),
            self::Youths => __('Youths (14 to 17)'),
            self::Adults => __('Adults (18 and over)'),
            self::DueHonours => __('Honours due'),
            self::HasRole => __('Holds a role'),
            self::HadRole => __('Ever held a role'),
            self::NoSubscription => __('Without a subscription'),
            self::NoSection => __('Without an active section'),
            self::PossibleDuplicates => __('Possible duplicates'),
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
            // Literally no narrowing beyond ClubScope, which is why the
            // label is just "Alle". It was "Mit Ehemaligen" until 2026-08-27,
            // which named 155 of the 186 extra rows and silently left out 30
            // deceased and the members who join next month. Any enumerating
            // name has to be corrected again the next time a group appears.
            self::All => $query,
            self::Members => $query->members(),
            self::Former => $query->noMembers(),
            self::MilestoneBirthdays => $query->members()->milestoneBirthdays(),
            self::Deaths => $query->dead(),
            self::Joined => $query->joined(),
            self::Left => $query->left(),
            self::Children => $query->members()->ageRange(null, 13),
            self::Youths => $query->members()->ageRange(14, 17),
            self::Adults => $query->members()->ageRange(18, null),
            self::DueHonours => $query->members()->dueHonor(),
            self::HasRole => $query->members()->hasRole(),
            self::HadRole => $query->everRole(),
            self::NoSubscription => $query->members()->noSubscription(),
            // members(), unlike the duplicates below: somebody who has left
            // is supposed to have no running section.
            self::NoSection => $query->members()->noSection(),
            // No members() here: both halves of a pair have to show up, and
            // one of them has usually left.
            self::PossibleDuplicates => $query->possibleDuplicates(),
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
