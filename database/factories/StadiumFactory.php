<?php

namespace Database\Factories;

use App\Models\City;
use App\Models\Instance;
use App\Models\Stadium;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Stadium>
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
            'instance_id' => Instance::factory(),
            'country_code' => $this->faker->countryCode,
            'city_id' => City::factory()->create()->id,
            'capacity' => random_int(1000, 100000),
        ];
    }
}
