<?php

namespace Database\Factories;

use App\Models\Stadium;
use App\Models\StadiumStand;
use App\StadiumStandPosition;
use App\StadiumStandStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StadiumStand>
 */
class StadiumStandFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'stadium_id' => Stadium::factory(),
            'position' => StadiumStandPosition::NORTH,
            'capacity' => random_int(1000, 20000),
            'status' => StadiumStandStatus::ACTIVE,
        ];
    }
}
