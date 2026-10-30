<?php

namespace App\Enums;

use App\Enums\Concerns\HasKey;

enum RowSeverity: int
{
    use HasKey;

    case Clean = 0;
    case Warning = 1;
    case Error = 2;

    public function label(): string
    {
        return match ($this) {
            self::Clean => 'Clean',
            self::Warning => 'Warning',
            self::Error => 'Error',
        };
    }
}
