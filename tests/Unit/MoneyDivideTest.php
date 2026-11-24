<?php

namespace Tests\Unit;

use App\Support\Money;
use PHPUnit\Framework\TestCase;

class MoneyDivideTest extends TestCase
{
    public function test_divide_rounds_half_up(): void
    {
        $this->assertSame('37333.33', Money::divide('112000.00', '3'));
        $this->assertSame('30666.67', Money::divide('92000.00', '3'));
        $this->assertSame('0.13', Money::divide('1', '8'));
        $this->assertSame('16000.00', Money::divide('32000.00', '2'));
    }

    public function test_divide_supports_other_scales(): void
    {
        $this->assertSame('65.2', Money::divide('6000000.0000', '92000.00', 1));
        $this->assertSame('34.8', Money::divide('3200000.0000', '92000.00', 1));
        $this->assertSame('1', Money::divide('2', '3', 0));
    }

    public function test_divide_by_zero_returns_zero(): void
    {
        $this->assertSame('0.00', Money::divide('10', '0'));
        $this->assertSame('0.0', Money::divide('10', '0.00', 1));
    }
}