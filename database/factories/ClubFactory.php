<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Instance;
use App\Models\Stadium;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Club>
 */
class ClubFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => $this->faker->name,
            'instance_id' => Instance::factory()->make(['id' => 1]),
            'country_code' => $this->faker->countryCode,
            'city_id' => City::factory()->make(['id' => 1]),
            'stadium_id' => Stadium::factory()->make(['id']),
            'rank' => $this->faker->numberBetween(1, 200),
            'rank_academy' => $this->faker->numberBetween(1, 200),
            'rank_training' => $this->faker->numberBetween(1, 200)
        ];
    }
}
