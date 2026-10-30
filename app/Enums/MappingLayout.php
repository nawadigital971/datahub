<?php

namespace App\Enums;

use App\Enums\Concerns\HasKey;

/**
 * Known spreadsheet layouts an import can be mapped from.
 */
enum MappingLayout: int
{
    use HasKey;

    case Master = 1;
    case FormExport = 2;
    case BusinessDirectory = 3;
    case Custom = 4;

    public function label(): string
    {
        return match ($this) {
            self::Master => 'Master',
            self::FormExport => 'Form export',
            self::BusinessDirectory => 'Business directory',
            self::Custom => 'Custom',
        };
    }
}
