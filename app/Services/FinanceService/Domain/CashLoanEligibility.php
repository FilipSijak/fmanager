<?php

namespace App\Services\FinanceService\Domain;

use App\Models\Account;
use DomainException;

class CashLoanEligibility
{
    public function ensureEligible(Account $clubAccount, CashLoanTerms $terms, bool $disbursePrincipal = true): void
    {
        $projectedBalance = $clubAccount->future_balance + ($disbursePrincipal ? $terms->principal : 0) - $terms->totalAmount;

        if ($projectedBalance < -$clubAccount->allowed_debt) {
            throw new DomainException('The club cannot afford this loan.');
        }
    }
}
