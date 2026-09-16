<?php

namespace Tests\Unit;

use App\Services\FinanceService\Domain\CashLoanCalculator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CashLoanCalculatorTest extends TestCase
{
    #[Test]
    public function it_calculates_cash_loan_interest_and_monthly_installments(): void
    {
        $terms = (new CashLoanCalculator)->calculate(12000, 24);

        $this->assertSame(12000, $terms->principal);
        $this->assertSame(1920, $terms->interestAmount);
        $this->assertSame(13920, $terms->totalAmount);
        $this->assertSame(24, $terms->installmentCount);
        $this->assertSame(580, $terms->installmentAmounts[0]);
        $this->assertSame(580, $terms->installmentAmounts[23]);
        $this->assertSame(13920, array_sum($terms->installmentAmounts));
    }
}
