<?php

namespace App\Enums;

/**
 * The languages the interface is available in.
 *
 * Backs both `clubs.locale` and `users.locale`. The club's setting is the
 * one that applies; a user only stores a value here to deviate from it, and
 * null on the user means "follow the club".
 */
enum Locale: string
{
    case German = 'de';
    case English = 'en';

    public function label(): string
    {
        return match ($this) {
            self::German => __('German'),
            self::English => __('English'),
        };
    }

    /**
     * The selectable languages, as {id, name} options for the frontend.
     *
     * @return list<array{id: string, name: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $locale): array => ['id' => $locale->value, 'name' => $locale->label()],
            self::cases()
        );
    }
}
