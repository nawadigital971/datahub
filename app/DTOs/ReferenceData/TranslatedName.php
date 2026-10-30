<?php

namespace App\DTOs\ReferenceData;

use InvalidArgumentException;

/**
 * Validates a translatable `name` from reference data: the fallback locale is required,
 * and only locales listed in config('app.locales') are kept.
 */
final readonly class TranslatedName
{
    /**
     * @return array<string, string>
     */
    public static function fromArray(mixed $name, string $context): array
    {
        $fallbackLocale = config('app.fallback_locale');

        if (! is_array($name) || ! is_string($name[$fallbackLocale] ?? null) || trim($name[$fallbackLocale]) === '') {
            throw new InvalidArgumentException("{$context}: name.{$fallbackLocale} is required.");
        }

        /** @var list<string> $locales */
        $locales = config('app.locales');

        return array_filter(
            array_intersect_key($name, array_flip($locales)),
            fn (mixed $value): bool => is_string($value) && trim($value) !== '',
        );
    }
}
