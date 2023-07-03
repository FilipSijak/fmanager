<?php

namespace App\Services\CompetitionService\Competitions;

use App\Models\Competition;
use App\Models\Season;

class CompetitionUpdater
{
    private array $gamesByCompetition;

    public function __construct(LeagueUpdater $leagueUpdater, TournamentUpdater $tournamentUpdater)
    {
        $this->leagueUpdater = $leagueUpdater;
        $this->tournamentUpdater = $tournamentUpdater;
    }

    public function setGamesByCompetition(array $gamesByCompetition)
    {
        $this->gamesByCompetition = $gamesByCompetition;
    }

    public function distributeGamesForCompetitionUpdates(Season $season, int $instanceId)
    {
        foreach ($this->gamesByCompetition as $competitionId => $games) {
            $competition = Competition::find($competitionId);
            $this->tournamentUpdater->setInstanceId($instanceId);
            $this->tournamentUpdater->setSeason($season);
            $this->leagueUpdater->setInstanceId($instanceId);
            $this->leagueUpdater->setSeason($season);

            if ($competition->type == 'league') {
                $this->leagueUpdater->updatePointsTable($games);
            } elseif ($competition->type == 'tournament') {
                if ($competition->groups) {
                    $this->tournamentUpdater->updatePointsTable($games);
                } else {
                    // need to see how do I get tournament summary
                    //$knockoutGames = json_decode(json_encode($games), true);

                    $this->tournamentUpdater->updateTournamentSummary($games);
                }
            }
        }
    }
}
