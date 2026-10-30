<?php

namespace App\DTOs\ReferenceData;

final readonly class CountryData
{
    use RequiresKeys;

    /**
     * @param  array<string, string>  $name
     * @param  list<string>  $aliases
     */
    public function __construct(
        public string $iso2,
        public string $dialCode,
        public array $name,
        public array $aliases = [],
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        self::requireStrings($row, ['iso2', 'dial_code'], 'Country');

        /** @var string $iso2 */
        $iso2 = $row['iso2'];
        /** @var string $dialCode */
        $dialCode = $row['dial_code'];

        return new self(strtoupper($iso2), ltrim($dialCode, '+'), TranslatedName::fromArray($row['name'] ?? null, "Country {$iso2}"), self::aliases($row));
    }
}
