<?php

namespace Tests\Unit;

use App\Support\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_round_half_up_handles_exact_boundary_values_without_floats(): void
    {
        $this->assertSame('1.00', Money::roundHalfUp('1.004'));
        $this->assertSame('1.01', Money::roundHalfUp('1.005'));
        $this->assertSame('2.00', Money::roundHalfUp('1.995'));
        $this->assertSame('-1.01', Money::roundHalfUp('-1.005'));
    }

    public function test_money_format_hides_insignificant_fractional_zeroes(): void
    {
        $this->assertSame('1,250', Money::format('1250.00'));
        $this->assertSame('1,250.5', Money::format('1250.50'));
        $this->assertSame('1,250.01', Money::format('1250.005'));
    }

    public function test_rounding_rejects_non_decimal_input(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::roundHalfUp('1e3');
    }
}
