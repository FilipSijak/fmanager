<?php

namespace App\Repositories;

use App\Models\ClubCompetitionProgression;
use App\Models\Game;
use App\Models\Instance;
use App\Models\Season;
use App\Repositories\Interfaces\ICompetitionRepository;
use App\Services\CompetitionService\DataLayer\CompetitionDataSource;
use App\Services\GameService\GameService;
use Carbon\Carbon;
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
        $knockout = DB::table('tournament_knockout')
            ->where('instance_id', $this->instanceId())
            ->where('season_id', $this->seasonId())
            ->where('competition_id', $competitionId)
            ->first();

        if (! $knockout) {
            return '';
        }

        $rounds = DB::table('tournament_knockout_rounds')
            ->where('tournament_knockout_id', $knockout->id)
            ->orderBy('round_number')
            ->get();
        $summary = [
            'id' => $knockout->id,
            'instance_id' => $knockout->instance_id,
            'season_id' => $knockout->season_id,
            'competition_id' => $knockout->competition_id,
            'participant_count' => $knockout->participant_count,
            'bracket_size' => $knockout->bracket_size,
            'status' => $knockout->status,
            'winner' => $knockout->winner_club_id,
            'finals_match' => null,
            'first_group' => ['num_rounds' => 0, 'rounds' => []],
            'second_group' => ['num_rounds' => 0, 'rounds' => []],
        ];

        foreach (['first' => 'first_group', 'second' => 'second_group'] as $side => $key) {
            $sideRounds = $rounds->where('bracket_side', $side);
            $summary[$key]['num_rounds'] = $sideRounds->count();
            foreach ($sideRounds as $round) {
                $summary[$key]['rounds'][$round->round_number] = [
                    'id' => $round->id,
                    'name' => $round->name,
                    'status' => $round->status,
                    'pairs' => $this->knockoutRoundPairs((int) $round->id),
                ];
            }
        }

        $finalRound = $rounds->firstWhere('bracket_side', 'final');
        if ($finalRound) {
            $finalTie = DB::table('tournament_knockout_ties')->where('round_id', $finalRound->id)->first();
            if ($finalTie) {
                $summary['finals_match'] = DB::table('games')
                    ->where('knockout_tie_id', $finalTie->id)
                    ->orderBy('leg_number')->value('id');
            }
        }

        return json_encode($summary, JSON_THROW_ON_ERROR);
    }

    private function knockoutRoundPairs(int $roundId): array
    {
        return DB::table('tournament_knockout_ties')
            ->where('round_id', $roundId)
            ->orderBy('position')
            ->get()
            ->map(function ($tie): array {
                $games = DB::table('games')->where('knockout_tie_id', $tie->id)->orderBy('leg_number')->get();
                $firstGame = $games->get(0);
                $secondGame = $games->get(1);

                return [
                    'id' => $tie->id,
                    'match1' => [
                        'homeTeamId' => $firstGame->hometeam_id ?? $tie->home_club_id,
                        'awayTeamId' => $firstGame->awayteam_id ?? $tie->away_club_id,
                    ],
                    'match2' => [
                        'homeTeamId' => $secondGame->hometeam_id ?? $tie->away_club_id,
                        'awayTeamId' => $secondGame->awayteam_id ?? $tie->home_club_id,
                    ],
                    'winner' => $tie->winner_club_id,
                    'status' => $tie->status,
                    'match1Id' => $firstGame->id ?? null,
                    'match2Id' => $secondGame->id ?? null,
                ];
            })->all();
    }

    public function setCompetitionsSeasons(int $instanceId, int $seasonId): void
    {
        $this->competitionDataSource->storeInitialCompetitionSeasonClubs($instanceId, $seasonId);
    }

    public function applyCompetitionProgressions(int $instanceId, int $sourceSeasonId): Season
    {
        return DB::transaction(function () use ($instanceId, $sourceSeasonId): Season {
            $sourceSeason = Season::query()
                ->where('instance_id', $instanceId)
                ->lockForUpdate()
                ->findOrFail($sourceSeasonId);

            $nextStartDate = Carbon::parse($sourceSeason->start_date)->addYear();
            $nextEndDate = Carbon::create($nextStartDate->year + 1, 6, 15);

            $nextSeason = Season::query()
                ->where('instance_id', $instanceId)
                ->whereDate('start_date', $nextStartDate)
                ->first();

            if (! $nextSeason) {
                $nextSeason = new Season;
                $nextSeason->instance_id = $instanceId;
                $nextSeason->start_date = $nextStartDate->toDateString();
                $nextSeason->end_date = $nextEndDate->toDateString();
                $nextSeason->save();
            }

            $domesticMemberships = DB::table('competition_season AS cs')
                ->join('competitions AS competition', 'competition.id', '=', 'cs.competition_id')
                ->where('cs.instance_id', $instanceId)
                ->where('cs.season_id', $sourceSeasonId)
                ->where('competition.instance_id', $instanceId)
                ->where('competition.competition_scope', 'domestic')
                ->select('cs.competition_id', 'cs.club_id')
                ->distinct()
                ->get();

            foreach ($domesticMemberships as $membership) {
                $this->storeCompetitionSeasonMembership(
                    $instanceId,
                    $nextSeason->id,
                    (int) $membership->competition_id,
                    (int) $membership->club_id
                );
            }

            $progressions = ClubCompetitionProgression::query()
                ->where('instance_id', $instanceId)
                ->where('source_season_id', $sourceSeasonId)
                ->whereIn('status', ['pending', 'applied'])
                ->lockForUpdate()
                ->get();

            foreach ($progressions as $progression) {
                if (in_array($progression->progression_type, ['promotion', 'relegation'], true)) {
                    DB::table('competition_season')
                        ->where('instance_id', $instanceId)
                        ->where('season_id', $nextSeason->id)
                        ->where('competition_id', $progression->source_competition_id)
                        ->where('club_id', $progression->club_id)
                        ->delete();
                }

                $this->storeCompetitionSeasonMembership(
                    $instanceId,
                    $nextSeason->id,
                    (int) $progression->target_competition_id,
                    (int) $progression->club_id
                );

                if ($progression->status === 'pending') {
                    $progression->forceFill([
                        'status' => 'applied',
                        'applied_at' => now(),
                    ])->save();
                }
            }

            return $nextSeason;
        });
    }

    private function storeCompetitionSeasonMembership(
        int $instanceId,
        int $seasonId,
        int $competitionId,
        int $clubId
    ): void {
        DB::table('competition_season')->updateOrInsert(
            [
                'instance_id' => $instanceId,
                'season_id' => $seasonId,
                'competition_id' => $competitionId,
                'club_id' => $clubId,
            ],
            [
                'group_id' => null,
                'points' => 0,
                'goals_for' => 0,
                'goals_against' => 0,
                'played' => 0,
                'wins' => 0,
                'draws' => 0,
                'losses' => 0,
            ]
        );
    }

    public function getScheduledGames(Instance $instance)
    {
        return Game::where('instance_id', $instance->id)
            ->whereDate('match_start', $instance->instance_date)
            ->whereNull('processed_at')
            ->whereIn('status', [Game::STATUS_SCHEDULED, Game::STATUS_POSTPONED])
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
            '
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
            ',
            [
                'points' => $homeTeamPoints,
                'wins' => $homeTeamWins,
                'draws' => $homeTeamDraws,
                'losses' => $homeTeamLosses,
                'goalsFor' => $game['home_team_goals'],
                'goalsAgainst' => $game['away_team_goals'],
                'clubId' => $game['hometeam_id'],
                'competitionId' => $game['competition_id'],
                'seasonId' => $game['season_id'],
                'instanceId' => $game['instance_id'],
            ]
        );

        DB::update(
            '
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
            ',
            [
                'points' => $awayTeamPoints,
                'wins' => $awayTeamWins,
                'draws' => $awayTeamDraws,
                'losses' => $awayTeamLosses,
                'goalsFor' => $game['away_team_goals'],
                'goalsAgainst' => $game['home_team_goals'],
                'clubId' => $game['awayteam_id'],
                'competitionId' => $game['competition_id'],
                'seasonId' => $game['season_id'],
                'instanceId' => $game['instance_id'],
            ]
        );
    }

    /**
     * Checks if all the games from the group stage have been played
     */
    public function tournamentGroupsFinished(array $match): bool
    {
        return ! DB::table('games')
            ->where('competition_id', $match['competition_id'])
            ->where('season_id', $match['season_id'])
            ->where('instance_id', $match['instance_id'])
            ->whereIn('status', [Game::STATUS_SCHEDULED, Game::STATUS_IN_PROGRESS, Game::STATUS_POSTPONED])
            ->exists();
    }

    public function resetTournamentGroupRule(int $competitionId): void
    {
        DB::table('competitions')
            ->where('id', $competitionId)
            ->update(['groups' => 0]);
    }

    public function topClubsByTournamentGroup(int $competitionId): array
    {
        return DB::select(
            '
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
            ',
            [
                'competitionId' => $competitionId,
                'seasonId' => $this->seasonId(),
                'instanceId' => $this->instanceId(),
            ]
        );
    }

    public function tournamentRoundWinner(int $matchId1, int $matchId2)
    {
        $match1 = Game::where('id', $matchId1)->first();
        $match2 = Game::where('id', $matchId2)->where('winner', '>', '0')->first();

        if (empty($match2)) {
            return false;
        }

        $team1 = new \stdClass;
        $team2 = new \stdClass;

        $team1->id = $match1->hometeam_id;
        $team2->id = $match1->awayteam_id;
        $team1->goals = $match1->home_team_goals;
        $team2->goals = $match1->away_team_goals;
        $team1->goals += $match2->away_team_goals;
        $team2->goals += $match2->home_team_goals;
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
                $matchService = new GameService;

                return $matchService->simulateMatchExtraTime($match2->id);
            } else {
                return $team1->goals > $team2->goals ? $team1->id : $team2->id;
            }
        }

        return $team1->points > $team2->points ? $team1->id : $team2->id;
    }
}
