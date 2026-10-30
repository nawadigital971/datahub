<?php

namespace App\Enums;

/**
 * Validation state of a contact's primary email.
 */
enum EmailStatus: int
{
    case Unknown = 0;
    case Valid = 1;
    case Invalid = 2;
    case TypoFixed = 3;

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Unknown',
            self::Valid => 'Valid',
            self::Invalid => 'Invalid',
            self::TypoFixed => 'Typo fixed',
        };
    }
}
