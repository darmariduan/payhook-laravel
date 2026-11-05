<?php

namespace PayHook\Laravel\Tests\Unit;

use PayHook\Laravel\Data\Heartbeat;
use PayHook\Laravel\Data\PaymentEvent;
use PayHook\Laravel\Exceptions\InvalidPayload;
use PHPUnit\Framework\TestCase;

class PayloadTest extends TestCase
{
    private function sample(array $over = []): array
    {
        return $over + [
            'event_id' => 'evt_1793359440000_a1b2c3d4e5f6',
            'event_type' => 'payment.incoming',
            'direction' => 'in',
            'amount' => 25000,
            'source' => 'BNI Merchant',
            'payer' => 'GOPAY',
            'reference' => 'PH-1793359440000',
            'timestamp' => '2026-10-30 18:24:00',
            'timestamp_ms' => 1793359440000,
            'package_name' => 'id.co.bni.merchant',
            'notification_title' => 'BNI Merchant',
            'notification_text' => 'Transaksi Sebesar Rp 25,000 dari GOPAY telah berhasil',
            'sent_by' => 'PayHook',
        ];
    }

    public function test_parses_incoming(): void
    {
        $p = PaymentEvent::fromArray($this->sample());
        $this->assertTrue($p->isIncoming());
        $this->assertSame(25000, $p->amount);
        $this->assertSame('GOPAY', $p->counterparty);
        $this->assertFalse($p->isTest);
    }

    public function test_parses_outgoing_and_legacy(): void
    {
        $p = PaymentEvent::fromArray($this->sample(['event_type' => 'payment.outgoing', 'recipient' => 'PLN']));
        $this->assertTrue($p->isOutgoing());
        $this->assertSame('PLN', $p->counterparty);

        // Payload lama (v1.7) tanpa payer/timestamp_ms tetap diterima
        $legacy = $this->sample();
        unset($legacy['payer'], $legacy['timestamp_ms'], $legacy['direction']);
        $this->assertSame('', PaymentEvent::fromArray($legacy)->counterparty);
    }

    public function test_rejects_invalid(): void
    {
        foreach ([
            ['amount' => 0], ['amount' => -5], ['amount' => 1.5], ['amount' => '10a'], ['amount' => null],
            ['event_id' => ''], ['event_id' => '../../etc'], ['event_type' => 'refund'],
        ] as $bad) {
            try {
                PaymentEvent::fromArray($this->sample($bad));
                $this->fail('harus ditolak: ' . json_encode($bad));
            } catch (InvalidPayload) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_currency_and_amount_decimal(): void
    {
        // Payload lama (sebelum PayHook v1.10): Rupiah, desimal = amount
        $old = PaymentEvent::fromArray($this->sample());
        $this->assertSame('IDR', $old->currency);
        $this->assertSame('25000', $old->amountDecimal);

        $usd = PaymentEvent::fromArray($this->sample(['amount' => 12, 'currency' => 'usd', 'amount_decimal' => 12.5]));
        $this->assertSame('USD', $usd->currency);
        $this->assertSame('12.5', $usd->amountDecimal);

        $kwd = PaymentEvent::fromArray($this->sample(['amount' => 1, 'currency' => 'KWD', 'amount_decimal' => '1.500']));
        $this->assertSame('1.5', $kwd->amountDecimal);

        // Tidak konsisten dengan amount / bukan angka → pakai amount
        $this->assertSame('25000', PaymentEvent::fromArray($this->sample(['amount_decimal' => 99.5]))->amountDecimal);
        $this->assertSame('25000', PaymentEvent::fromArray($this->sample(['amount_decimal' => '1e5']))->amountDecimal);
        $this->assertSame('25000', PaymentEvent::fromArray($this->sample(['amount_decimal' => 25000]))->amountDecimal);

        foreach (['RUPIAH', 'U$', 12, ['IDR']] as $bad) {
            try {
                PaymentEvent::fromArray($this->sample(['currency' => $bad]));
                $this->fail('currency harus ditolak: ' . json_encode($bad));
            } catch (InvalidPayload) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_sanitizes_strings(): void
    {
        $p = PaymentEvent::fromArray($this->sample(['payer' => "A\x00B\nC" . str_repeat('x', 500), 'is_test' => 'yes']));
        $this->assertStringNotContainsString("\x00", $p->counterparty);
        $this->assertLessThanOrEqual(100, mb_strlen($p->counterparty));
        $this->assertFalse($p->isTest, 'is_test harus boolean true, bukan string');
    }

    public function test_heartbeat(): void
    {
        $hb = Heartbeat::fromArray([
            'type' => 'heartbeat', 'device_id' => 'ph-abc123', 'app_version' => '1.8.0',
            'listener_connected' => true, 'battery_optimized' => false, 'last_event_at' => 5, 'sent_at' => 6,
        ], 100);
        $this->assertSame('ph-abc123', $hb->deviceId);
        $this->assertTrue($hb->listenerConnected);
        $this->assertSame(100, $hb->toArray()['received_at']);

        $this->expectException(InvalidPayload::class);
        Heartbeat::fromArray(['type' => 'heartbeat', 'device_id' => "x\ny"], 1);
    }
}
