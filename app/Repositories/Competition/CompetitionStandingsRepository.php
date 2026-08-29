<?php

namespace App\Repositories\Competition;

use App\Support\GameContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

final class CompetitionStandingsRepository
{
    public function __construct(private readonly GameContext $gameContext) {}

    public function table(int $competitionId): Collection
    {
        return DB::table('competition_season AS cs')
            ->select('clubs.id as club_id', 'clubs.name as club_name', 'cs.points', 'cs.goals_for', 'cs.goals_against', 'cs.wins', 'cs.draws', 'cs.losses', 'cs.played')
            ->join('clubs', 'cs.club_id', '=', 'clubs.id')
            ->where('cs.season_id', $this->gameContext->seasonId())
            ->where('cs.instance_id', $this->gameContext->instanceId())
            ->where('clubs.instance_id', $this->gameContext->instanceId())
            ->where('competition_id', $competitionId)->whereNull('cs.group_id')
            ->orderByDesc('cs.points')->orderByRaw('(cs.goals_for - cs.goals_against) DESC')
            ->orderByDesc('cs.goals_for')->orderBy('clubs.name')->get();
    }

    public function groupTables(int $competitionId): Collection
    {
        return DB::table('competition_season AS cs')
            ->select('cs.group_id', 'clubs.id as club_id', 'clubs.name as club_name', 'cs.points', 'cs.goals_for', 'cs.goals_against', 'cs.wins', 'cs.draws', 'cs.losses', 'cs.played')
            ->join('clubs', 'clubs.id', '=', 'cs.club_id')
            ->where('cs.competition_id', $competitionId)
            ->where('cs.instance_id', $this->gameContext->instanceId())
            ->where('cs.season_id', $this->gameContext->seasonId())
            ->where('clubs.instance_id', $this->gameContext->instanceId())
            ->whereNotNull('cs.group_id')->orderBy('cs.group_id')->orderByDesc('cs.points')
            ->orderByRaw('(cs.goals_for - cs.goals_against) DESC')->orderByDesc('cs.goals_for')
            ->orderBy('clubs.name')->get();
    }

    public function update(array $game): void
    {
        $home = $this->result($game, true);
        $away = $this->result($game, false);
        DB::transaction(function () use ($home, $away): void {
            $this->apply($home);
            $this->apply($away);
        });
    }

    public function topClubsByGroup(int $competitionId): array
    {
        return DB::select(
            'SELECT ranked.* FROM (
                SELECT id, competition_id, club_id, points, group_id,
                    ROW_NUMBER() OVER (PARTITION BY group_id ORDER BY points DESC,
                        (goals_for - goals_against) DESC, goals_for DESC, club_id ASC) AS position
                FROM competition_season
                WHERE competition_id = :competitionId AND season_id = :seasonId
                    AND instance_id = :instanceId AND group_id IS NOT NULL
            ) AS ranked WHERE ranked.position <= 2 ORDER BY ranked.group_id, ranked.position',
            ['competitionId' => $competitionId, 'seasonId' => $this->gameContext->seasonId(),
                'instanceId' => $this->gameContext->instanceId()]
        );
    }

    /** @return array<string, int> */
    private function result(array $game, bool $home): array
    {
        $winner = (int) $game['winner'];
        $won = ($home && $winner === 1) || (! $home && $winner === 2);
        $drawn = $winner === 3;

        return [
            'points' => $won ? 3 : ($drawn ? 1 : 0), 'wins' => $won ? 1 : 0,
            'draws' => $drawn ? 1 : 0, 'losses' => ! $won && ! $drawn ? 1 : 0,
            'goalsFor' => (int) $game[$home ? 'home_team_goals' : 'away_team_goals'],
            'goalsAgainst' => (int) $game[$home ? 'away_team_goals' : 'home_team_goals'],
            'clubId' => (int) $game[$home ? 'hometeam_id' : 'awayteam_id'],
            'competitionId' => (int) $game['competition_id'], 'seasonId' => (int) $game['season_id'],
            'instanceId' => (int) $game['instance_id'],
        ];
    }

    /** @param array<string, int> $result */
    private function apply(array $result): void
    {
        $updated = DB::update(
            'UPDATE competition_season SET points = coalesce(points, 0) + :points,
                played = played + 1, wins = wins + :wins, draws = draws + :draws,
                losses = losses + :losses, goals_for = goals_for + :goalsFor,
                goals_against = goals_against + :goalsAgainst
            WHERE club_id = :clubId AND competition_id = :competitionId
                AND season_id = :seasonId AND instance_id = :instanceId',
            $result
        );

        if ($updated !== 1) {
            throw new LogicException('Unable to update exactly one competition standings row.');
        }
    }
}
