<?php

namespace App\Repositories;

use App\Models\Game;
use App\Models\Instance;
use App\Repositories\Interfaces\ICompetitionRepository;
use App\Services\CompetitionService\DataLayer\CompetitionDataSource;
use App\Services\GameService\GameService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CompetitionRepository extends CoreRepository implements ICompetitionRepository
{
    private CompetitionDataSource $competitionDataSource;

    public function __construct(CompetitionDataSource $competitionDataSource)
    {
        $this->competitionDataSource = $competitionDataSource;
    }

    public function competitionTable(int $competitionId): Collection
    {
        return DB::table('competition_season AS cs')
            ->select('clubs.name', 'cs.points')
            ->join('clubs', 'cs.club_id', '=', 'clubs.id')
            ->where('season_id', $this->seasonId)
            ->where('cs.instance_id', $this->instanceId)
            ->where('competition_id', $competitionId)
            ->orderBy('points', 'DESC')
            ->get();
    }

    public function tournamentGroupsTables(int $competitionId): Collection
    {
        return DB::table('tournament_groups AS tg')
            ->select('tg.group_id', 'tg.points', 'clubs.name')
            ->join('clubs', 'clubs.id', '=', 'tg.club_id')
            ->where('tg.competition_id', $competitionId)
            ->where('tg.instance_id', $this->instanceId)
            ->where('tg.season_id', $this->seasonId)
            ->orderBy('tg.group_id', 'ASC')
            ->orderBy('tg.points', 'DESC')
            ->get();
    }

    public function getCompetitionKnockoutStageSummary(int $competitionId): string
    {
        $result = DB::table('tournament_knockout AS tk')
            ->select('tk.summary')
            ->where('instance_id', $this->instanceId)
            ->where('season_id', $this->seasonId)
            ->first();

        return $result->summary ?? '';
    }

    public function setCompetitionsSeasons(int $instanceId, int $seasonId): void
    {
        $this->competitionDataSource->storeInitialCompetitionSeasonClubs($instanceId, $seasonId);
    }

    public function getScheduledGames(Instance $instance)
    {
        return Game::where('instance_id', $instance->id)
                   ->where('match_start', $instance->instance_date)
                   ->where('winner', null)
                   ->get();
    }

    public function updatePointsTable(array $game): void
    {
        $homeTeamPoints = 0;
        $awayTeamPoints = 0;

        switch ($game['winner']) {
            case 1:
                $homeTeamPoints += 3;
                break;
            case 2:
                $awayTeamPoints += 3;
                break;
            case 3:
                $homeTeamPoints += 1;
                $awayTeamPoints += 1;
                break;
        }

        DB::update(
            "
                UPDATE competition_season
                SET points = coalesce(points, 0) + :points
                WHERE club_id = :clubId
            ",
            [
                "points" => $homeTeamPoints,
                "clubId" => $game['hometeam_id'],
            ]
        );

        DB::update(
            "
                UPDATE competition_season
                SET points = coalesce(points, 0) + :points
                WHERE club_id = :clubId
            ",
            [
                "points" => $awayTeamPoints,
                "clubId" => $game['awayteam_id'],
            ]
        );
    }

    public function updateTournamentGroupsPoints(int $competitionId, int $homeTeamId, int $points)
    {
        DB::update(
            "
                UPDATE tournament_groups
                SET `points` = `points` + :points
                WHERE competition_id = :competitionId
                AND club_id = :teamId
            ",
            [
                "points"        => $points,
                "competitionId" => $competitionId,
                "teamId"        => $homeTeamId,
            ]
        );
    }

    /**
     * Checks if all the games from the group stage have been played
     *
     * @param array $match
     *
     * @return bool
     */
    public function tournamentGroupsFinished(array $match): bool
    {
        $result = DB::select(
            "
                SELECT
                    count(tg.id) AS numberOfGroups
                FROM
                    (
                        SELECT group_id AS groupIds FROM tournament_groups
                        WHERE (club_id = :homeTeamId OR club_id = :awayTeamId)
                        LIMIT 1
                    )AS sq
                JOIN tournament_groups AS tg ON (tg.group_id = sq.groupIds)
            ",
            [
                'homeTeamId' => $match['hometeam_id'],
                'awayTeamId' => $match['awayteam_id'],
            ]
        );

        $numberOfGames = $result[0]->numberOfGroups * 12;

        $gamesPlayed = DB::select(
            "
            SELECT COUNT(id) AS gamesPlayed
            FROM games
            WHERE competition_id = :competitionId
            AND winner > 0
            ",
            ["competitionId" => $match["competition_id"]]
        );

        if ($numberOfGames == $gamesPlayed[0]->gamesPlayed) {
            return true;
        }

        return false;
    }

    public function resetTournamentGroupRule(int $competitionId)
    {
        DB::update(
            "
                    UPDATE competitions
                    SET groups = 0
                    WHERE id = :competitionId
                ",
            ["competitionId" => $competitionId]
        );
    }

    public function topClubsByTournamentGroup(int $competitionId): array
    {
        return DB::select(
            "
                SELECT
                    t1.*
                FROM
                (
                    SELECT
                        id,
                        competition_id,
                        club_id,
                          points,
                        group_id,
                        @rn := IF(@prev = group_id, @rn + 1, 1) AS rn,
                        @prev := group_id
                    FROM tournament_groups
                    JOIN (SELECT @prev := NULL, @rn := 0) AS vars
                    ORDER BY group_id, points DESC
                ) AS t1
                WHERE rn <= 2
                AND competition_id = :competitionId;
            ",
            ["competitionId" => $competitionId]
        );
    }

    public function tournamentKnockoutStageByCompetitionId($instanceId, $seasonId, int $competitionId)
    {
        return DB::select(
            "
                SELECT * FROM tournament_knockout WHERE competition_id = :competitionId
            ",
            ['competitionId' => $competitionId]
        )[0];
    }

    public function updateKnockoutSummary(array $summary, int $tournamentStructureId)
    {
        try {
            DB::update(
                "
                UPDATE tournament_knockout SET summary = :summary WHERE id = :id
            ",
                ['summary' => json_encode($summary), 'id' => $tournamentStructureId]
            );
        } catch (\Exception $e) {

        }
    }

    public function finishedKnockoutMatches(int $competitionId): array
    {
        return DB::select(
            "SELECT * FROM games WHERE competition_id = :competitionId AND winner > 0",
            ["competitionId" => $competitionId]
        );
    }

    public function tournamentRoundWinner(int $matchId1, int $matchId2)
    {
        $match1 = Game::where('id', $matchId1)->first();
        $match2 = Game::where('id', $matchId2)->where('winner', '>', '0')->first();

        if (empty($match2)) {
            return false;
        }

        $team1 = new \stdClass();
        $team2 = new \stdClass();

        $team1->id     = $match1->hometeam_id;
        $team2->id     = $match1->awayteam_id;
        $team1->goals  = $match1->home_team_goals;
        $team2->goals  = $match1->away_team_goals;
        $team1->goals  += $match2->away_team_goals;
        $team2->goals  += $match2->home_team_goals;
        $team1->points = 0;
        $team2->points = 0;

        switch ($match1->winner) {
            case 1:
                $team1->points += 3;
                break;
            case 2:
                $team2->points += 3;
                break;
            case 3:
                $team1->points += 1;
                $team2->points += 1;
                break;
        }

        switch ($match2->winner) {
            case 1:
                $team2->points += 3;
                break;
            case 2:
                $team1->points += 3;
                break;
            case 3:
                $team1->points += 1;
                $team2->points += 1;
                break;
        }

        // same amount of points - checking goal difference or simulating extra time
        if ($team1->points == $team2->points) {
            if ($team1->goals == $team2->goals) {
                $matchService = new GameService();
                return $matchService->simulateMatchExtraTime($match2->id);
            } else {
                return $team1->goals > $team2->goals ? $team1->id : $team2->id;
            }
        }

        return $team1->points > $team2->points ? $team1->id : $team2->id;
    }
}
