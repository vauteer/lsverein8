<?php

namespace App\Enums;

/**
 * Backs `members.gender` (char(1)). The case names are the German ones the
 * data came over with; the labels go through __() like every other enum's.
 */
enum Gender: string
{
    case Frau = 'f';
    case Mann = 'm';
    case Divers = 'd';

    public function label(): string
    {
        return match ($this) {
            self::Frau => __('Female'),
            self::Mann => __('Male'),
            self::Divers => __('Diverse'),
        };
    }

    /**
     * The value the BLSV statistic expects in its `Geschlecht` column.
     *
     * The BLSV format is the association's, not ours, and only ever carried
     * `m` and `w`. Until it is known whether it accepts a third value, a
     * diverse member is reported as `w` — which is what the old
     * `=== 'm' ? 'm' : 'w'` in Club::getBLSVStatistic() did by accident. It is
     * spelled out here so the assumption is visible and there is one place to
     * change once the association has answered.
     */
    public function blsvValue(): string
    {
        return $this === self::Mann ? 'm' : 'w';
    }

    /**
     * The genders that may currently be chosen.
     *
     * `Divers` is deliberately not among them. The case exists and the column
     * (char(1)) would hold it, but the BLSV statistic can only report `m` or
     * `w` and it is unknown whether the association accepts a third value —
     * see blsvValue(). Nobody needs it yet, so rather than export a diverse
     * member as female, it is parked: not offered by the picker, and refused
     * by validation, which uses this same list through
     * `Rule::enum(Gender::class)->only(...)`.
     *
     * To switch it on once the BLSV has answered: return `self::cases()` here
     * and settle blsvValue(). Nothing else has to change.
     *
     * @return list<self>
     */
    public static function selectable(): array
    {
        return [self::Frau, self::Mann];
    }

    /**
     * The selectable genders, as {id, name} options for the frontend.
     *
     * @return list<array{id: string, name: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $gender): array => ['id' => $gender->value, 'name' => $gender->label()],
            self::selectable()
        );
    }
}
