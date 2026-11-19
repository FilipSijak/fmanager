<?php

namespace App\Services\TransferService;

use App\Helpers\DisplayHelpers;
use App\Models\Account;
use App\Models\AccountsDebtLines;
use App\Models\Club;
use App\Models\FinanceTransactions;
use App\Models\Instance;
use App\Models\Transfer;
use App\Models\TransferFinancialDetails;
use App\Services\AccountingService\AccountingService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class TransferFinancialSettlement
{
    public function __construct(private readonly AccountingService $accounting) {}

    public function transferMoneyBetweenClubs(Transfer $transfer): void
    {
        DB::transaction(function () use ($transfer): void {
            $transfer = Transfer::query()->lockForUpdate()->findOrFail($transfer->id);
            $reference = 'transfer:'.$transfer->id;
            if ($transfer->financially_settled_on !== null || FinanceTransactions::query()->where('reference', $reference)->exists()) {
                return;
            }
            $transferFinancialDetails = TransferFinancialDetails::where('transfer_id', $transfer->id)->firstOrFail();
            $clubs = Club::query()->where('instance_id', $transfer->instance_id)
                ->whereIn('id', [$transfer->source_club_id, $transfer->target_club_id])->pluck('id');
            if ($clubs->count() !== 2) {
                throw new DomainException('Transfer accounts must belong to different clubs in the same game instance.');
            }
            $accounts = Account::query()->whereIn('club_id', $clubs)->orderBy('id')->lockForUpdate()->get()->keyBy('club_id');
            $targetClubAccount = $accounts->get($transfer->target_club_id);
            $sourceClubAccount = $accounts->get($transfer->source_club_id);
            if ($targetClubAccount === null || $sourceClubAccount === null) {
                throw new DomainException('The transfer account no longer exists.');
            }
            $settledOn = Carbon::parse($transfer->instance()->firstOrFail()->instance_date);

            if ($transferFinancialDetails->amount < 0 || $transferFinancialDetails->installments < 0) {
                throw new DomainException('Transfer amounts and installment counts cannot be negative.');
            }

            if ($transferFinancialDetails->installments > 0) {
                $monthlyPayment = $transferFinancialDetails->amount / $transferFinancialDetails->installments;
                $monthlyPayment = min(DisplayHelpers::roundAmounts($monthlyPayment), intdiv($transferFinancialDetails->amount, $transferFinancialDetails->installments));
                $createdAt = $settledOn;
                $accountsDebtLines = [];
                $assignedPaymentTotal = 0;

                for ($i = 1; $i <= $transferFinancialDetails->installments; $i++) {
                    $installmentAmount = $monthlyPayment;

                    if ($i === $transferFinancialDetails->installments) {
                        $installmentAmount = $transferFinancialDetails->amount - $assignedPaymentTotal;
                    }

                    $accountsDebtLines[] = [
                        'transfer_id' => $transfer->id,
                        'installment_number' => $i,
                        'sending_account_id' => $sourceClubAccount->id,
                        'receiving_account_id' => $targetClubAccount->id,
                        'amount' => $installmentAmount,
                        'created_at' => $createdAt->toDateString(),
                        'due_date' => $createdAt->copy()->addMonthsNoOverflow($i)->toDateString(),
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
                        $settledOn,
                        'transfer',
                        $reference,
                    );
                }

                $targetClubAccount->increment('transfer_budget', $transferFinancialDetails->amount);
                $sourceClubAccount->decrement('transfer_budget', $transferFinancialDetails->amount);
            }

            $transfer->financially_settled_on = $settledOn->toDateString();
            $transfer->save();
        });
    }

    public function processDueInstallments(Instance $instance): void
    {
        $date = Carbon::parse($instance->instance_date)->startOfDay();
        $accountIds = Account::query()->select('id')->whereIn('club_id', Club::query()->select('id')->where('instance_id', $instance->id));

        AccountsDebtLines::query()
            ->whereNull('paid_on')
            ->where('due_date', '<=', $date->toDateString())
            ->whereIn('sending_account_id', $accountIds)
            ->whereIn('receiving_account_id', $accountIds)
            ->chunkById(100, function (Collection $installments) use ($date): void {
                foreach ($installments as $installment) {
                    DB::transaction(function () use ($installment, $date): void {
                        $installment = AccountsDebtLines::query()->lockForUpdate()->findOrFail($installment->id);
                        if ($installment->paid_on !== null) {
                            return;
                        }
                        if ($installment->amount < 0) {
                            throw new DomainException('Transfer installments cannot be negative.');
                        }

                        if ($installment->amount > 0) {
                            $posting = $this->accounting->transfer(
                                Account::query()->findOrFail($installment->sending_account_id),
                                Account::query()->findOrFail($installment->receiving_account_id),
                                $installment->amount,
                                $date,
                                'transfer_installment',
                                'transfer-installment:'.$installment->id,
                                settlesCommitment: true,
                            );
                            $installment->transaction_id = $posting->id;
                        }

                        $installment->paid_on = $date->toDateString();
                        $installment->save();
                    });
                }
            });
    }
}
