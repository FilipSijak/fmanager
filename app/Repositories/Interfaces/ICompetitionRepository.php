<?php

namespace App\Repositories\Interfaces;

use App\Models\Game;
use App\Models\Instance;
use App\Models\Season;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

interface ICompetitionRepository
{
    /** @return list<int> */
    public function clubIdsForCompetitionSeason(int $competitionId, int $seasonId, int $instanceId): array;

    public function competitionTable(int $competitionId): Collection;

    public function tournamentGroupsTables(int $competitionId): Collection;

    public function getCompetitionKnockoutStageSummary(int $competitionId): string;

    public function setCompetitionsSeasons(int $instanceId, int $seasonId): void;

    public function applyCompetitionProgressions(int $instanceId, int $sourceSeasonId): Season;

    /** @return EloquentCollection<int, Game> */
    public function getScheduledGames(Instance $instance): EloquentCollection;

    public function updateCompetitionTable(array $game): void;

    public function tournamentGroupsFinished(array $match): bool;

    public function resetTournamentGroupRule(int $competitionId): void;

    public function topClubsByTournamentGroup(int $competitionId): array;

    public function tournamentRoundWinner(int $matchId1, int $matchId2): ?int;
}
