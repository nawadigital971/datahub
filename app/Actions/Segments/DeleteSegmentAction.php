<?php

namespace App\Actions\Segments;

use App\Models\Segment;

class DeleteSegmentAction
{
    /**
     * Delete a saved segment. Past exports keep their own copy of the filters.
     */
    public function handle(Segment $segment): void
    {
        $segment->delete();
    }
}
