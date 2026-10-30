<?php

namespace App\Enums;

/**
 * Where a spreadsheet column goes when it is mapped. "extra.<key>" (keep under a name) is also accepted.
 */
enum ImportTarget: string
{
    case NameEn = 'name_en';
    case NameAr = 'name_ar';
    case Name = 'name';
    case Company = 'company_name';
    case Type = 'type';
    case Gender = 'gender';
    case Phone = 'phone';
    case Landline = 'landline';
    case Email = 'email';
    case Nationality = 'nationality';
    case Address = 'address_raw';
    case Region = 'region';
    case Area = 'area';
    case Sector = 'sector';
    case Source = 'source';
    case SeenYear = 'seen_year';
    case Notes = 'notes';
    case Extra = 'extra';
    case Ignore = 'ignore';

    public function label(): string
    {
        return match ($this) {
            self::NameEn => 'English name',
            self::NameAr => 'Arabic name',
            self::Name => 'Name (Arabic or English, detected)',
            self::Company => 'Company',
            self::Type => 'Record type (person / business)',
            self::Gender => 'Gender',
            self::Phone => 'Phone / mobile',
            self::Landline => 'Landline',
            self::Email => 'Email',
            self::Nationality => 'Nationality',
            self::Address => 'Address / location',
            self::Region => 'Emirate / region / city',
            self::Area => 'Area / district',
            self::Sector => 'Business sector',
            self::Source => 'Source (the list this row came from)',
            self::SeenYear => 'Date or year collected',
            self::Notes => 'Notes (call codes, landlines…)',
            self::Extra => 'Keep as extra information',
            self::Ignore => 'Ignore',
        };
    }

    /**
     * True for a known target or "extra.<key>".
     */
    public static function isValid(string $target): bool
    {
        return self::tryFrom($target) !== null || preg_match('/^extra\.[a-z0-9_]{1,40}$/', $target) === 1;
    }

    /**
     * Targets that give a way to contact someone; a mapping needs at least one.
     *
     * @return list<string>
     */
    public static function contactTargets(): array
    {
        return [self::Phone->value, self::Landline->value, self::Email->value, self::Notes->value];
    }
}
