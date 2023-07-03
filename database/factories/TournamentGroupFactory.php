<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\Competition;
use App\Models\Instance;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TournamentGroup>
 */
class TournamentGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'instance_id' => Instance::factory()->make(['id'])->id,
            'competition_id' => Competition::factory()->make(['id'])->id,
            'season_id' => Season::factory()->make(['id'])->id,
            'group_id' => $this->faker->randomDigit(),
            'club_id' => Club::factory()->make(['id'])->id,
            'points' => 0,
        ];
    }
}
