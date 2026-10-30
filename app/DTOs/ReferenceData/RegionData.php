<?php

namespace App\DTOs\ReferenceData;

final readonly class RegionData
{
    use RequiresKeys;

    /**
     * @param  array<string, string>  $name
     * @param  list<string>  $aliases
     */
    public function __construct(
        public string $countryIso2,
        public string $slug,
        public array $name,
        public array $aliases = [],
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        self::requireStrings($row, ['country', 'slug'], 'Region');

        /** @var string $country */
        $country = $row['country'];
        /** @var string $slug */
        $slug = $row['slug'];

        return new self(strtoupper($country), $slug, TranslatedName::fromArray($row['name'] ?? null, "Region {$slug}"), self::aliases($row));
    }
}
