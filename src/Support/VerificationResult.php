<?php

namespace PayHook\Laravel\Support;

final class VerificationResult
{
    private function __construct(
        public readonly bool $ok,
        public readonly int $status,
        public readonly string $reason,
        public readonly ?string $replayKey = null,
        public readonly ?int $timestamp = null,
    ) {
    }

    /**
     * @param string|null $replayKey kunci unik request bertanda tangan (dipakai anti-replay)
     */
    public static function pass(?string $replayKey = null, ?int $timestamp = null): self
    {
        return new self(true, 200, '', $replayKey, $timestamp);
    }

    public static function fail(int $status, string $reason): self
    {
        return new self(false, $status, $reason);
    }
}
