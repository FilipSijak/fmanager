<?php

namespace App\Services\CompetitionService\DataLayer;

use App\Models\BaseData\BaseClubs;
use App\Models\Club;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CompetitionDataSource
{
    public function storeLeagueGames($leagueFixtures, $competitionId, $seasonId, $instanceId, $startDate, $roundLength): void
    {
        $countRound   = $roundLength;
        $insertString = "INSERT INTO games(instance_id, season_id, competition_id, hometeam_id, awayteam_id, stadium_id, match_start) VALUES";

        foreach ($leagueFixtures as $fixture) {
            $nextWeek   = $countRound % $roundLength == 0;
            $matchStart = $nextWeek ? $startDate->addWeek() : $startDate;

            $insertString .= "(" . $instanceId . "," . $seasonId . "," . $competitionId . ",". $fixture->homeTeamId . "," . $fixture->awayTeamId . "," . Club::where('id', $fixture->homeTeamId)->first()->stadium_id . ",'" . $matchStart->format("Y-m-d H:i:s") . "'), ";

            $countRound++;
        }

        $insert = substr($insertString, 0, -2);

        DB::statement($insert);
    }

    public function storeInitialCompetitionSeasonClubs(int $instanceId, int $seasonId)
    {
        $baseClubs = BaseClubs::all();
        $insertString = "INSERT INTO competition_season(instance_id, competition_id, season_id, club_id, points) VALUES";

        foreach ($baseClubs as $club) {
            $insertString .= "(". $instanceId ."," . $club->competition_id . "," . $seasonId . "," . $club->id . ", 0), ";
        }

        $insert = substr($insertString, 0, -2);

        DB::statement($insert);
    }

    public function storeTournamentKnockoutSchedule(int $instanceId, int $competitionId, int $seasonId, array $summary)
    {
        DB::insert(
            "
                INSERT INTO tournament_knockout (instance_id, competition_id, season_id, summary)
                VALUES (:instanceId, :competitionId, :seasonId, :summary)
            ",
            [
                'instanceId'    => $instanceId,
                'competitionId' => $competitionId,
                'seasonId'      => $seasonId,
                'summary'       => json_encode($summary),
            ]
        );
    }

    public function insertTournamentGroups($instanceId, $groups, $competitionId, $seasonId)
    {
        $insertString = "INSERT INTO tournament_groups(instance_id, competition_id, season_id, group_id, club_id, points) VALUES";

        foreach ($groups as $groupId => $group) {
            foreach ($group as $clubId)
            $insertString .= "(" . $instanceId . "," . $competitionId . "," . $seasonId . "," . $groupId . ",". $clubId . "," . 0 . "), ";
        }

        $insert = substr($insertString, 0, -2);

        DB::statement($insert);
    }
}
