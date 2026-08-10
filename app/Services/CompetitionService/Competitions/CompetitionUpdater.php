<?php

namespace App\Services\CompetitionService\Competitions;

use App\Models\Competition;
use App\Models\Season;
use Illuminate\Support\Facades\DB;

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

                $groupsActive = DB::table('competition_season')
                    ->where('instance_id', $instanceId)
                    ->where('season_id', $season->id)
                    ->where('competition_id', $competitionId)
                    ->where('groups_active', true)
                    ->exists();

                if ($isKnockoutPhase || ! $groupsActive) {
                    $this->tournamentUpdater->updateTournamentSummary($games);
                } else {
                    $this->tournamentUpdater->updatePointsTable($games);
                }
            }
        }
    }
}
