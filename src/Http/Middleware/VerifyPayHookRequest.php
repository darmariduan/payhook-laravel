<?php

namespace PayHook\Laravel\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use PayHook\Laravel\Support\ReplayGuard;
use PayHook\Laravel\Support\RequestVerifier;

/**
 * Verifikasi token, HMAC, timestamp, dan nonce (anti-replay) untuk request PayHook.
 * Alias middleware: "payhook.verify".
 */
class VerifyPayHookRequest
{
    public function __construct(private readonly ReplayGuard $replayGuard)
    {
    }

    /**
     * @param string|null $profile "heartbeat" untuk endpoint heartbeat (selalu Bearer dari aplikasi)
     */
    public function handle(Request $request, Closure $next, ?string $profile = null)
    {
        $config = (array) config('payhook', []);
        if ($profile === 'heartbeat') {
            $hb = (array) ($config['heartbeat'] ?? []);
            $config['auth'] = 'bearer';
            $config['token'] = ($hb['token'] ?? null) ?: ($config['token'] ?? null);
            $config['secret'] = ($hb['secret'] ?? null) ?: ($config['secret'] ?? null);
        }
        $verifier = new RequestVerifier($config);
        $result = $verifier->verify(
            fn (string $name) => $request->headers->get($name),
            $request->getContent(),
            Carbon::now()->getTimestamp(),
        );

        if (! $result->ok) {
            if ($result->status >= 500) {
                Log::error('PayHook: ' . $result->reason);
            }

            // Pesan generik ke klien; detail hanya untuk salah konfigurasi server
            return new JsonResponse(
                ['status' => 'error', 'message' => $result->status === 413 ? 'Payload too large' : 'Unauthorized'],
                $result->status >= 500 ? 500 : $result->status,
            );
        }

        if ($result->replayKey !== null) {
            $ttl = 2 * max(30, (int) ($config['tolerance'] ?? 300));
            if (! $this->replayGuard->claim($result->replayKey, $ttl)) {
                return new JsonResponse(['status' => 'error', 'message' => 'Replay detected'], 409);
            }
        }

        return $next($request);
    }
}
