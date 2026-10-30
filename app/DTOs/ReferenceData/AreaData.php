<?php

namespace App\DTOs\ReferenceData;

final readonly class AreaData
{
    use RequiresKeys;

    /**
     * @param  array<string, string>  $name
     * @param  list<string>  $aliases
     */
    public function __construct(
        public string $regionSlug,
        public string $slug,
        public array $name,
        public array $aliases,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        self::requireStrings($row, ['region', 'slug'], 'Area');

        /** @var string $region */
        $region = $row['region'];
        /** @var string $slug */
        $slug = $row['slug'];
        $aliases = is_array($row['aliases'] ?? null) ? $row['aliases'] : [];

        return new self(
            $region,
            $slug,
            TranslatedName::fromArray($row['name'] ?? null, "Area {$slug}"),
            array_values(array_unique(array_filter(array_map(
                fn (mixed $alias): string => is_string($alias) ? trim($alias) : '',
                $aliases,
            )))),
        );
    }
}
