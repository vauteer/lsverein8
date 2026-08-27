<?php

namespace App\Enums;

/**
 * Where a user lands after signing in, backing `users.landing_page`.
 *
 * Two screens today, because those are the two a club account actually starts
 * its day on: the treasurer wants the numbers, everybody else wants the list.
 * Adding a third is a case plus a line in `route()` — the column is a string,
 * not a boolean, for that reason.
 *
 * Only screens every account may open belong in here. Both current ones are
 * readable by any member of the club (`MemberPolicy::viewAny()` returns true,
 * and the dashboard is behind nothing but `auth`), so no choice can strand a
 * user on a 403 the moment they log in.
 */
enum LandingPage: string
{
    case Dashboard = 'dashboard';
    case Members = 'members';

    public function label(): string
    {
        return match ($this) {
            self::Dashboard => __('Dashboard'),
            self::Members => __('Members'),
        };
    }

    /**
     * The named route this lands on.
     */
    public function route(): string
    {
        return match ($this) {
            self::Dashboard => 'dashboard',
            self::Members => 'members.index',
        };
    }

    public function url(): string
    {
        return route($this->route());
    }

    /**
     * @return list<array{id: string, name: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $page): array => ['id' => $page->value, 'name' => $page->label()],
            self::cases()
        );
    }
}
