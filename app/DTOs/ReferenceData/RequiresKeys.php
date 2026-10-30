<?php

namespace App\DTOs\ReferenceData;

use InvalidArgumentException;

trait RequiresKeys
{
    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private static function aliases(array $row): array
    {
        $aliases = is_array($row['aliases'] ?? null) ? $row['aliases'] : [];

        return array_values(array_unique(array_filter(array_map(
            fn (mixed $alias): string => is_string($alias) ? trim($alias) : '',
            $aliases,
        ))));
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private static function requireStrings(array $row, array $keys, string $context): void
    {
        foreach ($keys as $key) {
            if (! is_string($row[$key] ?? null) || trim($row[$key]) === '') {
                throw new InvalidArgumentException("{$context}: \"{$key}\" is required.");
            }
        }
    }
}
