<?php

namespace Database\Factories;

use App\EntityTransactionDirection;
use App\EntityTransactionType;
use App\Models\Account;
use App\Models\FinanceTransactionEntity;
use App\Models\GameEntityAccount;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FinanceTransactionEntity> */
class FinanceTransactionEntityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_entity_account_id' => GameEntityAccount::factory(),
            'club_account_id' => Account::factory(),
            'direction' => EntityTransactionDirection::ENTITY_TO_CLUB,
            'event_type' => EntityTransactionType::PRIZE,
            'event_id' => null,
            'amount' => 100000,
            'transaction_date' => CarbonImmutable::now(),
        ];
    }
}
