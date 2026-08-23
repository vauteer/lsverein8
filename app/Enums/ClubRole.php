<?php

namespace App\Enums;

enum ClubRole: int
{
    case Basic = 1;
    case Advanced = 128;
    case Admin = 256;

    public function label(): string
    {
        return match ($this) {
            self::Basic => __('Read only'),
            self::Advanced => __('Editor'),
            self::Admin => __('Administrator'),
        };
    }
}
