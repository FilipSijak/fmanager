<?php

namespace Database\Factories;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StadiumStandConstruction>
 */
class StadiumStandConstructionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = CarbonImmutable::now()->startOfDay();

        return [
            'instance_id' => Instance::factory(),
            'stadium_id' => Stadium::factory(),
            'stadium_stand_id' => StadiumStand::factory(),
            'target_capacity' => 1000,
            'capacity_increase' => 1000,
            'started_at' => $startedAt,
            'completes_at' => $startedAt->addWeek(),
        ];
    }
}
