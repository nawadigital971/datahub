<?php

namespace App\Enums;

/**
 * The kinds of cleaning rules used when files are analysed. Each rule is a key (what is looked for)
 * and, for most types, a value (what it means).
 */
enum CleaningRuleType: string
{
    case CallCode = 'call_code';
    case CallPhrase = 'call_phrase';
    case EmailDomainFix = 'email_domain_fix';
    case EmailTldFix = 'email_tld_fix';
    case JunkName = 'junk_name';
    case FirstName = 'first_name';
    case LandlinePrefix = 'landline_prefix';
    case HeaderHint = 'header_hint';
    case BlockedFilename = 'blocked_filename';
    case GenderWord = 'gender_word';
    case BusinessWord = 'business_word';
    case NoteLabel = 'note_label';
    case PhoneSeparator = 'phone_separator';

    /** What a note label introduces. */
    public const array NOTE_LABEL_MEANINGS = ['landline', 'contract', 'agent', 'verify_transliteration'];

    public function label(): string
    {
        return match ($this) {
            self::CallCode => 'Call-centre code',
            self::CallPhrase => 'Call outcome phrase',
            self::EmailDomainFix => 'Email domain fix',
            self::EmailTldFix => 'Email ending fix',
            self::JunkName => 'Junk name',
            self::FirstName => 'First name (gender check)',
            self::LandlinePrefix => 'Landline prefix → emirate',
            self::HeaderHint => 'Column header hint',
            self::BlockedFilename => 'Blocked file name',
            self::GenderWord => 'Gender word',
            self::BusinessWord => 'Business word',
            self::NoteLabel => 'Notes label',
            self::PhoneSeparator => 'Word between phone numbers',
        };
    }

    /**
     * What the key and value mean, for people and for Claude.
     */
    public function description(): string
    {
        return match ($this) {
            self::CallCode => 'An uppercase code in Notes (e.g. "NI"). Value: the call outcome (not_interested, call_back, switched_off, wrong_number, subscribed).',
            self::CallPhrase => 'Words in Notes (e.g. "not interested"). Value: the call outcome, as for call codes.',
            self::EmailDomainFix => 'A misspelled email domain (e.g. "gamil.com"). Value: the correct domain; offered as a suggestion.',
            self::EmailTldFix => 'A misspelled ending on any domain (e.g. ".comm"). Value: the correct ending.',
            self::JunkName => 'A name that is not a real name (e.g. "test"). No value.',
            self::FirstName => 'A first name (lowercase; Arabic without diacritics). Value: "m" or "f". Used to flag genders that look wrong.',
            self::LandlinePrefix => 'The start of a UAE landline in E.164 (e.g. "+9714"). Value: the emirate slug (e.g. "dubai").',
            self::HeaderHint => 'A word found in a column header (e.g. "mobile"), or "=word" for an exact header. Value: the mapping target (phone, landline, email, name, name_en, name_ar, company_name, source, gender, nationality, address_raw, region, sector, seen_year, notes, type). Checked in order.',
            self::BlockedFilename => 'Words in a file name that mean the file must never be imported (e.g. employee data). Value: the reason shown.',
            self::GenderWord => 'A word in the gender column (e.g. "mr", "ذكر", "miss"). Value: "m" or "f".',
            self::BusinessWord => 'A word in the record-type column that means a business, not a person (e.g. "company", "شركة"). No value.',
            self::NoteLabel => 'A label in Notes followed by a value (e.g. "tel", "contract no", "agent"). Value: what it introduces: landline, contract, agent, or verify_transliteration (a tag on its own).',
            self::PhoneSeparator => 'A word between two phone numbers in one cell (e.g. "or", "او"). No value. Symbols such as / , ; | & always separate numbers.',
        };
    }

    public function needsValue(): bool
    {
        return ! in_array($this, [self::JunkName, self::BusinessWord, self::PhoneSeparator], true);
    }
}
