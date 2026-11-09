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

    /** @return list<array<string, mixed>> */
    public function recentAppearances(Player $player, int $seasonId, string $date): array
    {
        return $this->appearances($player, $seasonId, $date)
            ->join('clubs as home', function (JoinClause $join): void {
                $join->on('home.id', '=', 'games.hometeam_id')->on('home.instance_id', '=', 'games.instance_id');
            })
            ->join('clubs as away', function (JoinClause $join): void {
                $join->on('away.id', '=', 'games.awayteam_id')->on('away.instance_id', '=', 'games.instance_id');
            })
            ->select(['game_player_stats.*', 'games.match_start', 'games.hometeam_id',
                'games.home_team_goals', 'games.away_team_goals', 'home.name as home_name', 'away.name as away_name'])
            ->orderByDesc('games.match_start')->orderByDesc('games.id')->limit(5)->get()
            ->map(fn (GamePlayerStat $stat): array => [
                'game_id' => (int) $stat->game_id, 'date' => substr($stat->match_start, 0, 10),
                'opponent' => (int) $stat->club_id === (int) $stat->hometeam_id ? $stat->away_name : $stat->home_name,
                'venue' => (int) $stat->club_id === (int) $stat->hometeam_id ? 'Home' : 'Away',
                'home_goals' => $stat->home_team_goals === null ? null : (int) $stat->home_team_goals,
                'away_goals' => $stat->away_team_goals === null ? null : (int) $stat->away_team_goals,
                'minutes' => (int) $stat->minutes_played, 'goals' => (int) $stat->goals,
                'assists' => (int) $stat->assists, 'rating' => (float) $stat->rating,
            ])->all();
    }

    /** @return array{season_id: int, competitions: list<array<string, mixed>>, recent: list<array<string, mixed>>} */
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

        return ['season_id' => $seasonId, 'competitions' => $competitions,
            'recent' => $this->recentAppearances($player, $seasonId, $date)];
    }
}
