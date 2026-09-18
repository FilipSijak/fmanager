<?php

namespace App\Services\FinanceService\Domain;

use DomainException;

class CashLoanCalculator
{
    private const float CASH_LOAN_ANNUAL_INTEREST_RATE = 0.08;

    public function calculate(
        int $amount,
        int $lengthMonths,
        float $annualInterestRate = self::CASH_LOAN_ANNUAL_INTEREST_RATE,
    ): CashLoanTerms {
        if ($amount <= 0) {
            throw new DomainException('Loan amount must be greater than zero.');
        }

        if ($lengthMonths <= 0) {
            throw new DomainException('Loan length must be at least one month.');
        }

        if ($annualInterestRate < 0) {
            throw new DomainException('Loan interest rate cannot be negative.');
        }

        $installmentCount = $lengthMonths;
        $interestAmount = (int) round($amount * $annualInterestRate * ($lengthMonths / 12));
        $totalAmount = $amount + $interestAmount;
        $baseInstallmentAmount = intdiv($totalAmount, $installmentCount);
        $installmentAmounts = [];

        for ($installmentNumber = 1; $installmentNumber <= $installmentCount; $installmentNumber++) {
            $installmentAmounts[] = $installmentNumber === $installmentCount
                ? $totalAmount - ($baseInstallmentAmount * ($installmentCount - 1))
                : $baseInstallmentAmount;
        }

        return new CashLoanTerms(
            principal: $amount,
            interestAmount: $interestAmount,
            totalAmount: $totalAmount,
            installmentCount: $installmentCount,
            installmentAmounts: $installmentAmounts,
        );
    }
}
