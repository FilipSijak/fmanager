<?php

namespace App\Repositories;

use App\Models\Club;
use App\Models\Country;
use App\Models\Game;
use Carbon\CarbonInterface;

class TicketDemandRepository
{
    private const int RECENT_MATCH_LIMIT = 5;

    /** @return array{country_rank: int, results: list<array{home_club_id: int, home_goals: int, away_goals: int}>} */
    public function inputs(Club $club, CarbonInterface $before): array
    {
        $results = Game::query()->forInstance($club->instance_id)->forClub($club->id)
            ->where('status', Game::STATUS_COMPLETED)
            ->whereNotNull('home_team_goals')->whereNotNull('away_team_goals')
            ->where('match_start', '<', $before->format('Y-m-d H:i:s'))
            ->orderByDesc('match_start')->orderByDesc('id')->limit(self::RECENT_MATCH_LIMIT)
            ->get(['hometeam_id', 'home_team_goals', 'away_team_goals'])
            ->map(fn (Game $game): array => [
                'home_club_id' => (int) $game->hometeam_id,
                'home_goals' => (int) $game->home_team_goals,
                'away_goals' => (int) $game->away_team_goals,
            ])->all();

        return [
            'country_rank' => (int) Country::query()->where('code', $club->country_code)->value('ranking'),
            'results' => $results,
        ];
    }
}
