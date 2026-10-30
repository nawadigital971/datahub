<?php

namespace App\Actions\Contacts;

use App\Queries\Audience\AudienceCache;
use Illuminate\Support\Facades\Cache;

class BustAudienceCacheAction
{
    /**
     * Invalidate every cached Audience count (call after contacts or reference data change).
     */
    public function handle(): void
    {
        Cache::increment(AudienceCache::VERSION_KEY);
    }
}
