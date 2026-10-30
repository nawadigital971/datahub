<?php

namespace App\Enums;

enum SuppressionKind: int
{
    case Phone = 1;
    case Email = 2;

    public function label(): string
    {
        return match ($this) {
            self::Phone => 'Phone',
            self::Email => 'Email',
        };
    }
}
