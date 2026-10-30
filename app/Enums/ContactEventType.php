<?php

namespace App\Enums;

enum ContactEventType: int
{
    case Call = 1;
    case OptOut = 2;
    case Merge = 3;
    case Export = 4;
    case Edit = 5;

    public function label(): string
    {
        return match ($this) {
            self::Call => 'Call',
            self::OptOut => 'Opt-out',
            self::Merge => 'Merge',
            self::Export => 'Export',
            self::Edit => 'Edit',
        };
    }
}
