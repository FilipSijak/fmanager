<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Country>
 */
class CountryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'code' => $this->faker->countryCode,
            'name' => $this->faker->country,
            'ranking' => random_int(10, 200),
            'population' => random_int(100000, 10000000),
            'gdp' => random_int(10,20000),
            'gdppcapita' => random_int(1000, 100000),
            'continent' => 'Europe',
            'region' => 'West Europe'
        ];
    }
}
