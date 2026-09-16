<?php

namespace Tests\Unit;

use App\Services\FinanceService\Domain\MortgageLoanCalculator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MortgageLoanCalculatorTest extends TestCase
{
    #[Test]
    public function it_calculates_five_percent_annual_interest_over_yearly_terms(): void
    {
        $terms = (new MortgageLoanCalculator)->calculate(12000, 5);

        $this->assertSame(12000, $terms->principal);
        $this->assertSame(3000, $terms->interestAmount);
        $this->assertSame(15000, $terms->totalAmount);
        $this->assertSame(60, $terms->installmentCount);
        $this->assertSame(250, $terms->installmentAmounts[0]);
        $this->assertSame(15000, array_sum($terms->installmentAmounts));
    }
}
