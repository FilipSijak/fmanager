<?php

namespace App\Repositories\Competition;

use App\Models\Competition;
use App\Support\GameContext;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CompetitionReadRepository
{
    public function __construct(
        private readonly CompetitionStandingsRepository $standings,
        private readonly CompetitionTournamentRepository $tournaments,
        private readonly GameContext $gameContext,
    ) {}

    public function table(int $competitionId, ?int $seasonId = null): Collection
    {
        return $this->standings->table($competitionId, $seasonId);
    }

    public function groupTables(int $competitionId, ?int $seasonId = null): Collection
    {
        return $this->standings->groupTables($competitionId, $seasonId);
    }

    public function knockoutSummary(int $competitionId): string
    {
        return $this->tournaments->knockoutSummary($competitionId);
    }

    /** @return Collection<int, Competition> */
    public function competitions(): Collection
    {
        return Competition::query()->forInstance($this->gameContext->instanceId())
            ->orderBy('country_code')->orderBy('rank')->orderBy('name')
            ->get(['id', 'name', 'type', 'country_code', 'competition_scope', 'groups']);
    }

    public function matches(int $competitionId, ?int $seasonId = null): Collection
    {
        return DB::table('games AS g')
            ->join('clubs AS home', 'home.id', '=', 'g.hometeam_id')
            ->join('clubs AS away', 'away.id', '=', 'g.awayteam_id')
            ->leftJoin('competition_season AS membership', function (JoinClause $join): void {
                $join->on('membership.club_id', '=', 'g.hometeam_id')
                    ->on('membership.competition_id', '=', 'g.competition_id')
                    ->on('membership.season_id', '=', 'g.season_id')
                    ->on('membership.instance_id', '=', 'g.instance_id');
            })
            ->where('g.instance_id', $this->gameContext->instanceId())
            ->where('g.season_id', $seasonId ?? $this->gameContext->seasonId())
            ->where('g.competition_id', $competitionId)
            ->where('home.instance_id', $this->gameContext->instanceId())
            ->where('away.instance_id', $this->gameContext->instanceId())
            ->orderBy('g.match_start')->orderBy('g.id')
            ->get(['g.id', 'g.match_start', 'g.status', 'g.hometeam_id', 'g.awayteam_id',
                'home.name AS home_team', 'away.name AS away_team', 'g.home_team_goals',
                'g.away_team_goals', 'g.knockout_tie_id', 'g.leg_number', 'membership.group_id'])
            ->map(function ($game): object {
                if ($game->knockout_tie_id !== null) {
                    $game->group_id = null;
                }

                return $game;
            });
    }

    public function knockoutRounds(int $competitionId, ?int $seasonId = null): Collection
    {
        $rounds = DB::table('tournament_knockout_rounds AS rounds')
            ->join('tournament_knockout AS knockout', 'knockout.id', '=', 'rounds.tournament_knockout_id')
            ->where('knockout.instance_id', $this->gameContext->instanceId())
            ->where('knockout.season_id', $seasonId ?? $this->gameContext->seasonId())
            ->where('knockout.competition_id', $competitionId)
            ->orderBy('rounds.round_number')->orderBy('rounds.bracket_side')
            ->get(['rounds.id', 'rounds.round_number', 'rounds.bracket_side', 'rounds.name',
                'rounds.number_of_legs', 'rounds.status']);

        $clubs = DB::table('clubs')->where('instance_id', $this->gameContext->instanceId());
        $ties = DB::table('tournament_knockout_ties AS ties')
            ->leftJoinSub($clubs, 'home', 'home.id', '=', 'ties.home_club_id')
            ->leftJoinSub($clubs, 'away', 'away.id', '=', 'ties.away_club_id')
            ->whereIn('ties.round_id', $rounds->pluck('id'))
            ->orderBy('ties.position')
            ->get(['ties.id', 'ties.round_id', 'ties.home_club_id', 'ties.away_club_id',
                'home.name AS home_team', 'away.name AS away_team', 'ties.winner_club_id', 'ties.status'])
            ->groupBy('round_id');

        return $rounds->map(function ($round) use ($ties): object {
            $round->ties = $ties->get($round->id, collect())->values();

            return $round;
        });
    }
}
