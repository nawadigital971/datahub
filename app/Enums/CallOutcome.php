<?php

namespace App\Enums;

use App\Enums\Concerns\HasKey;

/**
 * Outcome of the last call-centre call (from CB/NI/SW/D notes).
 */
enum CallOutcome: int
{
    use HasKey;

    case NotInterested = 1;
    case CallBack = 2;
    case SwitchedOff = 3;
    case WrongNumber = 4;
    case Subscribed = 5;

    public function label(): string
    {
        return match ($this) {
            self::NotInterested => 'Not interested',
            self::CallBack => 'Call back',
            self::SwitchedOff => 'Switched off',
            self::WrongNumber => 'Wrong number',
            self::Subscribed => 'Subscribed',
        };
    }
}
