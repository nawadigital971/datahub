<?php

namespace App\Actions\Segments;

use App\DTOs\SegmentData;
use App\Models\Segment;

class CreateSegmentAction
{
    /**
     * Save a filter set. Only values that differ from the defaults are stored.
     */
    public function handle(SegmentData $data): Segment
    {
        return Segment::query()->create([
            'name' => $data->name,
            'filters' => $data->filters->toQuery(),
            'user_id' => $data->userId,
            'is_shared' => $data->isShared,
        ]);
    }
}
