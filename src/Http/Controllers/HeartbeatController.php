<?php

namespace PayHook\Laravel\Http\Controllers;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use PayHook\Laravel\Data\Heartbeat;
use PayHook\Laravel\Events\HeartbeatReceived;
use PayHook\Laravel\Exceptions\InvalidPayload;
use PayHook\Laravel\PayHookManager;

class HeartbeatController
{
    public function __construct(
        private readonly Dispatcher $events,
        private readonly PayHookManager $payhook,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true, 8);
        if (! is_array($data)) {
            return new JsonResponse(['status' => 'error', 'message' => 'Invalid JSON'], 422);
        }

        try {
            $heartbeat = Heartbeat::fromArray($data, Carbon::now()->getTimestamp());
        } catch (InvalidPayload $e) {
            return new JsonResponse(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        $this->payhook->recordHeartbeat($heartbeat);
        $this->events->dispatch(new HeartbeatReceived($heartbeat));

        return new JsonResponse(['status' => 'ok']);
    }
}
