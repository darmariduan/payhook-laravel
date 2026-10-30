<?php

namespace PayHook\Laravel;

use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Carbon;
use PayHook\Laravel\Data\Heartbeat;
use PayHook\Laravel\Support\Signature;
use PayHook\Laravel\Support\UniqueAmount;

/**
 * API utama paket (juga tersedia lewat facade PayHook).
 */
class PayHookManager
{
    private const DEVICES_KEY = 'payhook:hb:devices';

    public function __construct(
        private readonly CacheFactory $cacheFactory,
        private readonly array $config,
    ) {
    }

    public function cache(): Repository
    {
        return $this->cacheFactory->store($this->config['cache_store'] ?? null);
    }

    /**
     * Buat nominal unik untuk invoice baru.
     *
     * @param callable(int):bool $isTaken true bila nominal masih dipakai invoice aktif lain
     */
    public function uniqueAmount(int $base, callable $isTaken, int $min = 1, int $max = 999): int
    {
        return UniqueAmount::generate($base, $isTaken, $min, $max);
    }

    /**
     * Hitung signature untuk menguji endpoint Anda sendiri (mis. di test).
     */
    public function sign(string $rawBody, ?int $timestamp = null, ?string $secret = null): array
    {
        $ts = (string) ($timestamp ?? Carbon::now()->getTimestamp());
        $secret ??= (string) ($this->config['secret'] ?? '');

        return [
            'X-Payhook-Timestamp' => $ts,
            'X-Payhook-Nonce' => bin2hex(random_bytes(16)),
            'X-Payhook-Signature' => 'sha256=' . Signature::compute($secret, $ts, $rawBody),
        ];
    }

    public function recordHeartbeat(Heartbeat $heartbeat): void
    {
        $cache = $this->cache();
        // Disimpan permanen: perangkat yang mati tetap dikenali (isOnline() menilai dari received_at)
        $cache->forever('payhook:hb:' . $heartbeat->deviceId, $heartbeat->toArray());

        $devices = (array) $cache->get(self::DEVICES_KEY, []);
        if (in_array($heartbeat->deviceId, $devices, true)) {
            return;
        }

        $update = function () use ($cache, $heartbeat) {
            $max = max(1, (int) ($this->config['heartbeat']['max_devices'] ?? 100));
            $devices = (array) $cache->get(self::DEVICES_KEY, []);
            if (! in_array($heartbeat->deviceId, $devices, true)) {
                $devices[] = $heartbeat->deviceId;
                // Batasi jumlah perangkat yang dilacak agar cache tidak membengkak
                $cache->forever(self::DEVICES_KEY, array_values(array_slice($devices, -$max)));
            }
        };

        $store = $cache->getStore();
        if ($store instanceof LockProvider) {
            // Cegah dua heartbeat bersamaan saling menimpa daftar perangkat
            $store->lock('payhook:hb:devices:lock', 5)->block(3, $update);
        } else {
            $update();
        }
    }

    /**
     * @return array<string,mixed>|null
     */
    public function lastHeartbeat(string $deviceId): ?array
    {
        $v = $this->cache()->get('payhook:hb:' . $deviceId);

        return is_array($v) ? $v : null;
    }

    public function isOnline(string $deviceId, ?int $offlineAfter = null): bool
    {
        $hb = $this->lastHeartbeat($deviceId);
        if ($hb === null) {
            return false;
        }
        $limit = $offlineAfter ?? (int) ($this->config['heartbeat']['offline_after'] ?? 900);

        return (Carbon::now()->getTimestamp() - (int) ($hb['received_at'] ?? 0)) <= $limit && ($hb['listener_connected'] ?? false) === true;
    }

    /**
     * @return array<int,array<string,mixed>> heartbeat terakhir dari setiap perangkat yang dikenal
     */
    public function devices(): array
    {
        $out = [];
        foreach ((array) $this->cache()->get(self::DEVICES_KEY, []) as $id) {
            if (is_string($id) && ($hb = $this->lastHeartbeat($id)) !== null) {
                $out[] = $hb;
            }
        }

        return $out;
    }
}
