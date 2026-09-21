<?php

namespace App\Services\FinanceService\Domain;

use App\Models\Account;
use App\Models\AccountsDebtLinesEntity;
use DomainException;

class CashLoanEligibility
{
    public function ensureEligible(Account $clubAccount, LoanTerms $terms): void
    {
        $outstandingDebt = (int) AccountsDebtLinesEntity::query()
            ->where('club_account_id', $clubAccount->id)
            ->whereNull('paid_at')
            ->sum('amount');

        if ($outstandingDebt + $terms->totalAmount > $clubAccount->allowed_debt) {
            throw new DomainException('The club cannot afford this loan.');
        }
    }
}
