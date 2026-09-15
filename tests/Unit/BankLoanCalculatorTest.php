<?php

namespace Tests\Unit;

use App\Services\FinanceService\Domain\BankLoanCalculator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BankLoanCalculatorTest extends TestCase
{
    #[Test]
    public function it_calculates_flat_annual_interest_and_monthly_installments(): void
    {
        $terms = (new BankLoanCalculator)->calculate(12000, 2);

        $this->assertSame(12000, $terms->principal);
        $this->assertSame(1200, $terms->interestAmount);
        $this->assertSame(13200, $terms->totalAmount);
        $this->assertSame(24, $terms->installmentCount);
        $this->assertSame(550, $terms->installmentAmounts[0]);
        $this->assertSame(550, $terms->installmentAmounts[23]);
        $this->assertSame(13200, array_sum($terms->installmentAmounts));
    }
}
