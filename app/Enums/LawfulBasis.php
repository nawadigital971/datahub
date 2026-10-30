<?php

namespace App\Enums;

use App\Enums\Concerns\HasKey;

/**
 * GDPR-style lawful basis recorded per source.
 */
enum LawfulBasis: int
{
    use HasKey;

    case Consent = 1;
    case Contract = 2;
    case LegitimateInterest = 3;
    case Unknown = 4;

    public function label(): string
    {
        return match ($this) {
            self::Consent => 'Consent',
            self::Contract => 'Contract',
            self::LegitimateInterest => 'Legitimate interest',
            self::Unknown => 'Unknown',
        };
    }
}
