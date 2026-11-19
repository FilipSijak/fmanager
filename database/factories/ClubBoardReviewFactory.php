<?php

namespace Database\Factories;

use App\Models\ClubBoardReview;
use App\Models\ClubSeasonExpectation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ClubBoardReview>
 */
class ClubBoardReviewFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'club_season_expectation_id' => ClubSeasonExpectation::factory(),
            'instance_id' => fn (array $attributes): int => ClubSeasonExpectation::findOrFail($attributes['club_season_expectation_id'])->instance_id,
            'staff_coaching_id' => null,
            'reviewed_on' => '2030-10-15',
            'position' => 12,
            'played' => 8,
            'effective_tolerance' => 8,
            'outcome' => 'retained',
            'reason' => null,
        ];
    }
}
