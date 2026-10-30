<?php

namespace App\Queries\Audience;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Redis cache for expensive Audience counts, keyed by a hash of the normalized filters.
 * Every key contains a version number; BustAudienceCacheAction bumps it after an import commit,
 * which invalidates all cached counts at once.
 */
class AudienceCache
{
    public const string VERSION_KEY = 'audience:version';

    public const int TTL_SECONDS = 600;

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $callback
     * @return TValue
     */
    public function remember(string $kind, string $hash, Closure $callback, int $ttl = self::TTL_SECONDS): mixed
    {
        return Cache::remember("audience:v{$this->version()}:{$kind}:{$hash}", $ttl, $callback);
    }

    public function version(): int
    {
        $version = Cache::get(self::VERSION_KEY, 0);

        return is_numeric($version) ? (int) $version : 0;
    }
}
