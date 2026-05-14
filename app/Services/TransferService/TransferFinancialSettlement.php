<?php

namespace App\Services\TransferService;

use AllowDynamicProperties;
use App\Helpers\DisplayHelpers;
use App\Models\Account;
use App\Models\AccountsDebtLines;
use App\Models\Club;
use App\Models\Transfer;
use App\Models\TransferFinancialDetails;
use Carbon\Carbon;
use App\Services\FinanceService\FinanceService;

class TransferFinancialSettlement
{
    private FinanceService $financeService;

    public function __construct(
        FinanceService $financeService,
    )
    {
        $this->financeService = $financeService;
    }

    public function transferMoneyBetweenClubs(
        Transfer $transfer,
    )
    {
        $transferFinancialDetails = TransferFinancialDetails::where('transfer_id', $transfer->id)->first();
        $targetClubAccount = Account::where('club_id', $transfer->target_club_id)->firstOrFail();
        $sourceClubAccount = Account::where('club_id', $transfer->source_club_id)->firstOrFail();

        if ($transferFinancialDetails->installments > 0) {
            $monthlyPayment = $transferFinancialDetails->amount / $transferFinancialDetails->installments;
            $monthlyPayment = DisplayHelpers::roundAmounts($monthlyPayment);
            $createdAt = Carbon::today();
            $accountsDebtLines = [];
            $assignedPaymentTotal = 0;

            for ($i = 1; $i <= $transferFinancialDetails->installments; $i++) {
                $installmentAmount = $monthlyPayment;

                if ($i === $transferFinancialDetails->installments) {
                    $installmentAmount = $transferFinancialDetails->amount - $assignedPaymentTotal;
                }

                $accountsDebtLines[] = [
                    'sending_account_id' => $sourceClubAccount->id,
                    'receiving_account_id' => $targetClubAccount->id,
                    'amount' => $installmentAmount,
                    'created_at' => $createdAt->toDateString(),
                    'due_date' => $createdAt->copy()->addMonths($i)->toDateString(),
                ];

                $assignedPaymentTotal += $installmentAmount;
            }

            $targetClubAccount->future_balance += $transferFinancialDetails->amount;
            $targetClubAccount->transfer_budget += $transferFinancialDetails->amount;
            $sourceClubAccount->future_balance -= $transferFinancialDetails->amount;
            $sourceClubAccount->transfer_budget -= $transferFinancialDetails->amount;

            AccountsDebtLines::insert($accountsDebtLines);
            $targetClubAccount->save();
            $sourceClubAccount->save();
        } else {
            $this->financeService->makeTransaction(
                $targetClubAccount,
                $sourceClubAccount,
                $transferFinancialDetails->amount
            );

            $targetClubAccount->transfer_budget += $transferFinancialDetails->amount;
            $sourceClubAccount->transfer_budget -= $transferFinancialDetails->amount;

            $targetClubAccount->save();
            $sourceClubAccount->save();
        }
    }
}
