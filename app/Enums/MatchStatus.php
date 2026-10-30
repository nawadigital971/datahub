<?php

namespace App\Enums;

use App\Enums\Concerns\HasKey;

/**
 * How a staged row relates to existing contacts and other rows in its batch.
 */
enum MatchStatus: int
{
    use HasKey;

    case New = 1;
    case MatchesContact = 2;
    case DupInBatch = 3;
    case Conflict = 4;

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::MatchesContact => 'Matches contact',
            self::DupInBatch => 'Duplicate in batch',
            self::Conflict => 'Conflict',
        };
    }
}
