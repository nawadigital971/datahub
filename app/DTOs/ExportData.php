<?php

namespace App\DTOs;

use App\Enums\ExportChannel;
use App\Http\Requests\Exports\StoreExportRequest;

final readonly class ExportData
{
    public function __construct(
        public int $userId,
        public ExportChannel $channel,
        public bool $hashed,
        public string $purpose,
        public AudienceFilters $filters,
    ) {}

    public static function fromRequest(StoreExportRequest $request): self
    {
        $channel = ExportChannel::fromKey($request->string('channel')->toString());
        $filters = $request->validated('filters');

        return new self(
            (int) $request->user()?->id,
            $channel,
            $channel->supportsHashing() && $request->boolean('hashed'),
            trim($request->string('purpose')->toString()),
            AudienceFilters::fromArray(is_array($filters) ? $filters : []),
        );
    }
}
