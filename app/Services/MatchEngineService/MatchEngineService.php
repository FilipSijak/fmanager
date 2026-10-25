<?php

namespace App\Services\MatchEngineService;

use App\Models\Game;
use Carbon\Carbon;

class MatchEngineService
{
    public function __construct(
        private readonly MatchTeamBuilder $matchTeamBuilder,
        private readonly MatchSimulationEngine $matchSimulationEngine,
    ) {}

    public function simulate(Game $game): MatchSimulationResult
    {
        $matchDate = Carbon::parse($game->match_start)->toDateString();

        return $this->matchSimulationEngine->simulate(
            $this->matchTeamBuilder->forClub($game->hometeam_id, $matchDate),
            $this->matchTeamBuilder->forClub($game->awayteam_id, $matchDate),
        );
    }
}
