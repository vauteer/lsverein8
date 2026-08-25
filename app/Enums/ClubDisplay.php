<?php

namespace App\Enums;

/**
 * How a club identifies itself in the interface.
 *
 * A logo is not always just an emblem — plenty of clubs use a wordmark that
 * already contains the name, and repeating it next to the image looks wrong.
 * This is that choice.
 */
enum ClubDisplay: int
{
    case LogoAndName = 1;
    case LogoOnly = 2;
    case NameOnly = 3;

    public function label(): string
    {
        return match ($this) {
            self::LogoAndName => __('Logo and name'),
            self::LogoOnly => __('Logo only'),
            self::NameOnly => __('Name only'),
        };
    }

    public function showsLogo(): bool
    {
        return $this !== self::NameOnly;
    }

    public function showsName(): bool
    {
        return $this !== self::LogoOnly;
    }

    /**
     * The selectable styles, as {id, name} options for the frontend.
     *
     * @return list<array{id: int, name: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $display): array => ['id' => $display->value, 'name' => $display->label()],
            self::cases()
        );
    }
}
