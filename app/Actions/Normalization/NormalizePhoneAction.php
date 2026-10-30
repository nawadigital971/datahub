<?php

namespace App\Actions\Normalization;

use App\DTOs\Normalization\PhoneResult;
use App\Enums\CleaningRuleType;
use App\Enums\PhoneType;
use App\Queries\Imports\CleaningRulesQuery;
use App\Settings\DataSettings;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;

/**
 * Turns the phone shapes found in the files (numbers without a country code use the default phone
 * country from the data settings) (9715XXXXXXXX, 050-123 4567, 04-4473955, 00966…, Arabic digits,
 * several numbers in one cell) into E.164, tells mobiles from landlines and derives the country.
 */
class NormalizePhoneAction
{
    /** Symbols that always separate two numbers in one cell; words come from the cleaning rules. */
    private const string SEPARATOR_SYMBOLS = '\s*(?:/|\\\\|,|;|\||\n|&)\s*';

    private ?string $separators = null;

    private PhoneNumberUtil $util;

    public function __construct(private DataSettings $settings, private CleaningRulesQuery $rules)
    {
        $this->util = PhoneNumberUtil::getInstance();
    }

    /**
     * @return list<PhoneResult> One result per number in the cell (invalid ones keep their raw text).
     */
    public function handle(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $parts = preg_split($this->separators(), $this->asciiDigits($raw)) ?: [];

        $results = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '' && preg_match('/\d/', $part) === 1) {
                $results[] = $this->parse($part);
            }
        }

        return $results;
    }

    private function separators(): string
    {
        if ($this->separators === null) {
            $words = array_filter(array_map(trim(...), array_keys($this->rules->map(CleaningRuleType::PhoneSeparator))), fn (string $word): bool => $word !== '');
            usort($words, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
            $pattern = self::SEPARATOR_SYMBOLS;
            if ($words !== []) {
                $pattern .= '|\s+(?:'.implode('|', array_map(fn (string $word): string => preg_quote($word, '#'), $words)).')\s+';
            }
            $this->separators = '#'.$pattern.'#iu';
        }

        return $this->separators;
    }

    private function parse(string $raw): PhoneResult
    {
        $hasPlus = str_starts_with(ltrim($raw), '+');
        $digits = (string) preg_replace('/\D/', '', $raw);

        if (strlen($digits) < 6 || strlen($digits) > 15 + 2) {
            return new PhoneResult($raw, null, null, null);
        }

        $candidates = match (true) {
            str_starts_with($digits, '00') => ['+'.substr($digits, 2)],
            $hasPlus => ['+'.$digits],
            str_starts_with($digits, '971') && strlen($digits) >= 11 => ['+'.$digits],
            ! str_starts_with($digits, '0') && strlen($digits) >= 11 => ['+'.$digits, $digits],
            default => [$digits],
        };

        foreach ($candidates as $candidate) {
            try {
                $number = $this->util->parse($candidate, strtoupper($this->settings->default_phone_region));
            } catch (NumberParseException) {
                continue;
            }

            if (! $this->util->isValidNumber($number)) {
                continue;
            }

            $type = match ($this->util->getNumberType($number)) {
                PhoneNumberType::MOBILE, PhoneNumberType::FIXED_LINE_OR_MOBILE, PhoneNumberType::PAGER => PhoneType::Mobile,
                default => PhoneType::Landline,
            };

            return new PhoneResult(
                $raw,
                $this->util->format($number, PhoneNumberFormat::E164),
                $type,
                $this->util->getRegionCodeForNumber($number),
            );
        }

        return new PhoneResult($raw, null, null, null);
    }

    private function asciiDigits(string $value): string
    {
        return strtr($value, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]);
    }
}
