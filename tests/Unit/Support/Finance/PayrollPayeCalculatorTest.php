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
        // 20% × (400,000 − 335,000) = 13,000
        $this->assertSame(13_000.0, PayrollPayeCalculator::monthlyTax(400_000));
    }

    public function test_twenty_five_percent_band(): void
    {
        // 15,000 + 25% × (450,000 − 410,000) = 25,000
        $this->assertSame(25_000.0, PayrollPayeCalculator::monthlyTax(450_000));
    }

    public function test_thirty_percent_band(): void
    {
        // 33,750 + 30% × (1,000,000 − 485,000) = 188,250
        $this->assertSame(188_250.0, PayrollPayeCalculator::monthlyTax(1_000_000));
    }

    public function test_nine_hundred_thousand_matches_ura_formula(): void
    {
        // 33,750 + 30% × (900,000 − 485,000) = 158,250
        $this->assertSame(158_250.0, PayrollPayeCalculator::monthlyTax(900_000));
    }

    public function test_high_earner_surtax_above_10_million(): void
    {
        // 33,750 + 30% × (11,000,000 − 485,000) + 10% × (11,000,000 − 10,000,000) = 3,288,250
        $this->assertSame(3_288_250.0, PayrollPayeCalculator::monthlyTax(11_000_000));
    }

    public function test_official_schedule_has_five_ura_rows(): void
    {
        $this->assertCount(5, PayrollPayeCalculator::officialSchedule());
    }
}
