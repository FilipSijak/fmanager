<?php

namespace App\Repositories;

use App\Models\Account;
use App\Models\Club;
use App\Models\Competition;
use App\Models\FinanceTransactions;
use App\Models\Game;
use App\Models\Instance;
use App\Models\Season;
use App\Models\SeasonTicketSale;
use App\Models\Stadium;
use App\Models\StadiumStand;
use App\StadiumStandStatus;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class SeasonTicketRepository
{
    public function transaction(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }

    public function season(int $instanceId, int $seasonId): Season
    {
        return Season::query()->forInstance($instanceId)->findOrFail($seasonId);
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

    public function sale(Club $club, Season $season): ?SeasonTicketSale
    {
        return SeasonTicketSale::query()->forClub($club)->where('season_id', $season->id)->first();
    }

    public function remainingHomeGames(Club $club, Season $season, string $date, ?int $currentGameId): int
    {
        return Game::query()->forInstance($club->instance_id)->where('season_id', $season->id)->where('hometeam_id', $club->id)
            ->whereDate('match_start', '>=', $date)
            ->whereIn('competition_id', Competition::query()->forInstance($club->instance_id)->domesticLeague()->select('id'))
            ->whereDoesntHave('matchdaySettlement')
            ->where(function (Builder $query) use ($currentGameId): void {
                $query->whereIn('status', [Game::STATUS_SCHEDULED, Game::STATUS_POSTPONED, Game::STATUS_IN_PROGRESS]);
                if ($currentGameId !== null) {
                    $query->orWhere('games.id', $currentGameId);
                }
            })->count();
    }

    public function stadium(Club $club): Stadium
    {
        return Stadium::query()->forInstance($club->instance_id)->findOrFail($club->stadium_id);
    }

    /** @return list<array{capacity: int, available: bool}> */
    public function stands(Stadium $stadium): array
    {
        return $stadium->stands()->get()->map(fn (StadiumStand $stand): array => [
            'capacity' => (int) $stand->capacity, 'available' => $stand->status === StadiumStandStatus::ACTIVE,
        ])->all();
    }

    public function accountForUpdate(Club $club): Account
    {
        return Account::query()->where('club_id', $club->id)->lockForUpdate()->firstOrFail();
    }

    /** @param array{capacity: int, quantity: int, home_games: int, league_ticket_price: int, ticket_price: int, amount: int} $offer */
    public function recordSale(Club $club, Season $season, Stadium $stadium, string $date, array $offer, ?FinanceTransactions $transaction): SeasonTicketSale
    {
        return SeasonTicketSale::query()->create($offer + [
            'instance_id' => $club->instance_id, 'club_id' => $club->id, 'season_id' => $season->id,
            'stadium_id' => $stadium->id, 'sold_on' => $date, 'transaction_id' => $transaction?->id,
        ]);
    }
}
