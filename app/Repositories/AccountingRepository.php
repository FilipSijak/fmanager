<?php

namespace App\Repositories;

use App\EntityTransactionDirection;
use App\EntityTransactionType;
use App\Models\Account;
use App\Models\Club;
use App\Models\FinanceTransactionEntity;
use App\Models\FinanceTransactions;
use App\Models\GameEntityAccount;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AccountingRepository
{
    public function transaction(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }

    public function clubAccountForUpdate(int $accountId): Account
    {
        return Account::query()->lockForUpdate()->findOrFail($accountId);
    }

    /**
     * @param  list<int>  $accountIds
     * @return Collection<int, Account>
     */
    public function clubAccountsForUpdate(array $accountIds): Collection
    {
        return Account::query()->whereIn('id', $accountIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }

    public function entityAccountForUpdate(int $accountId): GameEntityAccount
    {
        return GameEntityAccount::query()->lockForUpdate()->findOrFail($accountId);
    }

    public function instanceId(Account $account): int
    {
        return (int) Club::query()->findOrFail($account->club_id)->instance_id;
    }

    public function postingForUpdate(string $reference): ?FinanceTransactions
    {
        return FinanceTransactions::query()->where('reference', $reference)->lockForUpdate()->first();
    }

    /** @param array{sending_account_id: ?int, receiving_account_id: ?int, amount: int, transaction_date: string, category: string, reference: string} $attributes */
    public function createPosting(array $attributes): FinanceTransactions
    {
        return FinanceTransactions::query()->create($attributes);
    }

    /** @param array{game_entity_account_id: int, club_account_id: int, direction: EntityTransactionDirection, event_type: EntityTransactionType, event_id: ?int, amount: int, transaction_date: CarbonInterface} $attributes */
    public function createEntityPosting(array $attributes): FinanceTransactionEntity
    {
        return FinanceTransactionEntity::query()->create($attributes);
    }

    /** @param array{balance: int, future_balance?: int} $changes */
    public function applyBalanceChanges(Account|GameEntityAccount $account, array $changes): void
    {
        foreach ($changes as $column => $amount) {
            $account->increment($column, $amount);
        }
    }
}
