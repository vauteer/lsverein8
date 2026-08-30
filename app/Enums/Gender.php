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
     * The association confirmed on 2026-08-29 that the format carries a third
     * value, `d`. Until then a diverse member was reported as `w`, and the
     * case was kept out of the picker rather than exported as female.
     *
     * The three letters are also the keys of every row of the age statistic,
     * which is why they are spelled out here rather than left as `string`:
     * `Club::buildBlsvStatistic()` counts into `['m' => …, 'w' => …, 'd' => …]`
     * with this as the key.
     *
     * @return 'm'|'w'|'d'
     */
    public function blsvValue(): string
    {
        return match ($this) {
            self::Mann => 'm',
            self::Frau => 'w',
            self::Divers => 'd',
        };
    }

    /**
     * The genders as {id, name} options for the frontend.
     *
     * @return list<array{id: string, name: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $gender): array => ['id' => $gender->value, 'name' => $gender->label()],
            self::cases()
        );
    }
}
