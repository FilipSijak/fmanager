<?php

namespace App\Services\AccountingService;

use App\EntityTransactionDirection;
use App\EntityTransactionType;
use App\Models\Account;
use App\Models\FinanceTransactionEntity;
use App\Models\FinanceTransactions;
use App\Models\GameEntityAccount;
use App\Repositories\AccountingRepository;
use App\Services\AccountingService\Domain\AccountingRules;
use Carbon\CarbonInterface;

class AccountingService
{
    public function __construct(
        private readonly AccountingRepository $repository,
        private readonly AccountingRules $rules,
    ) {}

    public function transfer(Account $sender, Account $receiver, int $amount, CarbonInterface $date, string $category, string $reference): FinanceTransactions
    {
        $this->rules->ensureDifferentAccounts((int) $sender->id, (int) $receiver->id);

        return $this->post($sender, $receiver, $amount, $date, $category, $reference);
    }

    public function expense(Account $account, int $amount, CarbonInterface $date, string $category, string $reference): FinanceTransactions
    {
        return $this->post($account, null, $amount, $date, $category, $reference);
    }

    public function income(Account $account, int $amount, CarbonInterface $date, string $category, string $reference): FinanceTransactions
    {
        return $this->post(null, $account, $amount, $date, $category, $reference);
    }

    public function entityTransaction(
        GameEntityAccount $entityAccount,
        Account $clubAccount,
        EntityTransactionDirection $direction,
        EntityTransactionType $eventType,
        int $amount,
        CarbonInterface $date,
        ?int $eventId = null,
        bool $settlesCommitment = false,
    ): FinanceTransactionEntity {
        $this->rules->ensurePositiveAmount($amount);

        return $this->repository->transaction(function () use ($entityAccount, $clubAccount, $direction, $eventType, $amount, $date, $eventId, $settlesCommitment): FinanceTransactionEntity {
            $lockedClub = $this->repository->clubAccountForUpdate($clubAccount->id);
            $lockedEntity = $this->repository->entityAccountForUpdate($entityAccount->id);

            $this->rules->ensureSameInstance($this->repository->instanceId($lockedClub), (int) $lockedEntity->instance_id);

            $clubChange = $this->rules->clubBalanceChange($direction, $amount);
            $this->repository->applyBalanceChanges($lockedClub, $this->rules->balanceChanges($clubChange, $settlesCommitment));
            $this->repository->applyBalanceChanges($lockedEntity, $this->rules->balanceChanges(-$clubChange, $settlesCommitment));

            return $this->repository->createEntityPosting([
                'game_entity_account_id' => $lockedEntity->id,
                'club_account_id' => $lockedClub->id,
                'direction' => $direction,
                'event_type' => $eventType,
                'event_id' => $eventId,
                'amount' => $amount,
                'transaction_date' => $date,
            ]);
        });
    }

    private function post(?Account $sender, ?Account $receiver, int $amount, CarbonInterface $date, string $category, string $reference): FinanceTransactions
    {
        $attributes = $this->rules->postingAttributes($sender?->id, $receiver?->id, $amount, $date, $category, $reference);

        return $this->repository->transaction(function () use ($sender, $receiver, $amount, $reference, $attributes): FinanceTransactions {
            $accounts = $this->repository->clubAccountsForUpdate(array_values(array_filter([$sender?->id, $receiver?->id])));
            $lockedSender = $sender === null ? null : $accounts->get($sender->id);
            $lockedReceiver = $receiver === null ? null : $accounts->get($receiver->id);
            $this->rules->ensureAccountExists($sender !== null, $lockedSender !== null);
            $this->rules->ensureAccountExists($receiver !== null, $lockedReceiver !== null);
            if ($lockedSender !== null && $lockedReceiver !== null) {
                $this->rules->ensureSameInstance($this->repository->instanceId($lockedSender), $this->repository->instanceId($lockedReceiver));
            }

            $existing = $this->repository->postingForUpdate($reference);
            if ($existing !== null) {
                $this->rules->ensureMatchingPosting($existing->getAttributes(), $attributes);

                return $existing;
            }

            if ($lockedSender !== null) {
                $this->repository->applyBalanceChanges($lockedSender, $this->rules->balanceChanges(-$amount));
            }
            if ($lockedReceiver !== null) {
                $this->repository->applyBalanceChanges($lockedReceiver, $this->rules->balanceChanges($amount));
            }

            return $this->repository->createPosting($attributes);
        });
    }
}
