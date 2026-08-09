<?php

namespace App\Services\GameService;

use App\Models\Game;

class GameService
{
    public function simulateMatchExtraTime(int $gameId): int
    {
        $game = Game::query()->findOrFail($gameId);
        return rand(1, 2) === 1 ? $game->hometeam_id : $game->awayteam_id;
    }
}
