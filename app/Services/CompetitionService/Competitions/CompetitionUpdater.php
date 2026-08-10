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
                $isKnockoutPhase = collect($games)->contains(
                    static fn (array $game): bool => ! empty($game['knockout_tie_id'])
                );

                if ($isKnockoutPhase || (int) $competition->groups === 0) {
                    $this->tournamentUpdater->updateTournamentSummary($games);
                } else {
                    $this->tournamentUpdater->updatePointsTable($games);
                }
            }
        }
    }
}
