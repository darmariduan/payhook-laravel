<?php

namespace PayHook\Laravel\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use PayHook\Laravel\PayHookServiceProvider;
use PayHook\Laravel\Support\Signature;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [PayHookServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('payhook.token', 'test-token');
        $app['config']->set('payhook.secret', 'test-secret');
        // Tanpa grup "api" agar tidak bergantung pada rate limiter aplikasi host
        $app['config']->set('payhook.routes.middleware', []);
    }

    /**
     * Kirim request persis seperti aplikasi PayHook (body mentah + header HMAC).
     */
    protected function payhookPost(string $uri, array $payload, array $overrideHeaders = [], ?int $ts = null, ?string $nonce = null)
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $ts = (string) ($ts ?? time());
        $headers = array_merge([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer test-token',
            'X-Payhook-Timestamp' => $ts,
            'X-Payhook-Nonce' => $nonce ?? bin2hex(random_bytes(16)),
            'X-Payhook-Signature' => 'sha256=' . Signature::compute('test-secret', $ts, $body),
        ], $overrideHeaders);

        return $this->call('POST', $uri, [], [], [], $this->transformHeadersToServerVars($headers), $body);
    }

    protected function samplePayment(array $over = []): array
    {
        return $over + [
            'event_id' => 'evt_' . bin2hex(random_bytes(6)),
            'event_type' => 'payment.incoming',
            'direction' => 'in',
            'amount' => 25000,
            'source' => 'BNI Merchant',
            'payer' => 'GOPAY',
            'reference' => 'PH-1',
            'timestamp' => '2026-10-30 18:24:00',
            'timestamp_ms' => 1793359440000,
            'package_name' => 'id.co.bni.merchant',
            'notification_title' => 'BNI Merchant',
            'notification_text' => 'Transaksi Sebesar Rp 25,000 dari GOPAY telah berhasil',
            'sent_by' => 'PayHook',
        ];
    }
}
