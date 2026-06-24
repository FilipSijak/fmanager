<?php

namespace App\Services\CompetitionService;

use App\Models\Competition;
use App\Models\Season;
use App\Services\CompetitionService\Competitions\CompetitionUpdater;
use App\Services\CompetitionService\Competitions\LeagueUpdater;
use App\Services\CompetitionService\Competitions\Tournament;
use App\Services\CompetitionService\Competitions\TournamentUpdater;
use App\Services\CompetitionService\DataLayer\CompetitionDataSource;
use App\Services\CompetitionService\Competitions\LeagueScheduleGenerator;
use Carbon\Carbon;
use Illuminate\Support\Collection;


class CompetitionService implements ICompetitionService
{
    private Season $season;
    private int    $instanceId;

    public function __construct(
        LeagueUpdater $leagueUpdater,
        TournamentUpdater $tournamentUpdater,
        private readonly CompetitionDataSource $competitionDataSource
    )
    {
        $this->leagueUpdater = $leagueUpdater;
        $this->tournamentUpdater = $tournamentUpdater;
    }

    public function makeLeague(array $clubIds, int $competitionId, int $seasonId, int $instanceId): void
    {
        if (count($clubIds) !== 20) {
            throw new \UnexpectedValueException(
                'League schedule requires exactly 20 clubs, '.count($clubIds).' provided.'
            );
        }

        $season = Season::query()->findOrFail($seasonId);
        $seasonYear = (int) Carbon::parse($season->start_date)->format('Y');
        $fixtures = (new LeagueScheduleGenerator($seasonYear))->generateSchedule($clubIds);

        $this->competitionDataSource->storeLeagueScheduleFixtures(
            $fixtures,
            $competitionId,
            $seasonId,
            $instanceId
        );
    }

    public function getAllCompetitions(): Collection
    {
        return Competition::all();
    }

    public function makeTournament(Collection $clubs, $competitionId, $seasonId, $instanceId)
    {
        $tournament = new Tournament();
        $dataSource = new CompetitionDataSource();
        $season = Season::where('id', $seasonId)->first();

        $groupSchedule = $tournament->createTournament($clubs, $instanceId, $seasonId);
        $schedule = $tournament->setTournamentFixtures($instanceId, $seasonId, $groupSchedule, $competitionId, $season->start_date);
        $dataSource->storeTournamentKnockoutSchedule($instanceId, $competitionId, $seasonId, $schedule);
    }

    public function updateTournamentSchedule()
    {

    }

    public function tournamentNewRound(array $clubs)
    {
        $tournament = new Tournament();

        return $tournament->setNextRoundPairs($clubs);
    }

    public function makeTournamentGroupStage(Collection $clubs, $competitionId, $seasonId, $instanceId)
    {
        $tournament = new Tournament();
        $groups = $tournament->createTournamentGroups($clubs->toArray());
        $dataSource = new CompetitionDataSource();

        $dataSource->insertTournamentGroups($instanceId, $groups, $competitionId, $seasonId);
    }

    public function competitionsRoundUpdate(array $games)
    {
        $competitionUpdater = new CompetitionUpdater($this->leagueUpdater, $this->tournamentUpdater);

        $competitionUpdater->setGamesByCompetition($games);
        $competitionUpdater->distributeGamesForCompetitionUpdates($this->season, $this->instanceId);
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
