<?php

namespace App\Services\CompetitionService\DataLayer;

use App\Models\BaseData\BaseClubs;
use App\Models\Club;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CompetitionDataSource
{
    public function storeLeagueGames($leagueFixtures, $competitionId, $startDate, $roundLength): void
    {
        $countRound   = $roundLength;
        $insertString = "INSERT INTO games(competition_id, hometeam_id, awayteam_id, stadium_id, match_start) VALUES";

        foreach ($leagueFixtures as $fixture) {
            $nextWeek   = $countRound % $roundLength == 0;
            $matchStart = $nextWeek ? $startDate->addWeek() : $startDate;

            $insertString .= "(" . $competitionId . "," . $fixture->homeTeamId . "," . $fixture->awayTeamId . "," . Club::where('id', $fixture->homeTeamId)->first()->stadium_id . ",'" . $matchStart->format("Y-m-d H:i:s") . "'), ";

            $countRound++;
        }

        $insert = substr($insertString, 0, -2);

        DB::statement($insert);
    }

    public function storeInitialCompetitionSeasonClubs(int $seasonId)
    {
        $baseClubs = BaseClubs::all();
        $insertString = "INSERT INTO competition_season(competition_id, season_id, club_id) VALUES";

        foreach ($baseClubs as $club) {
            $insertString .= "(" . $club->competition_id . "," . $seasonId . "," . $club->id . "), ";
        }

        $insert = substr($insertString, 0, -2);

        DB::statement($insert);
    }

    public function storeCompetitionPoints(int $seasonId, Collection $clubs, int $competitionId)
    {
        $insertString = "INSERT INTO competition_points(competition_id, season_id, club_id, points) VALUES";

        foreach ($clubs as $club) {
            $insertString .= "(" . $competitionId . "," . $seasonId . "," . $club->id . "," . 0 . "), ";
        }

        $insert = substr($insertString, 0, -2);

        DB::statement($insert);
    }

    public function storeTournamentKnockoutSchedule(int $competitionId, int $seasonId, array $summary)
    {
        DB::insert(
            "
                INSERT INTO tournament_knockout (competition_id, season_id, summary)
                VALUES (:competitionId, :seasonId, :summary)
            ",
            [
                'competitionId' => $competitionId,
                'seasonId'      => $seasonId,
                'summary'       => json_encode($summary),
            ]
        );
    }

    public function insertTournamentGroups($groups, $competitionId, $seasonId)
    {
        $insertString = "INSERT INTO tournament_groups(competition_id, season_id, group_id, club_id, points) VALUES";

        foreach ($groups as $groupId => $group) {
            foreach ($group as $clubId)
            $insertString .= "(" . $competitionId . "," . $seasonId . "," . $groupId . ",". $clubId . "," . 0 . "), ";
        }

        $insert = substr($insertString, 0, -2);

        DB::statement($insert);
    }
}
