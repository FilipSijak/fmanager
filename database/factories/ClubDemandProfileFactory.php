<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\ClubDemandProfile;
use App\Models\Instance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClubDemandProfile>
 */
class ClubDemandProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_id' => Club::factory()->state(['instance_id' => Instance::factory()]),
            'instance_id' => fn (array $attributes): int => Club::query()->findOrFail($attributes['club_id'])->instance_id,
            'period_start' => '2030-08-01',
            'club_rank' => 10,
            'country_rank' => 100,
            'performance_score' => 0.5,
            'ticket_price_sensitivity' => 2.0,
            'commercial_price_sensitivity' => 2.0,
        ];
    }
}
