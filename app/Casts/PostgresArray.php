<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Casts a one-dimensional Postgres array column (`smallint[]`, `text[]`) to a PHP list.
 * Elements are simple values (ids or slug-like codes), so quoting is only applied when needed.
 *
 * Use as `PostgresArray::class.':int'` or `PostgresArray::class.':string'`.
 *
 * @implements CastsAttributes<list<int|string>, list<int|string>>
 */
class PostgresArray implements CastsAttributes
{
    public function __construct(private string $type = 'string')
    {
        if (! in_array($this->type, ['int', 'string'], true)) {
            throw new InvalidArgumentException("Unsupported Postgres array type [{$this->type}].");
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<int|string>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        if (! is_string($value) || $value === '{}' || $value === '') {
            return [];
        }

        $elements = str_getcsv(substr($value, 1, -1), ',', '"', '\\');

        return array_map(
            fn (?string $element): int|string => $this->type === 'int' ? (int) $element : (string) $element,
            $elements,
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        $elements = array_map(
            fn (mixed $element): string => $this->type === 'int'
                ? (string) (int) $element
                : '"'.addcslashes((string) $element, '"\\').'"',
            is_array($value) ? $value : [],
        );

        return '{'.implode(',', $elements).'}';
    }
}
