<?php

namespace App\Enums;

use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;

/**
 * The seven age groups a club is read in, on the BLSV's boundaries.
 *
 * The lines are the association's, not ours: `Club::getBLSVStatistic()` reports
 * exactly these seven rows and `BlsvPdf` prints their German names, so moving
 * one changes what the club submits. They are here rather than in Club so that
 * the dashboard's age chart, the member selection behind it and the yearly
 * report can only ever draw the same lines.
 *
 * The class is deliberately *not* named after the association, though the
 * boundaries are: this is the only age segmentation the app has, and clubs that
 * are no BLSV member read their dashboard and filter their members by it too
 * (club 2 has `blsv_member = 0`). A BLSV name would invite a second, "neutral"
 * set of brackets, which is the one thing this enum exists to prevent. The one
 * genuinely association-specific part carries the name instead — see
 * `blsvRow()`, the same split `Gender::blsvValue()` makes.
 *
 * The backing value is what the member list carries in its URL
 * (`?filter=age_18-26`), so it must stay stable — it lives in bookmarks.
 */
enum AgeBracket: string
{
    case Upto5 = '0-5';
    case From6 = '6-13';
    case From14 = '14-17';
    case From18 = '18-26';
    case From27 = '27-40';
    case From41 = '41-60';
    case From61 = '61+';

    /**
     * First age in the bracket.
     *
     * Not `from()`/`to()`: `BackedEnum::from()` is already taken.
     */
    public function minAge(): int
    {
        return match ($this) {
            self::Upto5 => 0,
            self::From6 => 6,
            self::From14 => 14,
            self::From18 => 18,
            self::From27 => 27,
            self::From41 => 41,
            self::From61 => 61,
        };
    }

    /**
     * Last age in the bracket, null for the open-ended one.
     */
    public function maxAge(): ?int
    {
        return match ($this) {
            self::Upto5 => 5,
            self::From6 => 13,
            self::From14 => 17,
            self::From18 => 26,
            self::From27 => 40,
            self::From41 => 60,
            self::From61 => null,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Upto5 => __('Up to 5 years'),
            self::From6 => __('6 to 13 years'),
            self::From14 => __('14 to 17 years'),
            self::From18 => __('18 to 26 years'),
            self::From27 => __('27 to 40 years'),
            self::From41 => __('41 to 60 years'),
            self::From61 => __('61 years and over'),
        };
    }

    /**
     * The member selection that lists exactly this bracket.
     */
    public function filter(): string
    {
        return 'age_'.$this->value;
    }

    /**
     * Narrow a member query to this bracket, against the current key date.
     *
     * The one place the bracket becomes SQL, so the count beside the chart and
     * the list its bar links to cannot come apart.
     *
     * @param  Builder<Member>  $query
     */
    public function apply(Builder $query): void
    {
        $query->members()->ageRange($this->minAge(), $this->maxAge());
    }

    /**
     * The bracket a given age falls into.
     */
    public static function of(int $age): self
    {
        foreach (self::cases() as $bracket) {
            $to = $bracket->maxAge();

            if ($to === null || $age <= $to) {
                return $bracket;
            }
        }

        return self::From61;
    }

    /**
     * Which of the BLSV statistic's seven rows this bracket is.
     *
     * The only association-specific thing about the enum, hence the name: the
     * brackets themselves are what every club is read in, BLSV member or not.
     *
     * The cast is safe rather than lossy: `$this` is by definition one of
     * `self::cases()`, so the search never fails.
     */
    public function blsvRow(): int
    {
        return (int) array_search($this, self::cases(), true);
    }

    /**
     * The brackets as member-list selections, for the filter dropdown.
     *
     * @return list<array{id: string, name: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $bracket): array => [
                'id' => $bracket->filter(),
                'name' => __('Age: :name', ['name' => $bracket->label()]),
            ],
            self::cases()
        );
    }
}
