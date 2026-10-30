<?php

namespace App\Enums;

/**
 * Why a contact or value is on the do-not-contact list.
 */
enum DncReason: int
{
    case OptedOut = 1;
    case Complaint = 2;
    case WrongPerson = 3;
    case Legal = 4;
    case Other = 9;

    public function label(): string
    {
        return match ($this) {
            self::OptedOut => 'Opted out',
            self::Complaint => 'Complaint',
            self::WrongPerson => 'Wrong person',
            self::Legal => 'Legal request',
            self::Other => 'Other',
        };
    }
}
