<?php

namespace PayHook\Laravel\Http\Controllers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PayHook\Laravel\Data\PaymentEvent;
use PayHook\Laravel\Events\PaymentReceived;
use PayHook\Laravel\Events\PaymentSent;
use PayHook\Laravel\Exceptions\InvalidPayload;
use PayHook\Laravel\PayHookManager;
use Throwable;

/**
 * Menerima webhook transaksi PayHook, lalu memicu event PaymentReceived / PaymentSent
 * tepat satu kali per event_id.
 */
class WebhookController
{
    public function __construct(
        private readonly Dispatcher $events,
        private readonly PayHookManager $payhook,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 16);
        if (! is_array($data) || array_is_list($data)) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid JSON'], 422);
        }

        try {
            $payment = PaymentEvent::fromArray($data);
        } catch (InvalidPayload $e) {
            return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        if ($payment->isTest && ! config('payhook.dispatch_test_events', false)) {
            return new JsonResponse(['status' => 'ok', 'test' => true]);
        }
        if ($payment->isOutgoing() && ! config('payhook.dispatch_outgoing', true)) {
            return new JsonResponse(['status' => 'ignored']);
        }

        $cache = $this->payhook->cache();
        $key = 'payhook:event:' . hash('sha256', $payment->eventId);
        $ttl = max(60, (int) config('payhook.idempotency_ttl', 604800));

        // Klaim event secara atomik. "processing" berumur pendek agar event yang gagal
        // diproses (exception) dapat dicoba ulang oleh PayHook.
        $processingTtl = max(30, (int) config('payhook.processing_ttl', 300));
        if (! $cache->add($key, 'processing', $processingTtl)) {
            $state = $cache->get($key);
            if ($state === 'done') {
                return new JsonResponse(['status' => 'duplicate', 'event_id' => $payment->eventId]);
            }

            // Sedang diproses oleh request lain → minta PayHook mencoba lagi nanti
            return new JsonResponse(['status' => 'processing'], 429, ['Retry-After' => '60']);
        }

        try {
            $this->events->dispatch(
                $payment->isOutgoing() ? new PaymentSent($payment) : new PaymentReceived($payment)
            );
        } catch (Throwable $e) {
            $cache->forget($key);
            report($e);

            // 5xx → PayHook memasukkan ke antrean retry
            return new JsonResponse(['status' => 'error', 'message' => 'Processing failed'], 500);
        }

        $cache->put($key, 'done', $ttl);

        return new JsonResponse(['status' => 'ok', 'event_id' => $payment->eventId]);
    }
}
