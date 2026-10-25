<?php

namespace App\Services\MatchEngineService;

use App\Models\Game;

class MatchEngineService
{
    public function __construct(
        private readonly MatchTeamBuilder $matchTeamBuilder,
        private readonly MatchSimulationEngine $matchSimulationEngine,
    ) {}

    public function simulate(Game $game): MatchSimulationResult
    {
        return $this->matchSimulationEngine->simulate(
            $this->matchTeamBuilder->forClub($game->hometeam_id),
            $this->matchTeamBuilder->forClub($game->awayteam_id),
        );
    }
}
