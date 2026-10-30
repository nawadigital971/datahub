<?php

namespace App\Queries\Imports;

use App\Actions\Normalization\NormalizeArabicTextAction;
use Illuminate\Container\Attributes\Scoped;
use libphonenumber\PhoneNumberUtil;
use Locale;

/**
 * Every country with a phone numbering plan, named in English and Arabic by ICU. Used to add a country the
 * first time a contact's phone or nationality comes from it.
 */
#[Scoped]
class WorldCountries
{
    /** @var array<string, string>|null normalized name => ISO code */
    private ?array $byName = null;

    public function __construct(private NormalizeArabicTextAction $normalize) {}

    /**
     * The ISO code of a country named in English or Arabic ("Kenya", "كينيا"), or null.
     */
    public function isoForName(?string $name): ?string
    {
        $key = $this->normalize->handle($name);
        if ($key === '') {
            return null;
        }

        if (preg_match('/^[a-z]{2}$/', $key) === 1 && $this->attributes($key) !== null) {
            return strtoupper($key);
        }

        if ($this->byName === null) {
            $this->byName = [];
            foreach (PhoneNumberUtil::getInstance()->getSupportedRegions() as $iso) {
                foreach (['en', 'ar'] as $locale) {
                    $this->byName[$this->normalize->handle($this->name($iso, $locale))] ??= $iso;
                }
            }
        }

        return $this->byName[$key] ?? null;
    }

    /**
     * @return array{iso2: string, dial_code: string, name: array{en: string, ar: string}}|null
     */
    public function attributes(string $iso): ?array
    {
        $iso = strtoupper($iso);
        $dialCode = PhoneNumberUtil::getInstance()->getCountryCodeForRegion($iso);
        if ($dialCode === 0) {
            return null;
        }

        return ['iso2' => $iso, 'dial_code' => (string) $dialCode, 'name' => ['en' => $this->name($iso, 'en'), 'ar' => $this->name($iso, 'ar')]];
    }

    private function name(string $iso, string $locale): string
    {
        $name = Locale::getDisplayRegion('und_'.$iso, $locale);

        return $name !== false && $name !== '' ? $name : $iso;
    }
}
