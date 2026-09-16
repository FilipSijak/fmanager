<?php

namespace App\Services\FinanceService\Domain;

abstract readonly class LoanTerms
{
    /**
     * @param  list<int>  $installmentAmounts
     */
    public function __construct(
        public int $principal,
        public int $interestAmount,
        public int $totalAmount,
        public int $installmentCount,
        public array $installmentAmounts,
    ) {}
}
