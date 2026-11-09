<?php

namespace App\Repositories;

use App\Models\Game;
use App\Models\GamePlayerStat;
use App\Models\Player;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

class PlayerPerformanceRepository
{
    private function appearances(Player $player, int $seasonId, string $date): Builder
    {
        return GamePlayerStat::query()->where('game_player_stats.instance_id', $player->instance_id)
            ->where('player_id', $player->id)->where('minutes_played', '>', 0)
            ->join('games', function (JoinClause $join): void {
                $join->on('games.id', '=', 'game_player_stats.game_id')
                    ->on('games.instance_id', '=', 'game_player_stats.instance_id');
            })
            ->where('games.season_id', $seasonId)->where('games.status', Game::STATUS_COMPLETED)
            ->whereDate('games.match_start', '<=', $date);
    }

    /** @return array{season_id: int, competitions: list<array<string, mixed>>} */
    public function summary(Player $player, int $seasonId, string $date): array
    {
        $competitions = $this->appearances($player, $seasonId, $date)
            ->join('competitions', function (JoinClause $join): void {
                $join->on('competitions.id', '=', 'games.competition_id')
                    ->on('competitions.instance_id', '=', 'games.instance_id');
            })
            ->select(['competitions.id', 'competitions.name'])
            ->selectRaw('COUNT(*) as appearances, SUM(minutes_played) as minutes, SUM(goals) as goals, SUM(assists) as assists, AVG(rating) as average_rating')
            ->groupBy('competitions.id', 'competitions.name')->orderBy('competitions.name')->orderBy('competitions.id')
            ->get()->map(fn ($row): array => [
                'id' => (int) $row->id, 'name' => $row->name,
                'appearances' => (int) $row->appearances, 'minutes' => (int) $row->minutes,
                'goals' => (int) $row->goals, 'assists' => (int) $row->assists,
                'average_rating' => round((float) $row->average_rating, 1),
            ])->all();

        return ['season_id' => $seasonId, 'competitions' => $competitions];
    }
}
