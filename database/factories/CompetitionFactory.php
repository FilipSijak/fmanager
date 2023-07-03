<?php

namespace Database\Factories;

use App\Models\Instance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class CompetitionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'instance_id' => Instance::factory()->make(['id' => 1])->id,
            'name' => $this->faker->name,
            'country_code' => $this->faker->countryCode,
            'rank' => random_int(1, 20),
            'type' => 'league',
            'groups' => 0,
            'clubs_number' => 20
        ];
    }
}
