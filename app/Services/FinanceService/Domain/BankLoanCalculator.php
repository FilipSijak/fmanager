<?php

namespace App\Services\FinanceService\Domain;

use DomainException;

class BankLoanCalculator
{
    private const float ANNUAL_INTEREST_RATE = 0.05;

    public function calculate(int $amount, int $lengthYears): BankLoanTerms
    {
        if ($amount <= 0) {
            throw new DomainException('Loan amount must be greater than zero.');
        }

        if ($lengthYears <= 0) {
            throw new DomainException('Loan length must be at least one year.');
        }

        $installmentCount = $lengthYears * 12;
        $interestAmount = (int) round($amount * self::ANNUAL_INTEREST_RATE * $lengthYears);
        $totalAmount = $amount + $interestAmount;
        $baseInstallmentAmount = intdiv($totalAmount, $installmentCount);
        $installmentAmounts = [];

        for ($installmentNumber = 1; $installmentNumber <= $installmentCount; $installmentNumber++) {
            $installmentAmounts[] = $installmentNumber === $installmentCount
                ? $totalAmount - ($baseInstallmentAmount * ($installmentCount - 1))
                : $baseInstallmentAmount;
        }

        return new BankLoanTerms(
            principal: $amount,
            interestAmount: $interestAmount,
            totalAmount: $totalAmount,
            installmentCount: $installmentCount,
            installmentAmounts: $installmentAmounts,
        );
    }
}
