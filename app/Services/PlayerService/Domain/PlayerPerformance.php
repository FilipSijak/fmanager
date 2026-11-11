<?php

namespace App\Services\PlayerService\Domain;

use App\Models\GamePlayerStat;
use App\Models\PlayerInjury;
use App\Models\Season;
use Illuminate\Support\Collection;
use stdClass;

class PlayerPerformance
{
    /**
     * @param  Collection<int, Season>  $seasons
     * @return list<array{id: int, start_date: string, end_date: string}>
     */
    public function seasons(Collection $seasons): array
    {
        return $seasons
            ->map(fn (Season $season): array => [
                'id' => (int) $season->id, 'start_date' => $season->start_date, 'end_date' => $season->end_date,
            ])->all();
    }

    /**
     * @param  Collection<int, PlayerInjury>  $injuries
     * @return array{condition: ?int, morale: ?int, is_injured: bool, injuries: list<array{name: string, starts_at: string, ends_at: string}>}
     */
    public function fitness(?stdClass $progress, Collection $injuries): array
    {
        $injuries = $injuries
            ->map(fn (PlayerInjury $injury): array => [
                'name' => $injury->type, 'starts_at' => $injury->injury_start_date, 'ends_at' => $injury->injury_end_date,
            ])->all();

        return ['condition' => $progress === null ? null : (int) $progress->condition,
            'morale' => $progress === null ? null : (int) $progress->morale,
            'is_injured' => $injuries !== [], 'injuries' => $injuries];
    }

    /**
     * @param  Collection<int, stdClass>  $competitionStats
     * @param  Collection<int, GamePlayerStat>  $recentAppearances
     * @return array{season_id: ?int, competitions: list<array<string, mixed>>, recent: list<array<string, mixed>>}
     */
    public function summary(?int $seasonId, Collection $competitionStats, Collection $recentAppearances): array
    {
        $competitions = $competitionStats
            ->map(fn (stdClass $row): array => [
                'id' => (int) $row->id, 'name' => $row->name,
                'appearances' => (int) $row->appearances, 'minutes' => (int) $row->minutes,
                'goals' => (int) $row->goals, 'assists' => (int) $row->assists,
                'average_rating' => round((float) $row->average_rating, 1),
            ])->all();

        return ['season_id' => $seasonId, 'competitions' => $competitions,
            'recent' => $this->recentAppearances($recentAppearances)];
    }

    /**
     * @param  Collection<int, GamePlayerStat>  $appearances
     * @return list<array<string, mixed>>
     */
    private function recentAppearances(Collection $appearances): array
    {
        return $appearances
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
}
