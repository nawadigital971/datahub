<?php

namespace App\Enums;

enum ImportBatchStatus: int
{
    case Analyzing = 1;
    case Ready = 2;
    case Committing = 3;
    case Committed = 4;
    case Discarded = 5;
    case Failed = 6;
    case Mapping = 7;

    public function label(): string
    {
        return match ($this) {
            self::Analyzing => 'Analyzing',
            self::Ready => 'Ready',
            self::Committing => 'Committing',
            self::Committed => 'Committed',
            self::Discarded => 'Discarded',
            self::Failed => 'Failed',
            self::Mapping => 'Needs mapping',
        };
    }
}
