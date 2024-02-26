<?php

namespace Database\Factories;

use App\Models\Club;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountFactory extends Factory
{
    public function definition()
    {
        return [
            'club_id' => Club::factory()->make(['id' => 1])->id,
            'balance' => 68000000,
            'future_balance' => 68000000,
            'allowed_debt' => 68000000,
            'transfer_budget' => 68000000,
            'salaries_yearly_budget' => 136000000
        ];
    }
}
