<?php

namespace App\Enums;

enum PhoneType: int
{
    case Mobile = 1;
    case Landline = 2;

    public function label(): string
    {
        return match ($this) {
            self::Mobile => 'Mobile',
            self::Landline => 'Landline',
        };
    }
}
