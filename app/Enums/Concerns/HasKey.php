<?php

namespace App\Enums\Concerns;

use Illuminate\Support\Str;
use ValueError;

/**
 * Lets an int-backed enum be referenced by a readable snake_case key (e.g. "legitimate_interest")
 * in JSON reference data.
 */
trait HasKey
{
    public function key(): string
    {
        return Str::snake($this->name);
    }

    public static function tryFromKey(string $key): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->key() === $key) {
                return $case;
            }
        }

        return null;
    }

    public static function fromKey(string $key): self
    {
        foreach (self::cases() as $case) {
            if ($case->key() === $key) {
                return $case;
            }
        }

        throw new ValueError(sprintf('"%s" is not a valid key for enum %s.', $key, self::class));
    }
}
