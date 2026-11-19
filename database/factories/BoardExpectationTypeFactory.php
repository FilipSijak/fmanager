<?php

namespace Database\Factories;

use App\Models\BoardExpectationType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BoardExpectationType>
 */
class BoardExpectationTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'description' => fake()->sentence(),
            'rating' => fake()->unique()->randomElement(array_values(array_diff(range(0, 100), [20, 40, 60, 80, 100]))),
            'tolerance' => 2,
        ];
    }
}
