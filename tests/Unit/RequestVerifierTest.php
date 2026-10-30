<?php

namespace PayHook\Laravel\Tests\Unit;

use PayHook\Laravel\Support\RequestVerifier;
use PayHook\Laravel\Support\Signature;
use PHPUnit\Framework\TestCase;

class RequestVerifierTest extends TestCase
{
    private const NOW = 1_800_000_000;

    private function headers(array $h): callable
    {
        $lower = array_change_key_case($h, CASE_LOWER);

        return fn (string $name) => $lower[strtolower($name)] ?? null;
    }

    private function signed(string $body, string $secret, int $ts, array $extra = []): callable
    {
        return $this->headers($extra + [
            'Authorization' => 'Bearer tok',
            'X-Payhook-Timestamp' => (string) $ts,
            'X-Payhook-Nonce' => 'abcdef0123456789',
            'X-Payhook-Signature' => 'sha256=' . Signature::compute($secret, (string) $ts, $body),
        ]);
    }

    public function test_bearer_only(): void
    {
        $v = new RequestVerifier(['auth' => 'bearer', 'token' => 'tok']);
        $this->assertTrue($v->verify($this->headers(['Authorization' => 'Bearer tok']), '{}', self::NOW)->ok);
        $this->assertSame(401, $v->verify($this->headers(['Authorization' => 'Bearer bad']), '{}', self::NOW)->status);
        $this->assertSame(401, $v->verify($this->headers([]), '{}', self::NOW)->status);
        $this->assertSame(401, $v->verify($this->headers(['Authorization' => 'tok']), '{}', self::NOW)->status);
    }

    public function test_fails_closed_without_token(): void
    {
        $v = new RequestVerifier(['auth' => 'bearer', 'token' => '']);
        $r = $v->verify($this->headers(['Authorization' => 'Bearer ']), '{}', self::NOW);
        $this->assertFalse($r->ok);
        $this->assertSame(500, $r->status);

        $none = new RequestVerifier(['auth' => 'none', 'secret' => '']);
        $this->assertSame(500, $none->verify($this->headers([]), '{}', self::NOW)->status);
    }

    public function test_api_key_and_basic(): void
    {
        $api = new RequestVerifier(['auth' => 'api_key', 'token' => 'k1', 'api_key_header' => 'X-Key']);
        $this->assertTrue($api->verify($this->headers(['X-Key' => 'k1']), '{}', self::NOW)->ok);
        $this->assertFalse($api->verify($this->headers(['X-API-Key' => 'k1']), '{}', self::NOW)->ok);

        $basic = new RequestVerifier(['auth' => 'basic', 'token' => 'user:pass']);
        $this->assertTrue($basic->verify($this->headers(['Authorization' => 'Basic ' . base64_encode('user:pass')]), '{}', self::NOW)->ok);
        $this->assertFalse($basic->verify($this->headers(['Authorization' => 'Basic ' . base64_encode('user:x')]), '{}', self::NOW)->ok);
    }

    public function test_hmac_required_when_secret_set(): void
    {
        $v = new RequestVerifier(['auth' => 'bearer', 'token' => 'tok', 'secret' => 'sec', 'tolerance' => 300]);
        $body = '{"event_id":"evt_1"}';

        $ok = $v->verify($this->signed($body, 'sec', self::NOW), $body, self::NOW);
        $this->assertTrue($ok->ok);
        $this->assertSame(64, strlen((string) $ok->replayKey));

        // Tanpa header signature
        $this->assertSame(401, $v->verify($this->headers(['Authorization' => 'Bearer tok']), $body, self::NOW)->status);
        // Secret salah
        $this->assertFalse($v->verify($this->signed($body, 'wrong', self::NOW), $body, self::NOW)->ok);
        // Body diubah
        $this->assertFalse($v->verify($this->signed($body, 'sec', self::NOW), $body . ' ', self::NOW)->ok);
        // Timestamp kedaluwarsa / dari masa depan
        $this->assertFalse($v->verify($this->signed($body, 'sec', self::NOW - 301), $body, self::NOW)->ok);
        $this->assertFalse($v->verify($this->signed($body, 'sec', self::NOW + 301), $body, self::NOW)->ok);
        // Timestamp non-numerik
        $bad = $this->headers(['Authorization' => 'Bearer tok', 'X-Payhook-Timestamp' => '1e9',
            'X-Payhook-Signature' => 'sha256=' . Signature::compute('sec', '1e9', $body)]);
        $this->assertFalse($v->verify($bad, $body, self::NOW)->ok);
    }

    public function test_replay_key_does_not_depend_on_unsigned_nonce(): void
    {
        $v = new RequestVerifier(['auth' => 'bearer', 'token' => 'tok', 'secret' => 'sec']);
        $body = '{}';
        $a = $v->verify($this->signed($body, 'sec', self::NOW), $body, self::NOW);
        // Penyerang mengganti / menghapus nonce pada request yang sama → kunci replay tetap sama
        $b = $v->verify($this->signed($body, 'sec', self::NOW, ['X-Payhook-Nonce' => "other-nonce-123"]), $body, self::NOW);
        $c = $v->verify($this->signed($body, 'sec', self::NOW, ['X-Payhook-Nonce' => "bad nonce\n"]), $body, self::NOW);
        $this->assertTrue($a->ok && $b->ok && $c->ok);
        $this->assertSame($a->replayKey, $b->replayKey);
        $this->assertSame($a->replayKey, $c->replayKey);
        // Request berbeda → kunci berbeda
        $d = $v->verify($this->signed('{"x":1}', 'sec', self::NOW), '{"x":1}', self::NOW);
        $this->assertTrue($d->replayKey !== $a->replayKey);
    }

    public function test_no_replay_key_without_secret(): void
    {
        $v = new RequestVerifier(['auth' => 'bearer', 'token' => 'tok']);
        $this->assertNull($v->verify($this->headers(['Authorization' => 'Bearer tok']), '{}', self::NOW)->replayKey);
    }

    public function test_body_limit(): void
    {
        $v = new RequestVerifier(['auth' => 'bearer', 'token' => 'tok', 'max_body_bytes' => 10]);
        $this->assertSame(413, $v->verify($this->headers(['Authorization' => 'Bearer tok']), str_repeat('a', 11), self::NOW)->status);
    }
}
