<?php

namespace App\Services\CompetitionService\Competitions;

use App\Models\Season;
use App\Repositories\CompetitionRepository;

class LeagueUpdater
{
    public function __construct(CompetitionRepository $competitionRepository)
    {
        $this->competitionRepository = $competitionRepository;
    }

    public function updatePointsTable(array $games)
    {
        foreach ($games as $game) {
            $this->competitionRepository->updatePointsTable($game);
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
