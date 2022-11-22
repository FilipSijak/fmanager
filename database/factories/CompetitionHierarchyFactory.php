<?php

namespace Database\Factories;

use App\Models\Competition;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class CompetitionHierarchyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'competition_id' => Competition::factory()->make()->id,
            'parent_competition_id' => Competition::factory()->make()->id,
            'child_competition_id' => Competition::factory()->make()->id,
        ];
    }
}
