<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\Competition;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class CompetitionPointsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'competition_id' => Competition::factory()->create()->id,
            'season_id' => Season::factory()->create()->id,
            'club_id' => Club::factory()->create()->id,
            'points' => random_int(0, 120)
        ];
    }
}
