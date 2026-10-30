<?php

namespace PayHook\Laravel\Support;

use PayHook\Laravel\Exceptions\UniqueAmountUnavailable;

/**
 * Nominal unik: tambahkan kode acak kecil ke total tagihan agar setiap invoice
 * punya nominal berbeda dan bisa dicocokkan otomatis dengan pembayaran masuk.
 *
 * Contoh: base 100000 → 100347.
 */
final class UniqueAmount
{
    /**
     * @param callable(int):bool $isTaken mengembalikan true bila nominal sedang dipakai invoice aktif lain
     */
    public static function generate(int $base, callable $isTaken, int $min = 1, int $max = 999, int $attempts = 50): int
    {
        if ($base < 0 || $min < 0 || $max < $min) {
            throw new \InvalidArgumentException('Rentang nominal unik tidak valid');
        }
        $range = $max - $min + 1;
        $tries = min($attempts, $range);
        for ($i = 0; $i < $tries; $i++) {
            $candidate = $base + random_int($min, $max);
            if (! $isTaken($candidate)) {
                return $candidate;
            }
        }
        // Fallback: cari linear agar tetap menemukan slot kosong bila masih ada
        for ($code = $min; $code <= $max; $code++) {
            if (! $isTaken($base + $code)) {
                return $base + $code;
            }
        }

        throw new UniqueAmountUnavailable("Semua kode unik {$min}-{$max} untuk nominal {$base} sedang terpakai");
    }
}
