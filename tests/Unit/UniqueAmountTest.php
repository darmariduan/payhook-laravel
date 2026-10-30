<?php

namespace PayHook\Laravel\Tests\Unit;

use PayHook\Laravel\Exceptions\UniqueAmountUnavailable;
use PayHook\Laravel\Support\UniqueAmount;
use PHPUnit\Framework\TestCase;

class UniqueAmountTest extends TestCase
{
    public function test_generates_within_range_and_avoids_taken(): void
    {
        $taken = [];
        for ($i = 0; $i < 200; $i++) {
            $amount = UniqueAmount::generate(100000, fn (int $a) => isset($taken[$a]));
            $this->assertGreaterThanOrEqual(100001, $amount);
            $this->assertLessThanOrEqual(100999, $amount);
            $this->assertArrayNotHasKey($amount, $taken);
            $taken[$amount] = true;
        }
    }

    public function test_finds_last_free_slot(): void
    {
        $amount = UniqueAmount::generate(5000, fn (int $a) => $a !== 5007, 1, 10);
        $this->assertSame(5007, $amount);
    }

    public function test_throws_when_full(): void
    {
        $this->expectException(UniqueAmountUnavailable::class);
        UniqueAmount::generate(5000, fn () => true, 1, 5);
    }
}
