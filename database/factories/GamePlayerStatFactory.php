<?php

namespace Database\Factories;

use App\Models\Club;
use App\Models\Game;
use App\Models\GamePlayerStat;
use App\Models\Player;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GamePlayerStat>
 */
class GamePlayerStatFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $passesAttempted = fake()->numberBetween(10, 80);
        $tacklesAttempted = fake()->numberBetween(0, 6);

        return [
            'instance_id' => 1,
            'game_id' => Game::factory(),
            'club_id' => Club::factory(),
            'player_id' => Player::factory(),
            'position' => 'CM',
            'started' => true,
            'minutes_played' => 90,
            'passes_attempted' => $passesAttempted,
            'passes_completed' => fake()->numberBetween(0, $passesAttempted),
            'tackles_attempted' => $tacklesAttempted,
            'tackles_won' => fake()->numberBetween(0, $tacklesAttempted),
            'interceptions' => fake()->numberBetween(0, 4),
            'rating' => fake()->randomFloat(1, 5, 8),
        ];
    }

    public function goalkeeper(int $goalsConceded = 0): static
    {
        return $this->state(fn (): array => [
            'position' => 'GK',
            'goals_conceded' => $goalsConceded,
            'clean_sheet' => $goalsConceded === 0,
        ]);
    }
}
