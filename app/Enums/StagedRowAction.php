<?php

namespace App\Enums;

use App\Enums\Concerns\HasKey;

enum StagedRowAction: int
{
    use HasKey;

    case Create = 1;
    case Merge = 2;
    case Skip = 3;

    public function label(): string
    {
        return match ($this) {
            self::Create => 'Create',
            self::Merge => 'Merge',
            self::Skip => 'Skip',
        };
    }
}
