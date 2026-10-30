<?php

namespace PayHook\Laravel\Support;

/**
 * Verifikasi request PayHook tanpa ketergantungan framework (mudah diuji).
 *
 * Urutan cek: ukuran body → token (waktu-konstan) → HMAC + timestamp (bila secret diisi).
 * Pemeriksaan nonce (anti-replay) dilakukan oleh pemanggil menggunakan ReplayGuard
 * karena membutuhkan penyimpanan bersama.
 */
final class RequestVerifier
{
    /**
     * @param array{auth?:string,token?:?string,api_key_header?:string,secret?:?string,tolerance?:int,max_body_bytes?:int} $config
     */
    public function __construct(private readonly array $config)
    {
    }

    /**
     * @param callable(string):?string $header pengambil nilai header (case-insensitive)
     */
    public function verify(callable $header, string $rawBody, int $now): VerificationResult
    {
        $max = (int) ($this->config['max_body_bytes'] ?? 65536);
        if (strlen($rawBody) > $max) {
            return VerificationResult::fail(413, 'Payload too large');
        }

        $auth = strtolower((string) ($this->config['auth'] ?? 'bearer'));
        $token = (string) ($this->config['token'] ?? '');
        $secret = (string) ($this->config['secret'] ?? '');

        if ($auth !== 'none' && $token === '') {
            // Gagal tertutup: jangan pernah menerima request bila token belum dikonfigurasi
            return VerificationResult::fail(500, 'PayHook token is not configured');
        }
        if ($auth === 'none' && $secret === '') {
            return VerificationResult::fail(500, 'PayHook auth "none" requires a secret (HMAC)');
        }

        if ($auth === 'bearer') {
            $given = (string) ($header('Authorization') ?? '');
            if (stripos($given, 'Bearer ') !== 0 || ! hash_equals($token, trim(substr($given, 7)))) {
                return VerificationResult::fail(401, 'Invalid token');
            }
        } elseif ($auth === 'api_key') {
            $name = (string) ($this->config['api_key_header'] ?? 'X-API-Key');
            $given = (string) ($header($name) ?? '');
            if (! hash_equals($token, $given)) {
                return VerificationResult::fail(401, 'Invalid token');
            }
        } elseif ($auth === 'basic') {
            // PayHook "Basic Auth": token berisi "username:password"
            $given = (string) ($header('Authorization') ?? '');
            $expected = 'Basic ' . base64_encode($token);
            if (! hash_equals($expected, trim($given))) {
                return VerificationResult::fail(401, 'Invalid token');
            }
        } elseif ($auth !== 'none') {
            return VerificationResult::fail(500, 'Unsupported PayHook auth mode');
        }

        if ($secret === '') {
            return VerificationResult::pass();
        }

        $ts = trim((string) ($header('X-Payhook-Timestamp') ?? ''));
        if ($ts === '' || preg_match('/^\d{9,11}$/', $ts) !== 1) {
            return VerificationResult::fail(401, 'Missing or invalid timestamp');
        }
        $tolerance = max(30, (int) ($this->config['tolerance'] ?? 300));
        if (abs($now - (int) $ts) > $tolerance) {
            return VerificationResult::fail(401, 'Timestamp outside tolerance');
        }
        if (! Signature::verify($secret, $header('X-Payhook-Signature'), $ts, $rawBody)) {
            return VerificationResult::fail(401, 'Invalid signature');
        }

        // Kunci anti-replay selalu diturunkan dari timestamp + signature: request yang
        // diputar ulang pasti membawa pasangan yang sama, walau header nonce dihapus/diubah
        // (nonce tidak ikut ditandatangani). Nonce valid hanya menjadi tambahan.
        $nonce = $header('X-Payhook-Nonce');
        $nonce = $nonce !== null && preg_match('/^[A-Za-z0-9_-]{8,128}$/', $nonce) === 1 ? $nonce : '';
        $signature = (string) Signature::parseHeader($header('X-Payhook-Signature'));

        return VerificationResult::pass(hash('sha256', $ts . '.' . $signature), (int) $ts);
    }
}
