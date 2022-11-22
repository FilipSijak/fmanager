<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\Instance;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Balance>
 */
class BalanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'club_id' => Club::factory()->make()->id,
            'balance' => random_int(100000, 20000000),
            'debt' => random_int(100000, 20000000),
            'debt_expiration' => Carbon::now()->addYears(random_int(2, 15))
        ];
    }
}
