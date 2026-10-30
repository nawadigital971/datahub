<?php

namespace App\DTOs\ReferenceData;

use App\Http\Requests\ReferenceData\DeleteReferenceItemRequest;
use App\Http\Requests\ReferenceData\SaveReferenceItemRequest;

/**
 * One reference item to create, update or delete (from the dashboard or from MCP).
 */
final readonly class ReferenceItemData
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function __construct(
        public string $type,
        public ?int $id,
        public array $values,
        public ?int $userId,
    ) {}

    public static function fromRequest(SaveReferenceItemRequest|DeleteReferenceItemRequest $request): self
    {
        $item = $request->route('item');
        /** @var array<string, mixed> $values */
        $values = $request instanceof SaveReferenceItemRequest ? $request->validated('values') : [];

        return new self(
            $request->typeKey(),
            is_numeric($item) ? (int) $item : null,
            $values,
            is_numeric($request->user()?->getAuthIdentifier()) ? (int) $request->user()->getAuthIdentifier() : null,
        );
    }

    /**
     * @param  array{type: string, id?: int|null, values?: array<string, mixed>}  $data
     */
    public static function fromArray(array $data, ?int $userId): self
    {
        return new self($data['type'], $data['id'] ?? null, $data['values'] ?? [], $userId);
    }
}
