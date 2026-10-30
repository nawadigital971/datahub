<?php

namespace App\Actions\Contacts;

use Illuminate\Support\Facades\DB;

class RefreshFacetCountsAction
{
    /**
     * Rebuild the unfiltered facet counts without blocking readers.
     */
    public function handle(): void
    {
        DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY facet_counts');
    }
}
