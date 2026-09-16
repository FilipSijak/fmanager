<?php

namespace App\Services\FinanceService;

use App\DataModels\ClubFinancialSummary;
use App\EntityTransactionDirection;
use App\EntityTransactionType;
use App\FinanceEntityLoanStatus;
use App\FinanceLoanType;
use App\GameEntityType;
use App\Models\Account;
use App\Models\AccountsDebtLinesEntity;
use App\Models\FinanceEntityLoan;
use App\Models\FinanceTransactionEntity;
use App\Models\FinanceTransactions;
use App\Models\GameEntityAccount;
use App\Models\Instance;
use App\Repositories\ClubRepository;
use App\Services\FinanceService\Domain\CashLoanCalculator;
use App\Services\FinanceService\Domain\CashLoanEligibility;
use App\Services\FinanceService\Domain\CashLoanTerms;
use App\Services\FinanceService\Domain\MortgageLoanCalculator;
use App\Support\GameContext;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class FinanceService
{
    public function __construct(
        private readonly ClubRepository $clubRepository,
        private readonly GameContext $gameContext,
        private readonly CashLoanCalculator $cashLoanCalculator,
        private readonly CashLoanEligibility $cashLoanEligibility,
        private readonly MortgageLoanCalculator $mortgageLoanCalculator,
    ) {}

    public function getClubFinances(): ?ClubFinancialSummary
    {
        $instance = Instance::query()->findOrFail($this->gameContext->instanceId());

        return $this->clubRepository->getTransferBudgetAndBalance($instance->club_id);
    }

    public function takeOutCashLoan(
        int $amount,
        int $lengthMonths,
        CarbonInterface $startedAt,
    ): FinanceEntityLoan {
        $instance = Instance::query()->findOrFail($this->gameContext->instanceId());
        $clubAccount = Account::query()
            ->where('club_id', $instance->club_id)
            ->firstOrFail();
        $bankAccount = GameEntityAccount::query()
            ->where('instance_id', $instance->id)
            ->whereHas('gameEntity', function (Builder $query): void {
                $query->where('type', GameEntityType::BANK->value);
            })
            ->firstOrFail();
        $terms = $this->cashLoanCalculator->calculate($amount, $lengthMonths);

        return $this->issueLoan(
            $bankAccount,
            $clubAccount,
            $terms,
            $startedAt,
        );
    }

    public function payForConstruction(int $amount, CarbonInterface $transactionDate): FinanceTransactionEntity
    {
        $instance = Instance::query()->findOrFail($this->gameContext->instanceId());
        $clubAccount = Account::query()
            ->where('club_id', $instance->club_id)
            ->lockForUpdate()
            ->firstOrFail();
        if ($clubAccount->balance < $amount) {
            throw new DomainException('The club cannot afford this construction.');
        }
        $bankAccount = GameEntityAccount::query()
            ->where('instance_id', $instance->id)
            ->whereHas('gameEntity', function (Builder $query): void {
                $query->where('type', GameEntityType::BANK->value);
            })
            ->firstOrFail();

        return $this->makeEntityTransaction(
            $bankAccount,
            $clubAccount,
            EntityTransactionDirection::CLUB_TO_ENTITY,
            EntityTransactionType::STADIUM_CONSTRUCTION,
            $amount,
            $transactionDate,
        );
    }

    public function takeOutMortgageLoan(
        int $amount,
        int $lengthYears,
        CarbonInterface $startedAt,
        ?int $stadiumStandConstructionId = null,
        ?int $stadiumCommercialVenueId = null,
    ): FinanceEntityLoan {
        $instance = Instance::query()->findOrFail($this->gameContext->instanceId());
        $clubAccount = Account::query()->where('club_id', $instance->club_id)->firstOrFail();
        $bankAccount = GameEntityAccount::query()
            ->where('instance_id', $instance->id)
            ->whereHas('gameEntity', function (Builder $query): void {
                $query->where('type', GameEntityType::BANK->value);
            })
            ->firstOrFail();

        return $this->issueLoan(
            $bankAccount,
            $clubAccount,
            $this->mortgageLoanCalculator->calculate($amount, $lengthYears),
            $startedAt,
            FinanceLoanType::MORTGAGE,
            false,
            $stadiumStandConstructionId,
            $stadiumCommercialVenueId,
        );
    }

    public function issueLoan(
        GameEntityAccount $lenderGameEntityAccount,
        Account $borrowerClubAccount,
        CashLoanTerms $terms,
        CarbonInterface $startedAt,
        FinanceLoanType $loanType = FinanceLoanType::CASH,
        bool $disbursePrincipal = true,
        ?int $stadiumStandConstructionId = null,
        ?int $stadiumCommercialVenueId = null,
    ): FinanceEntityLoan {
        $principal = $terms->principal;
        $interestAmount = $terms->interestAmount;
        $totalAmount = $terms->totalAmount;
        $installmentCount = $terms->installmentCount;

        return DB::transaction(function () use (
            $lenderGameEntityAccount,
            $borrowerClubAccount,
            $principal,
            $interestAmount,
            $totalAmount,
            $installmentCount,
            $terms,
            $startedAt,
            $loanType,
            $disbursePrincipal,
            $stadiumStandConstructionId,
            $stadiumCommercialVenueId,
        ): FinanceEntityLoan {
            $lockedClubAccount = Account::query()
                ->whereKey($borrowerClubAccount->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->cashLoanEligibility->ensureEligible($lockedClubAccount, $terms, $disbursePrincipal);

            $loan = FinanceEntityLoan::query()->create([
                'instance_id' => $lenderGameEntityAccount->instance_id,
                'lender_game_entity_account_id' => $lenderGameEntityAccount->id,
                'borrower_club_account_id' => $borrowerClubAccount->id,
                'loan_type' => $loanType,
                'stadium_stand_construction_id' => $stadiumStandConstructionId,
                'stadium_commercial_venue_id' => $stadiumCommercialVenueId,
                'principal' => $principal,
                'interest_amount' => $interestAmount,
                'total_amount' => $totalAmount,
                'installment_count' => $installmentCount,
                'started_at' => $startedAt->toDateString(),
                'status' => FinanceEntityLoanStatus::ACTIVE,
            ]);

            if ($disbursePrincipal) {
                $this->makeEntityTransaction(
                    $lenderGameEntityAccount,
                    $lockedClubAccount,
                    EntityTransactionDirection::ENTITY_TO_CLUB,
                    EntityTransactionType::LOAN,
                    $principal,
                    $startedAt,
                    $loan->id,
                );
            }

            $lenderGameEntityAccount->newQuery()
                ->whereKey($lenderGameEntityAccount->id)
                ->update(['future_balance' => DB::raw("future_balance + {$totalAmount}")]);
            $borrowerClubAccount->newQuery()
                ->whereKey($lockedClubAccount->id)
                ->update(['future_balance' => DB::raw("future_balance - {$totalAmount}")]);

            foreach ($terms->installmentAmounts as $index => $installmentAmount) {
                $installmentNumber = $index + 1;

                $loan->installments()->create([
                    'game_entity_account_id' => $lenderGameEntityAccount->id,
                    'club_account_id' => $borrowerClubAccount->id,
                    'amount' => $installmentAmount,
                    'created_at' => $startedAt->toDateString(),
                    'due_date' => $startedAt->copy()->addMonths($installmentNumber)->toDateString(),
                    'installment_number' => $installmentNumber,
                ]);
            }

            return $loan->load('installments');
        });
    }

    public function repayLoanInstallment(
        AccountsDebtLinesEntity $installment,
        CarbonInterface $paidAt,
    ): FinanceTransactionEntity {
        return DB::transaction(function () use ($installment, $paidAt): FinanceTransactionEntity {
            $lockedInstallment = AccountsDebtLinesEntity::query()
                ->whereKey($installment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedInstallment->paid_at !== null) {
                throw new DomainException('This loan installment has already been paid.');
            }

            $loan = $lockedInstallment->loan()->lockForUpdate()->firstOrFail();

            $transaction = $this->recordEntityRepayment(
                GameEntityAccount::query()
                    ->whereKey($lockedInstallment->game_entity_account_id)
                    ->lockForUpdate()
                    ->firstOrFail(),
                Account::query()
                    ->whereKey($lockedInstallment->club_account_id)
                    ->lockForUpdate()
                    ->firstOrFail(),
                $lockedInstallment->amount,
                $paidAt,
                $loan->id,
            );

            $lockedInstallment->forceFill([
                'paid_at' => $paidAt,
                'transaction_id' => $transaction->id,
            ])->save();

            if (! $loan->installments()->whereNull('paid_at')->exists()) {
                $loan->update(['status' => FinanceEntityLoanStatus::COMPLETED]);
            }

            return $transaction;
        });
    }

    public function processDueLoanInstallments(Instance $instance, CarbonInterface $asOf): int
    {
        $processed = 0;

        AccountsDebtLinesEntity::query()
            ->whereNull('paid_at')
            ->whereDate('due_date', '<=', $asOf->toDateString())
            ->whereHas('loan', function (Builder $query) use ($instance): void {
                $query
                    ->where('instance_id', $instance->id)
                    ->where('status', FinanceEntityLoanStatus::ACTIVE);
            })
            ->orderBy('due_date')
            ->orderBy('id')
            ->get()
            ->each(function (AccountsDebtLinesEntity $installment) use (&$processed, $asOf): void {
                $this->repayLoanInstallment($installment, $asOf);
                $processed++;
            });

        return $processed;
    }

    public function makeEntityTransaction(
        GameEntityAccount $gameEntityAccount,
        Account $clubAccount,
        EntityTransactionDirection $direction,
        EntityTransactionType $eventType,
        int $amount,
        CarbonInterface $transactionDate,
        ?int $eventId = null,
    ): FinanceTransactionEntity {
        if ($amount <= 0) {
            throw new DomainException('Finance transaction amount must be greater than zero.');
        }

        return DB::transaction(function () use (
            $gameEntityAccount,
            $clubAccount,
            $direction,
            $eventType,
            $amount,
            $transactionDate,
            $eventId,
        ): FinanceTransactionEntity {
            $lockedEntityAccount = GameEntityAccount::query()
                ->whereKey($gameEntityAccount->id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedClubAccount = Account::query()
                ->whereKey($clubAccount->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($direction === EntityTransactionDirection::ENTITY_TO_CLUB) {
                $lockedEntityAccount->decrement('balance', $amount);
                $lockedEntityAccount->decrement('future_balance', $amount);
                $lockedClubAccount->increment('balance', $amount);
                $lockedClubAccount->increment('future_balance', $amount);
            } else {
                $lockedEntityAccount->increment('balance', $amount);
                $lockedEntityAccount->increment('future_balance', $amount);
                $lockedClubAccount->decrement('balance', $amount);
                $lockedClubAccount->decrement('future_balance', $amount);
            }

            return FinanceTransactionEntity::query()->create([
                'game_entity_account_id' => $lockedEntityAccount->id,
                'club_account_id' => $lockedClubAccount->id,
                'direction' => $direction,
                'event_type' => $eventType,
                'event_id' => $eventId,
                'amount' => $amount,
                'transaction_date' => $transactionDate,
            ]);
        });
    }

    public function makeTransaction(
        Account $receivingAccount,
        Account $sendingAccount,
        int $amount,
    ): bool {
        try {
            DB::beginTransaction();

            // log transaction
            $transaction = new FinanceTransactions([
                'sending_account_id' => $sendingAccount->id,
                'receiving_account_id' => $receivingAccount->id,
                'amount' => $amount,
                'transaction_date' => Carbon::today()->toDateString(),
            ]);

            // move amount
            $sendingAccount->balance -= $amount;
            $sendingAccount->future_balance -= $amount;
            $receivingAccount->balance += $amount;
            $receivingAccount->future_balance += $amount;

            $receivingAccount->save();
            $sendingAccount->save();
            $transaction->save();
        } catch (\Exception $e) {
            DB::rollBack();

            // log error

            return false;
        }

        DB::commit();

        return true;
    }

    private function recordEntityRepayment(
        GameEntityAccount $entityAccount,
        Account $clubAccount,
        int $amount,
        CarbonInterface $paidAt,
        int $loanId,
    ): FinanceTransactionEntity {
        $entityAccount->increment('balance', $amount);
        $clubAccount->decrement('balance', $amount);

        return FinanceTransactionEntity::query()->create([
            'game_entity_account_id' => $entityAccount->id,
            'club_account_id' => $clubAccount->id,
            'direction' => EntityTransactionDirection::CLUB_TO_ENTITY,
            'event_type' => EntityTransactionType::LOAN_REPAYMENT,
            'event_id' => $loanId,
            'amount' => $amount,
            'transaction_date' => $paidAt,
        ]);
    }
}
