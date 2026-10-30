<?php

namespace PayHook\Laravel\Tests\Feature;

use Illuminate\Support\Facades\Event;
use PayHook\Laravel\Events\PaymentReceived;
use PayHook\Laravel\Events\PaymentSent;
use PayHook\Laravel\Tests\TestCase;

class WebhookTest extends TestCase
{
    public function test_valid_webhook_dispatches_event_once(): void
    {
        Event::fake([PaymentReceived::class]);
        $payload = $this->samplePayment(['event_id' => 'evt_dup_1']);

        $this->payhookPost('/payhook/webhook', $payload, [], time() - 1)->assertOk()->assertJson(['status' => 'ok']);
        // Retry dari PayHook (event_id sama, ditandatangani ulang dengan timestamp baru) → tidak memicu event lagi
        $this->payhookPost('/payhook/webhook', $payload, [], time())->assertOk()->assertJson(['status' => 'duplicate']);

        Event::assertDispatchedTimes(PaymentReceived::class, 1);
        Event::assertDispatched(PaymentReceived::class, fn ($e) => $e->payment->amount === 25000
            && $e->payment->counterparty === 'GOPAY');
    }

    public function test_outgoing_dispatches_payment_sent(): void
    {
        Event::fake([PaymentReceived::class, PaymentSent::class]);
        $this->payhookPost('/payhook/webhook', $this->samplePayment([
            'event_type' => 'payment.outgoing', 'recipient' => 'PLN',
        ]))->assertOk();
        Event::assertDispatched(PaymentSent::class);
        Event::assertNotDispatched(PaymentReceived::class);
    }

    public function test_rejects_wrong_token(): void
    {
        Event::fake([PaymentReceived::class]);
        $this->payhookPost('/payhook/webhook', $this->samplePayment(), ['Authorization' => 'Bearer salah'])
            ->assertStatus(401);
        Event::assertNotDispatched(PaymentReceived::class);
    }

    public function test_rejects_bad_signature_and_stale_timestamp(): void
    {
        Event::fake([PaymentReceived::class]);
        $this->payhookPost('/payhook/webhook', $this->samplePayment(), ['X-Payhook-Signature' => 'sha256=' . str_repeat('0', 64)])
            ->assertStatus(401);
        $this->payhookPost('/payhook/webhook', $this->samplePayment(), [], time() - 3600)->assertStatus(401);
        Event::assertNotDispatched(PaymentReceived::class);
    }

    public function test_rejects_replayed_request_even_with_changed_nonce(): void
    {
        Event::fake([PaymentReceived::class]);
        $payload = $this->samplePayment();
        $ts = time();
        $this->payhookPost('/payhook/webhook', $payload, [], $ts, 'nonce-replay-0001')->assertOk();
        // Request identik diputar ulang (nonce sama, diganti, atau dihapus) → ditolak
        $this->payhookPost('/payhook/webhook', $payload, [], $ts, 'nonce-replay-0001')->assertStatus(409);
        $this->payhookPost('/payhook/webhook', $payload, [], $ts, 'nonce-replay-9999')->assertStatus(409);
        $this->payhookPost('/payhook/webhook', $payload, ['X-Payhook-Nonce' => ''], $ts)->assertStatus(409);
        Event::assertDispatchedTimes(PaymentReceived::class, 1);
    }

    public function test_invalid_payload_is_422(): void
    {
        $this->payhookPost('/payhook/webhook', $this->samplePayment(['amount' => -1]))->assertStatus(422);
        $this->payhookPost('/payhook/webhook', ['x' => 1])->assertStatus(422);
    }

    public function test_failed_listener_allows_retry(): void
    {
        $calls = 0;
        Event::listen(PaymentReceived::class, function () use (&$calls) {
            $calls++;
            if ($calls === 1) {
                throw new \RuntimeException('DB sibuk');
            }
        });
        $payload = $this->samplePayment(['event_id' => 'evt_retry_1']);

        $this->payhookPost('/payhook/webhook', $payload, [], time() - 1)->assertStatus(500);
        // Retry PayHook selalu ditandatangani ulang dengan timestamp baru
        $this->payhookPost('/payhook/webhook', $payload, [], time())->assertOk()->assertJson(['status' => 'ok']);
        $this->assertSame(2, $calls);
    }

    public function test_fails_closed_without_configured_token(): void
    {
        config(['payhook.token' => null]);
        $this->payhookPost('/payhook/webhook', $this->samplePayment())->assertStatus(500);
    }

    public function test_test_events_are_ignored_by_default(): void
    {
        Event::fake([PaymentReceived::class]);
        $this->payhookPost('/payhook/webhook', $this->samplePayment(['is_test' => true]))->assertOk()->assertJson(['test' => true]);
        Event::assertNotDispatched(PaymentReceived::class);
    }

    public function test_test_events_can_be_enabled(): void
    {
        Event::fake([PaymentReceived::class]);
        config(['payhook.dispatch_test_events' => true]);
        $this->payhookPost('/payhook/webhook', $this->samplePayment(['is_test' => true]))->assertOk();
        Event::assertDispatched(PaymentReceived::class, fn ($e) => $e->payment->isTest === true);
    }
}
