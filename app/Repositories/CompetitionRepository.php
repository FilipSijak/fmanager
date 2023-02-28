<?php

namespace App\Repositories;

use App\Services\CompetitionService\DataLayer\CompetitionDataSource;
use Illuminate\Database\Eloquent\Collection;

class CompetitionRepository
{
    private CompetitionDataSource $competitionDataSource;

    public function __construct(CompetitionDataSource $competitionDataSource)
    {
        $this->competitionDataSource = $competitionDataSource;
    }

    public function setCompetitionsSeasons(int $seasonId)
    {
        $this->competitionDataSource->storeInitialCompetitionSeasonClubs($seasonId);
    }

    public function setCompetitionPoints(int $seasonId, Collection $clubs, int $competitionId)
    {
        $this->competitionDataSource->storeCompetitionPoints($seasonId, $clubs, $competitionId);
    }
}
