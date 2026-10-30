<?php

namespace PayHook\Laravel\Support;

interface ReplayGuard
{
    /**
     * Tandai nonce sebagai terpakai. Mengembalikan false bila nonce sudah pernah dipakai.
     */
    public function claim(string $nonce, int $ttlSeconds): bool;
}
