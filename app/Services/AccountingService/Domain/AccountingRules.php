<?php

namespace App\Services\AccountingService\Domain;

use App\EntityTransactionDirection;
use Carbon\CarbonInterface;
use DomainException;

class AccountingRules
{
    public function ensureDifferentAccounts(int $senderId, int $receiverId): void
    {
        if ($senderId === $receiverId) {
            throw new DomainException('A payment requires different accounts.');
        }
    }

    public function ensurePositiveAmount(int $amount): void
    {
        if ($amount <= 0) {
            throw new DomainException('Finance transaction amount must be greater than zero.');
        }
    }

    public function ensureAccountExists(bool $required, bool $exists): void
    {
        if ($required && ! $exists) {
            throw new DomainException('The payment account no longer exists.');
        }
    }

    public function ensureSameInstance(int $firstInstanceId, int $secondInstanceId): void
    {
        if ($firstInstanceId !== $secondInstanceId) {
            throw new DomainException('Payments must belong to the same game instance.');
        }
    }

    /** @return array{sending_account_id: ?int, receiving_account_id: ?int, amount: int, transaction_date: string, category: string, reference: string} */
    public function postingAttributes(?int $senderId, ?int $receiverId, int $amount, CarbonInterface $date, string $category, string $reference): array
    {
        $this->ensurePositiveAmount($amount);
        if (trim($reference) === '' || trim($category) === '') {
            throw new DomainException('Payments require a category and reference.');
        }

        return [
            'sending_account_id' => $senderId,
            'receiving_account_id' => $receiverId,
            'amount' => $amount,
            'transaction_date' => $date->format('Y-m-d H:i:s'),
            'category' => $category,
            'reference' => $reference,
        ];
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array{sending_account_id: ?int, receiving_account_id: ?int, amount: int, transaction_date: string, category: string, reference: string}  $attributes
     */
    public function ensureMatchingPosting(array $existing, array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            if (($existing[$key] ?? null) != $value) {
                throw new DomainException('The payment reference has already been used for a different payment.');
            }
        }
    }

    public function clubBalanceChange(EntityTransactionDirection $direction, int $amount): int
    {
        return $direction === EntityTransactionDirection::ENTITY_TO_CLUB ? $amount : -$amount;
    }

    /** @return array{balance: int, future_balance?: int} */
    public function balanceChanges(int $amount, bool $settlesCommitment = false): array
    {
        return $settlesCommitment ? ['balance' => $amount] : ['balance' => $amount, 'future_balance' => $amount];
    }
}
