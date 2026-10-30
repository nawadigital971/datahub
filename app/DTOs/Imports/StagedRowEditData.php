<?php

namespace App\DTOs\Imports;

use App\Http\Requests\Imports\UpdateStagedRowRequest;

final readonly class StagedRowEditData
{
    public function __construct(
        public string $field,
        public string|int|null $value,
        public int $userId,
    ) {}

    public static function fromRequest(UpdateStagedRowRequest $request): self
    {
        $value = $request->validated('value');

        return new self(
            $request->string('field')->toString(),
            is_int($value) || is_string($value) ? (is_string($value) && trim($value) === '' ? null : $value) : null,
            (int) $request->user()?->id,
        );
    }
}
