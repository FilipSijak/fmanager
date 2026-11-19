<?php

namespace Database\Factories;

use App\Models\BoardExpectationType;
use App\Models\Club;
use App\Models\ClubSeasonExpectation;
use App\Models\Instance;
use App\Models\Season;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClubSeasonExpectation>
 */
class ClubSeasonExpectationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'instance_id' => Instance::factory(),
            'season_id' => fn (array $attributes): int => Season::factory()->create(['instance_id' => $attributes['instance_id']])->id,
            'club_id' => fn (array $attributes): int => Club::factory()->create(['instance_id' => $attributes['instance_id']])->id,
            'competition_id' => null,
            'board_expectation_type_id' => BoardExpectationType::query()->where('rating', 40)->value('id') ?? BoardExpectationType::factory(),
            'description' => 'Finish in mid-table',
            'rating' => 40,
            'tolerance' => 4,
            'expectation_score' => 50,
            'target_position' => null,
            'league_size' => null,
            'budget' => 1000000,
            'transfer_budget' => 500000,
            'team_quality' => 10,
            'club_rank' => 10,
            'assigned_on' => '2030-08-15',
        ];
    }
}
