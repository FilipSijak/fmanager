<?php

namespace Database\Factories;

use App\Models\StaffContract;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StaffContract>
 */
class StaffContractFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'contract_start' => '2026-07-01',
            'contract_end' => '2027-06-30',
            'salary' => 1000,
            'signing_fee' => null,
        ];
    }
}
