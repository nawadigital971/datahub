<?php

namespace App\DTOs\Imports;

final readonly class StageContactsData
{
    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function __construct(
        public int $userId,
        public ?string $sourceName,
        public string $label,
        public array $rows,
        public ?int $batchId = null,
    ) {}
}
