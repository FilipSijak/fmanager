<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Instance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Stadium>
 */
class StadiumFactory extends Factory
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
            'instance_id' => Instance::factory()->make()->id,
            'country_code' => $this->faker->countryCode,
            'city_id' => City::factory()->make()->id,
            'capacity' => random_int(1000, 100000)
        ];
    }
}
