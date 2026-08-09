<?php

namespace App\Services\GameService;

use App\Models\Game;

class GameService
{
    public function simulateGameResult(): array
    {
        $winner = rand(1, 3);
        $winnerGoals = rand(1, 5);

        if ($winner === 1) {
            return ['home_team_goals' => $winnerGoals, 'away_team_goals' => rand(0, $winnerGoals - 1)];
        }
        if ($winner === 2) {
            return ['home_team_goals' => rand(0, $winnerGoals - 1), 'away_team_goals' => $winnerGoals];
        }

        $goals = rand(0, 3);
        return ['home_team_goals' => $goals, 'away_team_goals' => $goals];
    }

    public function simulateMatchExtraTime(int $gameId): int
    {
        $game = Game::query()->findOrFail($gameId);
        return rand(1, 2) === 1 ? $game->hometeam_id : $game->awayteam_id;
    }
}
