<?php

namespace App\Services\FinanceService;

use App\DataModels\ClubFinancialSummary;
use App\EntityTransactionDirection;
use App\EntityTransactionType;
use App\Models\Account;
use App\Models\FinanceTransactionEntity;
use App\Models\FinanceTransactions;
use App\Models\GameEntityAccount;
use App\Models\Instance;
use App\Repositories\ClubRepository;
use App\Support\GameContext;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

class FinanceService
{
    public function __construct(
        private readonly ClubRepository $clubRepository,
        private readonly GameContext $gameContext,
    ) {}

    public function getClubFinances(): ?ClubFinancialSummary
    {
        $instance = Instance::query()->findOrFail($this->gameContext->instanceId());

        return $this->clubRepository->getTransferBudgetAndBalance($instance->club_id);
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
}
