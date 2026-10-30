<?php

namespace App\DTOs;

use App\Http\Requests\Audience\StoreSegmentRequest;

final readonly class SegmentData
{
    public function __construct(
        public string $name,
        public bool $isShared,
        public AudienceFilters $filters,
        public ?int $userId,
    ) {}

    public static function fromRequest(StoreSegmentRequest $request): self
    {
        $filters = $request->validated('filters');

        return new self(
            trim($request->string('name')->toString()),
            $request->boolean('is_shared'),
            AudienceFilters::fromArray(is_array($filters) ? $filters : []),
            $request->user()?->id,
        );
    }
}
