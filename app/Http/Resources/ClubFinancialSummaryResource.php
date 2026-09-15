<?php

namespace App\Http\Resources;

use App\DataModels\ClubFinancialSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ClubFinancialSummary */
class ClubFinancialSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'balance' => $this->balance,
            'future_balance' => $this->futureBalance,
            'allowed_debt' => $this->allowedDebt,
            'transfer_budget' => $this->transferBudget,
            'annual_salary_budget' => $this->annualSalaryBudget,
            'annual_player_wages' => $this->annualPlayerWages,
            'remaining_annual_salary_budget' => $this->remainingAnnualSalaryBudget,
        ];
    }
}
