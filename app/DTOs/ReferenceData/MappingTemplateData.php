<?php

namespace App\DTOs\ReferenceData;

use App\Enums\MappingLayout;
use InvalidArgumentException;

final readonly class MappingTemplateData
{
    use RequiresKeys;

    /**
     * @param  array<string, string>  $columnMap  Spreadsheet header => staged-row field.
     */
    public function __construct(
        public string $name,
        public MappingLayout $layout,
        public array $columnMap,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        self::requireStrings($row, ['name', 'layout'], 'Mapping template');

        /** @var string $name */
        $name = $row['name'];
        /** @var string $layout */
        $layout = $row['layout'];
        $columnMap = $row['column_map'] ?? null;

        if (! is_array($columnMap) || $columnMap === []) {
            throw new InvalidArgumentException("Mapping template {$name}: \"column_map\" is required.");
        }

        return new self($name, MappingLayout::fromKey($layout), array_map(strval(...), $columnMap));
    }
}
