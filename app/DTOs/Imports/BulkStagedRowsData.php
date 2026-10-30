<?php

namespace App\DTOs\Imports;

use App\Http\Requests\Imports\BulkUpdateStagedRowsRequest;

final readonly class BulkStagedRowsData
{
    /**
     * @param  list<int>|null  $ids  Selected rows, or null for "all rows matching $filters".
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        public string $operation,
        public string|int|null $value,
        public ?array $ids,
        public array $filters,
    ) {}

    public static function fromRequest(BulkUpdateStagedRowsRequest $request): self
    {
        $value = $request->validated('value');
        $filters = $request->validated('filter');

        return new self(
            $request->string('operation')->toString(),
            is_int($value) || is_string($value) ? $value : null,
            $request->boolean('all_matching') ? null : array_values(array_map('intval', (array) $request->validated('ids', []))),
            is_array($filters) ? $filters : [],
        );
    }
}
