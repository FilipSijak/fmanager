<?php

namespace App\Repositories;

use App\Models\Club;
use App\Models\ClubDemandProfile;
use App\Models\Country;
use App\Models\Game;
use App\Models\Instance;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class TicketDemandRepository
{
    private const int RECENT_MATCH_LIMIT = 10;

    public function transaction(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }

    /** @return LazyCollection<int, Club> */
    public function clubs(Instance $instance): LazyCollection
    {
        return Club::query()->forInstance($instance->id)->lazyById(200);
    }

    public function lockClub(Club $club): Club
    {
        return Club::query()->forInstance($club->instance_id)->lockForUpdate()->findOrFail($club->id);
    }

    public function profile(Club $club, CarbonInterface $periodStart): ?ClubDemandProfile
    {
        return ClubDemandProfile::query()->forClub($club)->where('period_start', $periodStart->toDateString())->first();
    }

    /** @param array{club_rank: int, country_rank: int, performance_score: float, ticket_price_sensitivity: float, commercial_price_sensitivity: float} $values */
    public function recordProfile(Club $club, CarbonInterface $periodStart, array $values): ClubDemandProfile
    {
        return ClubDemandProfile::query()->create($values + [
            'instance_id' => $club->instance_id,
            'club_id' => $club->id,
            'period_start' => $periodStart->toDateString(),
        ])->refresh();
    }

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
