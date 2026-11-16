<?php

namespace App\Enums;

/**
 * Validation state of a contact's primary email.
 */
enum EmailStatus: int
{
    case Unknown = 0;
    case Valid = 1;
    case Invalid = 2;
    case TypoFixed = 3;

    /** Reachable but uncertain (catch-all domain, role address, full inbox) — kept in email reach. */
    case Risky = 4;

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Unknown',
            self::Valid => 'Valid',
            self::Invalid => 'Invalid',
            self::TypoFixed => 'Typo fixed',
            self::Risky => 'Risky',
        };
    }

    /**
     * Map an email verifier (Reoon) result to a status; null keeps the current status.
     */
    public static function fromVerifierResult(string $result): ?self
    {
        return match ($result) {
            'safe', 'valid' => self::Valid,
            'invalid', 'disabled', 'disposable', 'spamtrap', 'rejected' => self::Invalid,
            'catch_all', 'role_account', 'inbox_full' => self::Risky,
            default => null,
        };
    }
}
