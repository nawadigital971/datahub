<?php

namespace App\Enums;

/**
 * Stored as char(1) on contacts and staged rows.
 */
enum Gender: string
{
    case Male = 'm';
    case Female = 'f';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Male',
            self::Female => 'Female',
        };
    }
}
