<?php

namespace Database\Factories;

use App\FinanceEntityLoanStatus;
use App\Models\Account;
use App\Models\FinanceEntityLoan;
use App\Models\GameEntityAccount;
use App\Models\Instance;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<FinanceEntityLoan> */
class FinanceEntityLoanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'instance_id' => Instance::factory(),
            'lender_game_entity_account_id' => GameEntityAccount::factory(),
            'borrower_club_account_id' => Account::factory(),
            'principal' => 100000,
            'interest_amount' => 10000,
            'total_amount' => 110000,
            'installment_count' => 11,
            'started_at' => CarbonImmutable::today(),
            'status' => FinanceEntityLoanStatus::ACTIVE,
        ];
    }
}
