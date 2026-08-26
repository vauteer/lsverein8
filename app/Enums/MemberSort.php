<?php

namespace App\Enums;

use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;

/**
 * The orders the member list can be read in, carried over from lsverein7 —
 * which addressed them by the bare integers 1..6 in both the URL and the
 * controller's switch.
 */
enum MemberSort: string
{
    case Name = 'name';
    case Address = 'address';
    case Birthday = 'birthday';
    case Age = 'age';
    case Bank = 'bank';
    case Number = 'number';

    public function label(): string
    {
        return match ($this) {
            self::Name => __('Name'),
            self::Address => __('Address'),
            self::Birthday => __('Birthday'),
            self::Age => __('Age'),
            self::Bank => __('Bank'),
            self::Number => __('Member number'),
        };
    }

    /**
     * Apply this order to the member query.
     *
     * Every branch ends on `surname, first_name, id` so the paginator cannot
     * shuffle rows between pages on a tie — lsverein7 left most of these
     * unbroken and a member could appear on two pages or on neither.
     *
     * @param  Builder<Member>  $query
     */
    public function apply(Builder $query): void
    {
        match ($this) {
            self::Name => $query,
            self::Address => $query->orderBy('city')->orderBy('street'),
            // Upcoming birthdays: by day of the year, ignoring which year the
            // member was born in. MySQL-only, like the scopes it sits beside.
            self::Birthday => $query->orderByRaw('date_format(birthday, "%m-%d")'),
            self::Age => $query->orderBy('birthday', 'desc'),
            self::Bank => $query->orderBy('bank'),
            self::Number => $query->orderBy('member_id'),
        };

        $query->orderBy('surname')->orderBy('first_name')->orderBy('id');
    }

    /**
     * The selectable orders, as {id, name} options for the frontend.
     *
     * @return list<array{id: string, name: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $sort): array => ['id' => $sort->value, 'name' => $sort->label()],
            self::cases()
        );
    }
}
