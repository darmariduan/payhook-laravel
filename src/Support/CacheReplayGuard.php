<?php

namespace PayHook\Laravel\Support;

use Illuminate\Contracts\Cache\Repository;

final class CacheReplayGuard implements ReplayGuard
{
    public function __construct(private readonly Repository $cache)
    {
    }

    public function claim(string $nonce, int $ttlSeconds): bool
    {
        // Cache::add bersifat atomik pada driver yang mendukung (redis, database, memcached, file)
        return $this->cache->add('payhook:replay:' . hash('sha256', $nonce), 1, $ttlSeconds);
    }
}
