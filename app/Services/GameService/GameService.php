<?php

namespace App\Services\GameService;

use App\Models\Game;
use Illuminate\Database\Eloquent\Collection;

class GameService
{
    public function simulateRound(Collection $games)
    {
        foreach ($games as $game) {
            $this->simulateGame($game);
        }
    }

    private function simulateGame(Game $game)
    {
        $game->winner = (string) rand(1, 3);
        $this->storeGameGoals($game);
        $game->save();
    }

    public function simulateMatchExtraTime(int $gameId)
    {
        $game = Game::where('id', $gameId)->first();

        $game->winner = (string) rand(1, 2);
        $this->storeGameGoals($game);
        $game->save();

        return $game->winner == 1 ? $game->hometeam_id : $game->awayteam_id;
    }

    private function storeGameGoals(Game &$game)
    {
        $winnerGoals = rand(1, 5);

        switch ($game->winner) {
            case 1:
                // home team win
                $game->home_team_goals = $winnerGoals;
                $game->away_team_goals = rand(0, $winnerGoals - 1);
                break;
            case 2:
                // away team win
                $game->away_team_goals = $winnerGoals;
                $game->home_team_goals = rand(0, $winnerGoals - 1);

                break;
            case 3:
                // draw
                $goals = rand(0, 3);

                $game->away_team_goals = $goals;
                $game->home_team_goals = $goals;
        }
    }
}
