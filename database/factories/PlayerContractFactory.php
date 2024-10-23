<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlayerContract>
 */
class PlayerContractFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'salary' => $this->faker->numberBetween(1000, 10000),
            'appearance' => $this->faker->numberBetween(1000, 10000),
            'assist' => $this->faker->numberBetween(1000, 10000),
            'goal' => $this->faker->numberBetween(1000, 10000),
            'league' => $this->faker->numberBetween(1000, 10000),
            'pc_promotion_salary_raise' => 0,
            'pc_demotion_salary_cut' => 0,
            'cup' => $this->faker->numberBetween(1000, 10000),
            'el' => $this->faker->numberBetween(1000, 10000),
            'promotion' => $this->faker->numberBetween(1000, 10000),
            'clean_sheet' => $this->faker->numberBetween(1000, 10000)
        ];
    }
}
