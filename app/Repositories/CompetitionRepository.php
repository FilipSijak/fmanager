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

    public function clubIdsForCompetitionSeason(int $competitionId, int $seasonId, int $instanceId): array
    {
        return DB::table('competition_season AS cs')
            ->join('clubs', 'clubs.id', '=', 'cs.club_id')
            ->where('cs.competition_id', $competitionId)
            ->where('cs.season_id', $seasonId)
            ->where('cs.instance_id', $instanceId)
            ->where('clubs.instance_id', $instanceId)
            ->orderBy('cs.id')
            ->pluck('cs.club_id')
            ->map(fn ($clubId) => (int) $clubId)
            ->all();
    }

    public function competitionTable(int $competitionId): Collection
    {
        return DB::table('competition_season AS cs')
            ->select('clubs.id as club_id', 'clubs.name as club_name', 'cs.points', 'cs.goals_for', 'cs.goals_against', 'cs.wins', 'cs.draws', 'cs.losses', 'cs.played')
            ->join('clubs', 'cs.club_id', '=', 'clubs.id')
            ->where('season_id', $this->seasonId())
            ->where('cs.instance_id', $this->instanceId())
            ->where('competition_id', $competitionId)
            ->whereNull('cs.group_id')
            ->orderBy('cs.points', 'DESC')
            ->orderByRaw('(cs.goals_for - cs.goals_against) DESC')
            ->orderBy('cs.goals_for', 'DESC')
            ->orderBy('clubs.name', 'ASC')
            ->get();
    }

    public function tournamentGroupsTables(int $competitionId): Collection
    {
        return DB::table('competition_season AS tg')
            ->select('tg.group_id', 'clubs.id as club_id', 'clubs.name as club_name', 'tg.points', 'tg.goals_for', 'tg.goals_against', 'tg.wins', 'tg.draws', 'tg.losses', 'tg.played')
            ->join('clubs', 'clubs.id', '=', 'tg.club_id')
            ->where('tg.competition_id', $competitionId)
            ->where('tg.instance_id', $this->instanceId())
            ->where('tg.season_id', $this->seasonId())
            ->whereNotNull('tg.group_id')
            ->orderBy('tg.group_id', 'ASC')
            ->orderBy('tg.points', 'DESC')
            ->orderByRaw('(tg.goals_for - tg.goals_against) DESC')
            ->orderBy('tg.goals_for', 'DESC')
            ->orderBy('clubs.name', 'ASC')
            ->get();
    }

    public function getCompetitionKnockoutStageSummary(int $competitionId): string
    {
        $result = DB::table('tournament_knockout AS tk')
            ->select('tk.summary')
            ->where('instance_id', $this->instanceId())
            ->where('season_id', $this->seasonId())
            ->where('competition_id', $competitionId)
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

    public function updateCompetitionTable(array $game): void
    {
        $homeTeamPoints = 0;
        $awayTeamPoints = 0;
        $homeTeamWins = 0;
        $awayTeamWins = 0;
        $homeTeamDraws = 0;
        $awayTeamDraws = 0;
        $homeTeamLosses = 0;
        $awayTeamLosses = 0;

        switch ($game['winner']) {
            case 1:
                $homeTeamPoints = 3;
                $homeTeamWins = 1;
                $awayTeamLosses = 1;
                break;
            case 2:
                $awayTeamPoints = 3;
                $awayTeamWins = 1;
                $homeTeamLosses = 1;
                break;
            case 3:
                $homeTeamPoints = 1;
                $awayTeamPoints = 1;
                $homeTeamDraws = 1;
                $awayTeamDraws = 1;
                break;
        }

        DB::update(
            "
                UPDATE competition_season
                SET points = coalesce(points, 0) + :points,
                    played = played + 1,
                    wins = wins + :wins,
                    draws = draws + :draws,
                    losses = losses + :losses,
                    goals_for = goals_for + :goalsFor,
                    goals_against = goals_against + :goalsAgainst
                WHERE club_id = :clubId
                AND competition_id = :competitionId
                AND season_id = :seasonId
                AND instance_id = :instanceId
            ",
            [
                "points" => $homeTeamPoints,
                "wins" => $homeTeamWins,
                "draws" => $homeTeamDraws,
                "losses" => $homeTeamLosses,
                "goalsFor" => $game['home_team_goals'],
                "goalsAgainst" => $game['away_team_goals'],
                "clubId" => $game['hometeam_id'],
                "competitionId" => $game['competition_id'],
                "seasonId" => $game['season_id'],
                "instanceId" => $game['instance_id'],
            ]
        );

        DB::update(
            "
                UPDATE competition_season
                SET points = coalesce(points, 0) + :points,
                    played = played + 1,
                    wins = wins + :wins,
                    draws = draws + :draws,
                    losses = losses + :losses,
                    goals_for = goals_for + :goalsFor,
                    goals_against = goals_against + :goalsAgainst
                WHERE club_id = :clubId
                AND competition_id = :competitionId
                AND season_id = :seasonId
                AND instance_id = :instanceId
            ",
            [
                "points" => $awayTeamPoints,
                "wins" => $awayTeamWins,
                "draws" => $awayTeamDraws,
                "losses" => $awayTeamLosses,
                "goalsFor" => $game['away_team_goals'],
                "goalsAgainst" => $game['home_team_goals'],
                "clubId" => $game['awayteam_id'],
                "competitionId" => $game['competition_id'],
                "seasonId" => $game['season_id'],
                "instanceId" => $game['instance_id'],
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
        return !DB::table('games')
            ->where('competition_id', $match['competition_id'])
            ->where('season_id', $match['season_id'])
            ->where('instance_id', $match['instance_id'])
            ->whereNull('winner')
            ->exists();
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
                SELECT ranked.*
                FROM (
                    SELECT
                        id,
                        competition_id,
                        club_id,
                        points,
                        group_id,
                        ROW_NUMBER() OVER (
                            PARTITION BY group_id
                            ORDER BY points DESC,
                                (goals_for - goals_against) DESC,
                                goals_for DESC,
                                club_id ASC
                        ) AS position
                    FROM competition_season
                    WHERE competition_id = :competitionId
                    AND season_id = :seasonId
                    AND instance_id = :instanceId
                    AND group_id IS NOT NULL
                ) AS ranked
                WHERE ranked.position <= 2
                ORDER BY ranked.group_id, ranked.position
            ",
            [
                'competitionId' => $competitionId,
                'seasonId' => $this->seasonId(),
                'instanceId' => $this->instanceId(),
            ]
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
