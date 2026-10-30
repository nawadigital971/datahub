<?php

namespace App\DTOs\ReferenceData;

/**
 * A lookup identified only by slug + translated name (sectors, source groups).
 */
final readonly class SlugLookupData
{
    use RequiresKeys;

    /**
     * @param  array<string, string>  $name
     */
    public function __construct(
        public string $slug,
        public array $name,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        self::requireStrings($row, ['slug'], 'Lookup');

        /** @var string $slug */
        $slug = $row['slug'];

        return new self($slug, TranslatedName::fromArray($row['name'] ?? null, "Lookup {$slug}"));
    }
}
