<?php

namespace PayHook\Laravel\Tests\Unit;

use PayHook\Laravel\Support\Signature;
use PHPUnit\Framework\TestCase;

class SignatureTest extends TestCase
{
    public function test_compute_matches_payhook_android_format(): void
    {
        // Nilai referensi: HMAC-SHA256("rahasia", "1700000000.{\"a\":1}")
        $expected = hash_hmac('sha256', '1700000000.{"a":1}', 'rahasia');
        $this->assertSame($expected, Signature::compute('rahasia', '1700000000', '{"a":1}'));
    }

    public function test_verify_accepts_prefixed_and_uppercase(): void
    {
        $sig = Signature::compute('s3cret', '1700000000', 'body');
        $this->assertTrue(Signature::verify('s3cret', 'sha256=' . $sig, '1700000000', 'body'));
        $this->assertTrue(Signature::verify('s3cret', 'SHA256=' . strtoupper($sig), '1700000000', 'body'));
        $this->assertTrue(Signature::verify('s3cret', $sig, '1700000000', 'body'));
    }

    public function test_verify_rejects_tampering(): void
    {
        $sig = Signature::compute('s3cret', '1700000000', 'body');
        $this->assertFalse(Signature::verify('s3cret', 'sha256=' . $sig, '1700000001', 'body'));
        $this->assertFalse(Signature::verify('s3cret', 'sha256=' . $sig, '1700000000', 'body2'));
        $this->assertFalse(Signature::verify('other', 'sha256=' . $sig, '1700000000', 'body'));
        $this->assertFalse(Signature::verify('', 'sha256=' . $sig, '1700000000', 'body'));
        $this->assertFalse(Signature::verify('s3cret', null, '1700000000', 'body'));
        $this->assertFalse(Signature::verify('s3cret', 'sha256=zz', '1700000000', 'body'));
    }
}
