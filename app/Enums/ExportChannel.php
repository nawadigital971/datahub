<?php

namespace App\Enums;

use App\Enums\Concerns\HasKey;

enum ExportChannel: int
{
    use HasKey;

    case Email = 1;
    case Whatsapp = 2;
    case Sms = 3;
    case Meta = 4;
    case Google = 5;
    case Calls = 6;

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::Whatsapp => 'WhatsApp',
            self::Sms => 'SMS',
            self::Meta => 'Meta audience',
            self::Google => 'Google Customer Match',
            self::Calls => 'Call centre',
        };
    }

    /**
     * Which contacts this channel can reach (do-not-contact is always excluded separately).
     *
     * @return literal-string
     */
    public function contactCondition(): string
    {
        return match ($this) {
            self::Email => 'has_email',
            self::Whatsapp => 'has_mobile',
            self::Sms => 'has_uae_mobile',
            self::Meta, self::Google => '(has_mobile OR has_email)',
            self::Calls => '(has_mobile OR has_landline)',
        };
    }

    /**
     * Ad platforms accept SHA-256 hashed identifiers; the other channels need readable data.
     */
    public function supportsHashing(): bool
    {
        return in_array($this, [self::Meta, self::Google], true);
    }

    /**
     * CSV header row, in the column names each platform expects.
     *
     * @return list<string>
     */
    public function columns(): array
    {
        return match ($this) {
            self::Email => ['email', 'first_name', 'last_name', 'emirate', 'source'],
            self::Whatsapp => ['phone', 'name', 'language'],
            self::Sms => ['msisdn', 'name'],
            self::Meta => ['email', 'phone', 'fn', 'ln', 'ct', 'country'],
            self::Google => ['Email', 'Phone', 'First Name', 'Last Name', 'Country'],
            self::Calls => ['name', 'company', 'mobile', 'landline', 'emirate', 'area', 'last_outcome'],
        };
    }

    /**
     * Excel needs a byte-order mark to show Arabic correctly; ad platforms prefer plain UTF-8.
     */
    public function writesByteOrderMark(): bool
    {
        return ! $this->supportsHashing();
    }
}
