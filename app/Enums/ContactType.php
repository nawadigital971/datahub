<?php

namespace App\Enums;

/**
 * Person or business record.
 */
enum ContactType: int
{
    case Person = 1;
    case Business = 2;

    public function label(): string
    {
        return match ($this) {
            self::Person => 'Person',
            self::Business => 'Business',
        };
    }
}
