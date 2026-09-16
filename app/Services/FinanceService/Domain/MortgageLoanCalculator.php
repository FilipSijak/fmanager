<?php

namespace App\Services\FinanceService\Domain;

use DomainException;

class MortgageLoanCalculator
{
    private const float ANNUAL_INTEREST_RATE = 0.05;

    public function calculate(int $amount, int $lengthYears): MortgageLoanTerms
    {
        if ($amount <= 0) {
            throw new DomainException('Mortgage amount must be greater than zero.');
        }

        if ($lengthYears < 2 || $lengthYears > 12) {
            throw new DomainException('Mortgage length must be between two and twelve years.');
        }

        $lengthMonths = $lengthYears * 12;
        $interestAmount = (int) round($amount * self::ANNUAL_INTEREST_RATE * $lengthYears);
        $totalAmount = $amount + $interestAmount;
        $baseInstallmentAmount = intdiv($totalAmount, $lengthMonths);
        $installmentAmounts = [];

        for ($installmentNumber = 1; $installmentNumber <= $lengthMonths; $installmentNumber++) {
            $installmentAmounts[] = $installmentNumber === $lengthMonths
                ? $totalAmount - ($baseInstallmentAmount * ($lengthMonths - 1))
                : $baseInstallmentAmount;
        }

        return new MortgageLoanTerms(
            principal: $amount,
            interestAmount: $interestAmount,
            totalAmount: $totalAmount,
            installmentCount: $lengthMonths,
            installmentAmounts: $installmentAmounts,
        );
    }
}
