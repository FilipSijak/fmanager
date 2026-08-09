<?php

namespace App\Services\CompetitionService\DataLayer;

use App\Models\Club;
use Illuminate\Support\Facades\DB;

class CompetitionDataSource
{
    public function storeLeagueScheduleFixtures(array $fixtures, int $competitionId, int $seasonId, int $instanceId): void
    {
        $this->insertGameFixtures($fixtures, $competitionId, $seasonId, $instanceId);
    }

    public function storeTournamentGroupScheduleFixtures(array $fixtures, int $competitionId, int $seasonId, int $instanceId): void
    {
        $this->insertGameFixtures($fixtures, $competitionId, $seasonId, $instanceId);
    }

    private function insertGameFixtures(array $fixtures, int $competitionId, int $seasonId, int $instanceId): void
    {
        $clubStadiums = Club::query()
            ->where('instance_id', $instanceId)
            ->whereIn('id', collect($fixtures)->pluck('home_club_id')->unique()->all())
            ->pluck('stadium_id', 'id');

        $rows = [];

        foreach ($fixtures as $fixture) {
            $homeClubId = (int) $fixture['home_club_id'];

            if (!isset($clubStadiums[$homeClubId])) {
                throw new \UnexpectedValueException(
                    "Unable to schedule fixture: home club ".$homeClubId." has no stadium for instance ".$instanceId."."
                );
            }

            $rows[] = [
                'instance_id' => $instanceId,
                'season_id' => $seasonId,
                'competition_id' => $competitionId,
                'hometeam_id' => $homeClubId,
                'awayteam_id' => (int) $fixture['away_club_id'],
                'stadium_id' => (int) $clubStadiums[$homeClubId],
                'match_start' => $fixture['date']->format('Y-m-d H:i:s'),
            ];
        }

        DB::table('games')->insert($rows);
    }

    public function storeInitialCompetitionSeasonClubs(int $instanceId, int $seasonId): void
    {
        $rows = DB::table('base_clubs AS bc')
            ->join('clubs AS c', function ($join) use ($instanceId) {
                $join->on('c.base_club_id', '=', 'bc.id')
                    ->where('c.instance_id', '=', $instanceId);
            })
            ->join('competitions AS comp', function ($join) use ($instanceId) {
                $join->on('comp.base_competition_id', '=', 'bc.competition_id')
                    ->where('comp.instance_id', '=', $instanceId);
            })
            ->select([
                DB::raw((int) $instanceId.' AS instance_id'),
                'comp.id AS competition_id',
                DB::raw((int) $seasonId.' AS season_id'),
                'c.id AS club_id',
                DB::raw('0 AS points'),
            ])
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        DB::table('competition_season')->insert($rows);
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

    public function insertTournamentGroups(int $instanceId, array $groups, int $competitionId, int $seasonId): void
    {
        $rows = [];

        foreach ($groups as $groupId => $group) {
            foreach ($group as $clubId) {
                $rows[] = [
                    'instance_id' => $instanceId,
                    'competition_id' => $competitionId,
                    'season_id' => $seasonId,
                    'group_id' => (int) $groupId,
                    'club_id' => (int) $clubId,
                ];
            }
        }

        DB::table('competition_season')->insert($rows);
    }
}
