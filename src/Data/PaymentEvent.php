<?php

namespace PayHook\Laravel\Data;

use PayHook\Laravel\Exceptions\InvalidPayload;

/**
 * Payload transaksi dari PayHook (event_type payment.incoming / payment.outgoing).
 */
final class PaymentEvent
{
    public const INCOMING = 'payment.incoming';
    public const OUTGOING = 'payment.outgoing';

    private const MAX_AMOUNT = 1_000_000_000_000;

    public function __construct(
        public readonly string $eventId,
        public readonly string $eventType,
        public readonly int $amount,
        public readonly string $source,
        public readonly string $packageName,
        public readonly string $counterparty,
        public readonly string $reference,
        public readonly string $timestamp,
        public readonly ?int $timestampMs,
        public readonly string $notificationTitle,
        public readonly string $notificationText,
        public readonly bool $isTest,
        /** @var array<string,mixed> */
        public readonly array $raw,
        /** Kode ISO 4217 (PayHook v1.10+). Payload lama tanpa field currency dianggap "IDR". */
        public readonly string $currency = 'IDR',
        /**
         * Nominal persis sebagai string desimal, mis. "12.50" → "12.5" (PayHook v1.10+).
         * Payload lama: sama dengan amount. Pakai string agar tidak kehilangan presisi float.
         */
        public readonly string $amountDecimal = '',
    ) {
    }

    public function isIncoming(): bool
    {
        return $this->eventType === self::INCOMING;
    }

    public function isOutgoing(): bool
    {
        return $this->eventType === self::OUTGOING;
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $eventId = self::str($data, 'event_id', 128);
        if ($eventId === '' || preg_match('/^[A-Za-z0-9_.:-]{1,128}$/', $eventId) !== 1) {
            throw new InvalidPayload('event_id tidak valid');
        }

        $type = self::str($data, 'event_type', 40) ?: self::INCOMING;
        if (! in_array($type, [self::INCOMING, self::OUTGOING], true)) {
            throw new InvalidPayload('event_type tidak dikenal');
        }

        $amount = $data['amount'] ?? null;
        if (is_string($amount) && ctype_digit($amount)) {
            $amount = (int) $amount;
        }
        if (! is_int($amount) || $amount < 1 || $amount > self::MAX_AMOUNT) {
            throw new InvalidPayload('amount harus bilangan bulat positif');
        }

        $currency = $data['currency'] ?? null;
        if ($currency === null || $currency === '') {
            $currency = 'IDR';
        } elseif (! is_string($currency) || preg_match('/^[A-Za-z]{3}$/', $currency) !== 1) {
            throw new InvalidPayload('currency harus kode ISO 4217 tiga huruf');
        }
        $currency = strtoupper($currency);

        $amountDecimal = self::decimal($data['amount_decimal'] ?? null, $amount);

        $ms = $data['timestamp_ms'] ?? null;
        $ms = is_int($ms) && $ms > 0 ? $ms : null;

        $counterparty = $type === self::OUTGOING
            ? self::str($data, 'recipient', 100)
            : self::str($data, 'payer', 100);

        return new self(
            eventId: $eventId,
            eventType: $type,
            amount: $amount,
            source: self::str($data, 'source', 100),
            packageName: self::str($data, 'package_name', 255),
            counterparty: $counterparty,
            reference: self::str($data, 'reference', 128),
            timestamp: self::str($data, 'timestamp', 40),
            timestampMs: $ms,
            notificationTitle: self::str($data, 'notification_title', 500),
            notificationText: self::str($data, 'notification_text', 1000),
            isTest: ($data['is_test'] ?? false) === true,
            raw: $data,
            currency: $currency,
            amountDecimal: $amountDecimal,
        );
    }

    /**
     * amount_decimal harus angka desimal positif yang bagian bulatnya sama dengan amount;
     * selain itu (atau tidak ada) dipakai amount.
     */
    private static function decimal(mixed $v, int $amount): string
    {
        if (is_int($v)) {
            $v = (string) $v;
        } elseif (is_float($v) && is_finite($v)) {
            $v = rtrim(rtrim(number_format($v, 3, '.', ''), '0'), '.');
        }
        if (! is_string($v) || preg_match('/^(\d{1,13})(?:\.(\d{1,3}))?$/', $v, $m) !== 1) {
            return (string) $amount;
        }
        if ((int) $m[1] !== $amount) {
            return (string) $amount;
        }
        $frac = rtrim($m[2] ?? '', '0');

        return $frac === '' ? (string) $amount : $amount . '.' . $frac;
    }

    /**
     * Ambil string, buang karakter kontrol, dan batasi panjang.
     *
     * @param array<string,mixed> $data
     */
    private static function str(array $data, string $key, int $max): string
    {
        $v = $data[$key] ?? '';
        if (! is_string($v) && ! is_int($v)) {
            return '';
        }
        $v = preg_replace('/[\x00-\x1F\x7F]/u', ' ', (string) $v) ?? '';

        return mb_substr(trim($v), 0, $max);
    }
}
