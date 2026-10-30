<?php

namespace App\DTOs\ReferenceData;

use App\Enums\CleaningRuleType;
use InvalidArgumentException;

final readonly class CleaningRuleData
{
    public function __construct(
        public CleaningRuleType $type,
        public string $key,
        public ?string $value,
        public ?string $notes = null,
        public ?int $position = null,
    ) {}

    /**
     * @param  array<string, mixed>  $row
     */
    public static function fromArray(array $row): self
    {
        $type = CleaningRuleType::tryFrom(is_string($row['type'] ?? null) ? $row['type'] : '')
            ?? throw new InvalidArgumentException('Cleaning rule: unknown "type".');
        $key = is_scalar($row['key'] ?? null) ? trim((string) $row['key']) : '';
        if ($key === '') {
            throw new InvalidArgumentException('Cleaning rule: "key" is required.');
        }
        $value = is_scalar($row['value'] ?? null) ? trim((string) $row['value']) : null;
        $notes = is_string($row['notes'] ?? null) ? trim($row['notes']) : null;

        return new self($type, $key, $value === '' ? null : $value, $notes === '' ? null : $notes, isset($row['position']) && is_numeric($row['position']) ? (int) $row['position'] : null);
    }
}
