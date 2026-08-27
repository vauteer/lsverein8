<?php

namespace App\Http\Controllers;

use App\Enums\AgeBracket;
use App\Enums\Gender;
use App\Enums\MemberFilter;
use App\Models\ClubMember;
use App\Models\Member;
use App\Models\Section;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The club at a glance: how many members there are, how old they are, how long
 * they have been in, and how they spread over the sections and subscriptions.
 *
 * Every number on this screen is a link into the member list, and each one is
 * produced by the very selection it links to — the sections and subscriptions
 * through `AssignedMemberCount`, the age groups through `AgeBracket::apply()`,
 * the yearly figures through the same `club_member` conditions the `joined`
 * and `retired` selections use. A tile that cannot promise that is not a link.
 *
 * Nothing here may reach for the MySQL-only member scopes (`dueHonor`,
 * `joined`, `retired`, `dead`, `milestoneBirthdays`): the whole screen would
 * then be unexercisable on the SQLite test connection. Where one of those
 * selections is linked to, the number beside it is arrived at another way.
 */
class DashboardController extends Controller
{
    /**
     * How many years the development chart looks back, matching the member
     * list's own year picker (SelectsMembers::YEARS_BACK).
     */
    private const int YEARS_BACK = 10;

    public function __invoke(Request $request): Response
    {
        // Every age and membership figure below is read against today.
        Member::$_keyDate = now()->endOfDay();

        // Loaded once and bucketed in PHP, the way Club::getBLSVStatistic()
        // does it: age and membership years are derived attributes, so a
        // query per bracket would be a query per bracket per gender.
        $members = Member::query()->members()->with('memberships')->get();

        return Inertia::render('Dashboard', [
            'year' => now()->year,
            'summary' => $this->summary($members),
            'ageStructure' => $this->ageStructure($members),
            'membershipYears' => $this->membershipYears($members),
            'development' => $this->development(),
            'sections' => $this->sections(),
            // Who pays what is a treasurer's question, the same rule that
            // keeps the subscription columns out of MemberResource and makes
            // the "without a subscription" selection admin-only.
            'subscriptions' => $request->user()->hasAdminRights()
                ? $this->subscriptions()
                : null,
        ]);
    }

    /**
     * @param  Collection<int, Member>  $members
     * @return array{members: int, former: int, joined: int, left: int, average_age: float|null, due_honours: int}
     */
    private function summary(Collection $members): array
    {
        $year = now()->year;

        return [
            'members' => $members->count(),
            'former' => Member::query()->noMembers()->count(),
            'joined' => $this->countByYear('from', $year),
            'left' => $this->countByYear('to', $year),
            'average_age' => $members->isEmpty()
                ? null
                : round($members->avg(fn (Member $member): int => $member->age), 1),
            'due_honours' => $this->dueHonours($members),
        ];
    }

    /**
     * The members whose membership reaches one of the club's honour years
     * this year.
     *
     * Counted from `Member::membershipYears()` — the very number the member
     * list prints beside each of them — rather than through the `dueHonor`
     * scope, which is MySQL-only and could not run on the test connection.
     *
     * @param  Collection<int, Member>  $members
     */
    private function dueHonours(Collection $members): int
    {
        // Once, not per member: Member::honorThisYear() resolves currentClub()
        // itself, which would be one Club::find() per row.
        $honorYears = collect(explode(',', (string) currentClub()->honor_years))
            ->map(fn (string $year): int => (int) trim($year))
            ->filter()
            ->all();

        if ($honorYears === []) {
            return 0;
        }

        return $members
            ->filter(fn (Member $member): bool => in_array($member->membershipYears(), $honorYears, true))
            ->count();
    }

    /**
     * Members whose membership started (`from`) or ended (`to`) in the given
     * year, counted the way the `joined` and `retired` selections count them:
     * distinct members of this club, however many memberships they hold.
     *
     * A plain range comparison rather than those scopes' `YEAR(...)`, which
     * SQLite has no function for.
     */
    private function countByYear(string $column, int $year): int
    {
        $ids = ClubMember::query()
            ->whereBetween($column, [
                Date::create($year, 1, 1)->startOfDay(),
                Date::create($year, 12, 31)->endOfDay(),
            ])
            ->pluck('member_id');

        return Member::query()->whereIn('id', $ids)->count();
    }

    /**
     * The seven BLSV age groups, split by gender.
     *
     * @param  Collection<int, Member>  $members
     * @return list<array{filter: string, label: string, male: int, female: int, other: int, total: int}>
     */
    private function ageStructure(Collection $members): array
    {
        $byBracket = $members->groupBy(fn (Member $member): string => AgeBracket::of($member->age)->value);

        return array_map(function (AgeBracket $bracket) use ($byBracket): array {
            /** @var Collection<int, Member> $inBracket */
            $inBracket = $byBracket->get($bracket->value, new Collection);

            $male = $inBracket->where('gender', Gender::Mann)->count();
            $female = $inBracket->where('gender', Gender::Frau)->count();

            return [
                'filter' => $bracket->filter(),
                'label' => $bracket->label(),
                'male' => $male,
                'female' => $female,
                // Divers is parked rather than impossible - see Gender.
                'other' => $inBracket->count() - $male - $female,
                'total' => $inBracket->count(),
            ];
        }, AgeBracket::cases());
    }

    /**
     * How long the current members have been in, in bands.
     *
     * Deliberately not links: there is no member selection for "20 to 29 years
     * in the club", and a number that cannot keep that promise should not look
     * like it can.
     *
     * @param  Collection<int, Member>  $members
     * @return list<array{label: string, count: int}>
     */
    private function membershipYears(Collection $members): array
    {
        $bands = [[0, 4], [5, 9], [10, 19], [20, 29], [30, 39], [40, 49], [50, null]];

        // Once per member, not once per member and band: membershipYears()
        // walks the memberships every time it is asked.
        $years = $members->map(fn (Member $member): int => $member->membershipYears());

        return array_map(function (array $band) use ($years): array {
            [$from, $to] = $band;

            return [
                'label' => $to === null ? "{$from}+" : "{$from}\u{2013}{$to}",
                'count' => $years
                    ->filter(fn (int $held): bool => $held >= $from && ($to === null || $held <= $to))
                    ->count(),
            ];
        }, $bands);
    }

    /**
     * Head count at the end of each of the last ten years, with that year's
     * arrivals and departures.
     *
     * The head count is `Member::memberIds()` against 31 December, which is
     * what the member list shows for a past year through its year picker — so
     * a column and the list it links to agree.
     *
     * @return list<array{year: int, members: int, joined: int, left: int}>
     */
    private function development(): array
    {
        $current = now()->year;

        return array_map(function (int $year) use ($current): array {
            $keyDate = $year === $current
                ? now()->endOfDay()
                : Date::create($year, 12, 31)->endOfDay();

            return [
                'year' => $year,
                'members' => Member::memberIds($keyDate)->count(),
                'joined' => $this->countByYear('from', $year),
                'left' => $this->countByYear('to', $year),
            ];
        }, range($current - self::YEARS_BACK + 1, $current));
    }

    /**
     * @return list<array{label: string, count: int, filter: string}>
     */
    private function sections(): array
    {
        // array_values, not Collection::values(): only the native call proves
        // a list to phpstan, which sortedByCount() declares.
        return $this->sortedByCount(array_values(
            Section::query()->withCurrentMemberCount()->orderBy('name')->get()
                ->map(fn (Section $section): array => [
                    'label' => $section->name,
                    'count' => (int) $section->getAttribute('members_count'),
                    'filter' => "section_{$section->id}",
                ])
                ->all()
        ));
    }

    /**
     * The subscriptions currently held, plus the members holding none — that
     * last row is the treasurer's actual question, and it is the reason the
     * whole card is admin-only.
     *
     * @return list<array{label: string, count: int, filter: string}>
     */
    private function subscriptions(): array
    {
        $subscriptions = $this->sortedByCount(array_values(
            Subscription::query()->withCurrentMemberCount()->orderBy('name')->get()
                ->map(fn (Subscription $subscription): array => [
                    'label' => $subscription->name,
                    'count' => (int) $subscription->getAttribute('members_count'),
                    'filter' => "subscription_{$subscription->id}",
                ])
                ->all()
        ));

        return [...$subscriptions, [
            'label' => MemberFilter::NoSubscription->label(),
            'count' => Member::query()->members()->noSubscription()->count(),
            'filter' => MemberFilter::NoSubscription->value,
        ]];
    }

    /**
     * Most members first, then by name — the order the two arguments were
     * already put in, so a tie reads alphabetically.
     *
     * @param  list<array{label: string, count: int, filter: string}>  $rows
     * @return list<array{label: string, count: int, filter: string}>
     */
    private function sortedByCount(array $rows): array
    {
        usort($rows, fn (array $first, array $second): int => $second['count'] <=> $first['count']);

        return $rows;
    }
}
