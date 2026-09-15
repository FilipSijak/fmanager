<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\AccountsDebtLinesEntity;
use App\Models\GameEntityAccount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AccountsDebtLinesEntity> */
class AccountsDebtLinesEntityFactory extends Factory
{
    public function definition(): array
    {
        $createdAt = CarbonImmutable::today();

        return [
            'game_entity_account_id' => GameEntityAccount::factory(),
            'club_account_id' => Account::factory(),
            'amount' => 100000,
            'created_at' => $createdAt,
            'due_date' => $createdAt->addMonth(),
        ];
    }
}
