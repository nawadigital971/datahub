<?php

namespace App\Jobs;

use App\Actions\Contacts\RefreshFacetCountsAction;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Rebuilds the unfiltered facet counts after contacts were edited (one rebuild per minute at most).
 */
class RefreshFacetCountsJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 60;

    public function handle(RefreshFacetCountsAction $refresh): void
    {
        $refresh->handle();
    }
}
