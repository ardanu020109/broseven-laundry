<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_multiply_is_exact_and_rounds_half_up(): void
    {
        $this->assertSame('20000.00', Money::multiply('8000.00', '2.50'));
        $this->assertSame('1980.00', Money::multiply('6000.00', '0.33'));
        $this->assertSame('2566.66', Money::multiply('7777.77', '0.33'));
        $this->assertSame('5.03', Money::multiply('10.05', '0.50')); // 5.025 dibulatkan ke atas
    }

    public function test_decimal_normalizes_scale(): void
    {
        $this->assertSame('2.50', Money::decimal(2.5));
        $this->assertSame('4.00', Money::decimal('4'));
    }

    public function test_is_whole(): void
    {
        $this->assertTrue(Money::isWhole(3));
        $this->assertTrue(Money::isWhole('3.00'));
        $this->assertFalse(Money::isWhole('2.50'));
        $this->assertFalse(Money::isWhole(0.5));
    }
}