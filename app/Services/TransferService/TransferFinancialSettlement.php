<?php

namespace App\Services\TransferService;

use App\Helpers\DisplayHelpers;
use App\Models\Account;
use App\Models\AccountsDebtLines;
use App\Models\FinanceTransactions;
use App\Models\Transfer;
use App\Models\TransferFinancialDetails;
use App\Services\FinanceService\AccountingService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TransferFinancialSettlement
{
    public function __construct(private readonly AccountingService $accounting) {}

    public function transferMoneyBetweenClubs(Transfer $transfer): void
    {
        DB::transaction(function () use ($transfer): void {
            $transfer = Transfer::query()->lockForUpdate()->findOrFail($transfer->id);
            $reference = 'transfer:'.$transfer->id;
            if (FinanceTransactions::query()->where('reference', $reference)->exists()) {
                return;
            }
            $transferFinancialDetails = TransferFinancialDetails::where('transfer_id', $transfer->id)->firstOrFail();
            $targetClubAccount = Account::where('club_id', $transfer->target_club_id)->firstOrFail();
            $sourceClubAccount = Account::where('club_id', $transfer->source_club_id)->firstOrFail();

            if ($transferFinancialDetails->installments > 0) {
                $monthlyPayment = $transferFinancialDetails->amount / $transferFinancialDetails->installments;
                $monthlyPayment = DisplayHelpers::roundAmounts($monthlyPayment);
                $createdAt = Carbon::parse($transfer->instance()->firstOrFail()->instance_date);
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
                if ($transferFinancialDetails->amount > 0) {
                    $this->accounting->transfer(
                        $sourceClubAccount,
                        $targetClubAccount,
                        $transferFinancialDetails->amount,
                        Carbon::parse($transfer->instance()->firstOrFail()->instance_date),
                        'transfer',
                        $reference,
                    );
                }

                $targetClubAccount->increment('transfer_budget', $transferFinancialDetails->amount);
                $sourceClubAccount->decrement('transfer_budget', $transferFinancialDetails->amount);
            }
        });
    }
}
