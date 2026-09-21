<?php

namespace Database\Factories;

use App\Models\BaseData\BaseFormation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BaseFormation>
 */
class BaseFormationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('4-#-#'),
            'name' => fake()->words(2, true),
            'is_active' => true,
            'tactical_tendency' => 'balanced',
        ];
    }
}
