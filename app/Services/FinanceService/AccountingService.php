<?php

namespace App\Services\FinanceService;

use App\EntityTransactionDirection;
use App\EntityTransactionType;
use App\Models\Account;
use App\Models\Club;
use App\Models\FinanceTransactionEntity;
use App\Models\FinanceTransactions;
use App\Models\GameEntityAccount;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Support\Facades\DB;

class AccountingService
{
    public function transfer(Account $sender, Account $receiver, int $amount, CarbonInterface $date, string $category, string $reference): FinanceTransactions
    {
        if ($sender->is($receiver)) {
            throw new DomainException('A payment requires different accounts.');
        }

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
        $this->validateAmount($amount);

        return DB::transaction(function () use ($entityAccount, $clubAccount, $direction, $eventType, $amount, $date, $eventId, $settlesCommitment): FinanceTransactionEntity {
            $lockedClub = Account::query()->lockForUpdate()->findOrFail($clubAccount->id);
            $lockedEntity = GameEntityAccount::query()->lockForUpdate()->findOrFail($entityAccount->id);

            if ($this->instanceId($lockedClub) !== (int) $lockedEntity->instance_id) {
                throw new DomainException('Payments must belong to the same game instance.');
            }

            $clubChange = $direction === EntityTransactionDirection::ENTITY_TO_CLUB ? $amount : -$amount;
            $this->changeBalance($lockedClub, $clubChange, $settlesCommitment);
            $this->changeBalance($lockedEntity, -$clubChange, $settlesCommitment);

            return FinanceTransactionEntity::query()->create([
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
        $this->validateAmount($amount);
        if (trim($reference) === '' || trim($category) === '') {
            throw new DomainException('Payments require a category and reference.');
        }

        return DB::transaction(function () use ($sender, $receiver, $amount, $date, $category, $reference): FinanceTransactions {
            $accounts = Account::query()->whereIn('id', array_filter([$sender?->id, $receiver?->id]))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $lockedSender = $sender === null ? null : $accounts->get($sender->id);
            $lockedReceiver = $receiver === null ? null : $accounts->get($receiver->id);
            if (($sender !== null && $lockedSender === null) || ($receiver !== null && $lockedReceiver === null)) {
                throw new DomainException('The payment account no longer exists.');
            }
            if ($lockedSender !== null && $lockedReceiver !== null && $this->instanceId($lockedSender) !== $this->instanceId($lockedReceiver)) {
                throw new DomainException('Payments must belong to the same game instance.');
            }

            $attributes = [
                'sending_account_id' => $sender?->id,
                'receiving_account_id' => $receiver?->id,
                'amount' => $amount,
                'transaction_date' => $date->format('Y-m-d H:i:s'),
                'category' => $category,
                'reference' => $reference,
            ];
            $existing = FinanceTransactions::query()->where('reference', $reference)->lockForUpdate()->first();
            if ($existing !== null) {
                foreach ($attributes as $key => $value) {
                    if ($existing->getAttribute($key) != $value) {
                        throw new DomainException('The payment reference has already been used for a different payment.');
                    }
                }

                return $existing;
            }

            if ($lockedSender !== null) {
                $this->changeBalance($lockedSender, -$amount);
            }
            if ($lockedReceiver !== null) {
                $this->changeBalance($lockedReceiver, $amount);
            }

            return FinanceTransactions::query()->create($attributes);
        });
    }

    private function validateAmount(int $amount): void
    {
        if ($amount <= 0) {
            throw new DomainException('Finance transaction amount must be greater than zero.');
        }
    }

    private function instanceId(Account $account): int
    {
        return (int) Club::query()->findOrFail($account->club_id)->instance_id;
    }

    private function changeBalance(Account|GameEntityAccount $account, int $amount, bool $settlesCommitment = false): void
    {
        $account->increment('balance', $amount);
        if (! $settlesCommitment) {
            $account->increment('future_balance', $amount);
        }
    }
}
