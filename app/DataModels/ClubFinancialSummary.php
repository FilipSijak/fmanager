<?php

namespace App\DataModels;

final readonly class ClubFinancialSummary
{
    public function __construct(
        public int $balance,
        public int $futureBalance,
        public int $allowedDebt,
        public int $transferBudget,
        public int $annualSalaryBudget,
        public int $annualPlayerWages,
        public int $remainingAnnualSalaryBudget,
    ) {}
}
