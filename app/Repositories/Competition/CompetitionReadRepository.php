<?php

namespace App\Repositories\Competition;

use Illuminate\Support\Collection;

final class CompetitionReadRepository
{
    public function __construct(
        private readonly CompetitionStandingsRepository $standings,
        private readonly CompetitionTournamentRepository $tournaments,
    ) {}

    public function table(int $competitionId): Collection
    {
        return $this->standings->table($competitionId);
    }

    public function groupTables(int $competitionId): Collection
    {
        return $this->standings->groupTables($competitionId);
    }

    public function knockoutSummary(int $competitionId): string
    {
        return $this->tournaments->knockoutSummary($competitionId);
    }
}
