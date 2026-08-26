<?php

namespace App\Enums;

/**
 * Backs `members.gender`. The case names are the German salutations the data
 * came over with; the labels go through __() like every other enum's.
 */
enum Gender: string
{
    case Frau = 'f';
    case Mann = 'm';

    public function label(): string
    {
        return match ($this) {
            self::Frau => __('Ms'),
            self::Mann => __('Mr'),
        };
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
            self::cases()
        );
    }
}
