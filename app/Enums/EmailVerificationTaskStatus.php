<?php

namespace App\Enums;

/**
 * Lifecycle of a bulk task sent to the email verifier.
 */
enum EmailVerificationTaskStatus: int
{
    case Submitted = 1;
    case Completed = 2;
    case Failed = 3;

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }
}
