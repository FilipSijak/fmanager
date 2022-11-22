<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\Competition;
use App\Models\Stadium;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Game>
 */
class GameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $homeTeamId = Club::factory()->make()->id;
        $stadium = Stadium::factory()->make();

        return [
            'competition_id' => Competition::factory()->make()->id,
            'hometeam_id' => $homeTeamId,
            'awayteam_id' => Club::factory()->make()->id,
            'stadium_id' => $stadium->id,
            'attendance' => $stadium->capacity,
            'match_start' => Carbon::now()->format('Y-m-d H:i'),
            'winner' => $homeTeamId,
            'home_team_goals' => 2,
            'away_team_goals' => 1,
            'match_summary' => ''
        ];
    }
}
