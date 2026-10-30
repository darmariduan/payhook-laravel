<?php

namespace PayHook\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use PayHook\Laravel\PayHookManager;

/**
 * @method static int uniqueAmount(int $base, callable $isTaken, int $min = 1, int $max = 999)
 * @method static array sign(string $rawBody, ?int $timestamp = null, ?string $secret = null)
 * @method static array|null lastHeartbeat(string $deviceId)
 * @method static bool isOnline(string $deviceId, ?int $offlineAfter = null)
 * @method static array devices()
 *
 * @see PayHookManager
 */
class PayHook extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return PayHookManager::class;
    }
}
