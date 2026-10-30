<?php

namespace App\Enums;

/**
 * Problems detected on staged import rows (stored in staged_rows.issue_codes).
 */
enum IssueCode: string
{
    case InvalidPhone = 'invalid_phone';
    case LandlineInMobile = 'landline_in_mobile';
    case PhoneInNotes = 'phone_in_notes';
    case MissingContactMethod = 'missing_contact_method';
    case EmailInvalid = 'email_invalid';
    case EmailTypo = 'email_typo';
    case JunkName = 'junk_name';
    case UnverifiedTransliteration = 'unverified_transliteration';
    case GenderSuspect = 'gender_suspect';
    case UnknownArea = 'unknown_area';
    case UnknownRegion = 'unknown_region';
    case Suppressed = 'suppressed';
    case StaleData = 'stale_data';

    /**
     * Errors block a row (it is skipped unless fixed); warnings are worth a look.
     */
    public function severity(): RowSeverity
    {
        return $this === self::MissingContactMethod ? RowSeverity::Error : RowSeverity::Warning;
    }

    public function label(): string
    {
        return match ($this) {
            self::InvalidPhone => 'Invalid phone',
            self::LandlineInMobile => 'Landline in mobile column',
            self::PhoneInNotes => 'Phone found in notes',
            self::MissingContactMethod => 'No phone or email',
            self::EmailInvalid => 'Invalid email',
            self::EmailTypo => 'Email domain typo',
            self::JunkName => 'Junk name',
            self::UnverifiedTransliteration => 'Unverified transliteration',
            self::GenderSuspect => 'Gender looks wrong',
            self::UnknownArea => 'Unknown area',
            self::UnknownRegion => 'Unknown emirate',
            self::Suppressed => 'On do-not-contact list',
            self::StaleData => 'Stale data',
        };
    }
}
