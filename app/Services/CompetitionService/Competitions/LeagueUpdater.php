<?php

namespace App\Services\CompetitionService\Competitions;

use App\Models\Season;
use App\Repositories\Competition\CompetitionStandingsRepository;

class LeagueUpdater
{
    public function __construct(CompetitionStandingsRepository $competitionRepository)
    {
        $this->competitionRepository = $competitionRepository;
    }

    public function updatePointsTable(array $games)
    {
        foreach ($games as $game) {
            $this->competitionRepository->update($game);
        }
    }

    public function setInstanceId(int $instanceId)
    {
        $this->instanceId = $instanceId;
    }

    public function setSeason(Season $season)
    {
        $this->season = $season;
    }
}
