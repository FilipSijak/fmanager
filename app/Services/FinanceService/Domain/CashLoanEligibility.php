<?php

namespace App\Services\FinanceService\Domain;

use App\Models\Account;
use DomainException;

class CashLoanEligibility
{
    public function ensureEligible(Account $clubAccount, CashLoanTerms $terms): void
    {
        $projectedBalance = $clubAccount->future_balance + $terms->principal - $terms->totalAmount;

        if ($projectedBalance < -$clubAccount->allowed_debt) {
            throw new DomainException('The club cannot afford this loan.');
        }
    }
}
