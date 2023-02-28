<?php

namespace App\Services\CompetitionService;

use App\Models\Competition;
use App\Models\Season;
use App\Services\CompetitionService\Competitions\CompetitionConfig;
use App\Services\CompetitionService\Competitions\League;
use App\Services\CompetitionService\Competitions\Tournament;
use App\Services\CompetitionService\DataLayer\CompetitionDataSource;
use Illuminate\Support\Collection;

class CompetitionService implements ICompetitionService
{
    public function makeLeague($clubs, $competitionId, $seasonId, $instanceId)
    {
        try {
            $countClubs = count($clubs);

            if ($countClubs) {
                $leagueGames = (new League())->generateLeagueGames($clubs);
                $dataSource = new CompetitionDataSource();
                $competitionConfig = new CompetitionConfig();
                $roundLength = $countClubs / 2;
                $dataSource->storeLeagueGames($leagueGames, $competitionId, $competitionConfig->getStartDate(), $roundLength);
            }
        } catch (\Exception $exception) {
            dd('exit');
            // log exception
        }
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

        $groupSchedule = $tournament->createTournament($clubs);
        $schedule = $tournament->setTournamentFixtures($groupSchedule, $competitionId, $season->start_date);
        $dataSource->storeTournamentKnockoutSchedule($competitionId, $seasonId, $schedule);
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

        $dataSource->insertTournamentGroups($groups, $competitionId, $seasonId);
    }
}
