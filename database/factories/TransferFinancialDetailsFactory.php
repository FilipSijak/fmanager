<?php

namespace Database\Factories;

use App\Models\Transfer;
use App\Models\TransferFinancialDetails;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TransferFinancialDetails>
 */
class TransferFinancialDetailsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'transfer_id' => Transfer::factory()->make(['id' => 1])->id,
            'amount' => $this->faker->numberBetween(1000, 10000),
            'installments' => 0,
        ];
    }
}
