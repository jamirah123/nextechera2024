<?php

namespace Tests\Unit\Support\Finance;

use App\Support\Finance\PayrollPayeCalculator;
use PHPUnit\Framework\TestCase;

class PayrollPayeCalculatorTest extends TestCase
{
    public function test_tax_free_up_to_335000(): void
    {
        $this->assertSame(0.0, PayrollPayeCalculator::monthlyTax(335_000));
        $this->assertSame(0.0, PayrollPayeCalculator::monthlyTax(200_000));
    }

    public function test_twenty_percent_band(): void
    {
        $this->assertSame(13_000.0, PayrollPayeCalculator::monthlyTax(400_000));
    }

    public function test_twenty_five_percent_band(): void
    {
        $this->assertSame(25_000.0, PayrollPayeCalculator::monthlyTax(450_000));
    }

    public function test_thirty_percent_band(): void
    {
        $this->assertSame(188_250.0, PayrollPayeCalculator::monthlyTax(1_000_000));
    }

    public function test_high_earner_surtax_above_10_million(): void
    {
        $this->assertSame(2_988_250.0, PayrollPayeCalculator::monthlyTax(11_000_000));
    }
}
