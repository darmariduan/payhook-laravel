<?php

namespace PayHook\Laravel\Support;

/**
 * HMAC-SHA256 PayHook.
 *
 * Nilai yang ditandatangani: "{timestamp}.{rawBody}" dengan secret yang sama
 * seperti di aplikasi PayHook. Header: "X-Payhook-Signature: sha256=<hex>".
 */
final class Signature
{
    public static function compute(string $secret, string $timestamp, string $rawBody): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $rawBody, $secret);
    }

    /**
     * Ambil hex dari header "sha256=<hex>" (huruf kecil). Null bila format salah.
     */
    public static function parseHeader(?string $header): ?string
    {
        if ($header === null) {
            return null;
        }
        $header = trim($header);
        if (stripos($header, 'sha256=') === 0) {
            $header = substr($header, 7);
        }
        $header = strtolower($header);

        return preg_match('/^[0-9a-f]{64}$/', $header) === 1 ? $header : null;
    }

    public static function verify(string $secret, ?string $header, string $timestamp, string $rawBody): bool
    {
        $given = self::parseHeader($header);
        if ($given === null || $secret === '') {
            return false;
        }

        return hash_equals(self::compute($secret, $timestamp, $rawBody), $given);
    }
}
