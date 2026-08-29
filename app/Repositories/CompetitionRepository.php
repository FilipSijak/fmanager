<?php

namespace App\Repositories;

use App\Models\Game;
use App\Models\Instance;
use App\Models\Season;
use App\Repositories\Competition\CompetitionScheduleRepository;
use App\Repositories\Competition\CompetitionSeasonRepository;
use App\Repositories\Competition\CompetitionStandingsRepository;
use App\Repositories\Competition\CompetitionTournamentRepository;
use App\Repositories\Interfaces\ICompetitionRepository;
use App\Services\CompetitionService\DataLayer\CompetitionDataSource;
use App\Services\GameService\GameService;
use App\Support\GameContext;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/** Backwards-compatible façade. New consumers should use the focused repositories. */
class CompetitionRepository implements ICompetitionRepository
{
    private readonly CompetitionSeasonRepository $seasons;

    private readonly CompetitionStandingsRepository $standings;

    private readonly CompetitionTournamentRepository $tournaments;

    private readonly CompetitionScheduleRepository $schedule;

    public function __construct(
        CompetitionDataSource $dataSource,
        private readonly GameContext $gameContext,
        GameService $gameService,
    ) {
        $this->seasons = new CompetitionSeasonRepository($dataSource);
        $this->standings = new CompetitionStandingsRepository($gameContext);
        $this->tournaments = new CompetitionTournamentRepository($gameContext, $gameService);
        $this->schedule = new CompetitionScheduleRepository;
    }

    public function setSeasonId(?int $id): void
    {
        $this->gameContext->setSeasonId($id);
    }

    public function setInstanceId(?int $id): void
    {
        $this->gameContext->setInstanceId($id);
    }

    public function clubIdsForCompetitionSeason(int $competitionId, int $seasonId, int $instanceId): array
    {
        return $this->seasons->clubIds($competitionId, $seasonId, $instanceId);
    }

    public function competitionTable(int $competitionId): Collection
    {
        return $this->standings->table($competitionId);
    }

    public function tournamentGroupsTables(int $competitionId): Collection
    {
        return $this->standings->groupTables($competitionId);
    }

    public function getCompetitionKnockoutStageSummary(int $competitionId): string
    {
        return $this->tournaments->knockoutSummary($competitionId);
    }

    public function setCompetitionsSeasons(int $instanceId, int $seasonId): void
    {
        $this->seasons->storeInitial($instanceId, $seasonId);
    }

    public function applyCompetitionProgressions(int $instanceId, int $sourceSeasonId): Season
    {
        return $this->seasons->applyProgressions($instanceId, $sourceSeasonId);
    }

    /** @return EloquentCollection<int, Game> */
    public function getScheduledGames(Instance $instance): EloquentCollection
    {
        return $this->schedule->scheduledFor($instance);
    }

    public function updateCompetitionTable(array $game): void
    {
        $this->standings->update($game);
    }

    public function tournamentGroupsFinished(array $match): bool
    {
        return $this->tournaments->groupsFinished($match);
    }

    public function resetTournamentGroupRule(int $competitionId): void
    {
        $this->tournaments->disableGroups($competitionId);
    }

    public function topClubsByTournamentGroup(int $competitionId): array
    {
        return $this->standings->topClubsByGroup($competitionId);
    }

    public function tournamentRoundWinner(int $matchId1, int $matchId2): ?int
    {
        return $this->tournaments->roundWinner($matchId1, $matchId2);
    }
}
