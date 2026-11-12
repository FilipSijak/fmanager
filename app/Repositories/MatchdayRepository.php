<?php

namespace App\Repositories;

use App\Models\Account;
use App\Models\Club;
use App\Models\Competition;
use App\Models\Game;
use App\Models\MatchdaySettlement;
use App\Models\Stadium;
use App\Models\StadiumCommercialProduct;
use App\Models\StadiumCommercialVenue;
use App\Models\StadiumStand;
use App\StadiumStandStatus;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class MatchdayRepository
{
    public function transaction(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }

    public function gameForUpdate(Game $game): Game
    {
        return Game::query()->forInstance($game->instance_id)->lockForUpdate()->findOrFail($game->id);
    }

    public function settlement(Game $game): ?MatchdaySettlement
    {
        return $game->matchdaySettlement()->first();
    }

    public function club(Game $game, int $clubId): Club
    {
        return Club::query()->forInstance($game->instance_id)->findOrFail($clubId);
    }

    public function competition(Game $game): Competition
    {
        return Competition::query()->forInstance($game->instance_id)->findOrFail($game->competition_id);
    }

    public function stadium(Game $game): Stadium
    {
        return Stadium::query()->where('instance_id', $game->instance_id)->findOrFail($game->stadium_id);
    }

    public function accountForUpdate(Club $club): Account
    {
        return Account::query()->where('club_id', $club->id)->lockForUpdate()->firstOrFail();
    }

    /** @return list<array{capacity: int, available: bool}> */
    public function stands(Stadium $stadium): array
    {
        return $stadium->stands()->get()->map(fn (StadiumStand $stand): array => [
            'capacity' => (int) $stand->capacity,
            'available' => $stand->status === StadiumStandStatus::ACTIVE,
        ])->all();
    }

    /** @return list<array{size: int, products: list<array{base_price: int, price_multiplier: float}>}> */
    public function venues(Stadium $stadium): array
    {
        $products = StadiumCommercialProduct::query()->forStadium($stadium)->available()
            ->whereHas('baseProduct.category', fn (Builder $query): Builder => $query->active())
            ->with('baseProduct')->get()->groupBy('baseProduct.category_id');

        return StadiumCommercialVenue::query()->forStadium($stadium)
            ->whereHas('category', fn (Builder $query): Builder => $query->active())->get()
            ->map(fn (StadiumCommercialVenue $venue): array => [
                'size' => $venue->size->value,
                'products' => $products->get($venue->category_id, collect())->map(fn (StadiumCommercialProduct $product): array => [
                    'base_price' => (int) $product->base_price,
                    'price_multiplier' => (float) $product->price_change_coef,
                ])->values()->all(),
            ])->all();
    }

    /** @param array{attendance: int, ticket_type: string, ticket_price: int, season_ticket_attendance: int, paying_attendance: int, gate_receipts: int, commercial_income: int, operating_costs: int} $amounts */
    public function recordSettlement(Game $game, string $date, array $amounts): MatchdaySettlement
    {
        $game->forceFill(['attendance' => $amounts['attendance']])->save();

        return MatchdaySettlement::query()->create($amounts + [
            'game_id' => $game->id,
            'instance_id' => $game->instance_id,
            'club_id' => $game->hometeam_id,
            'stadium_id' => $game->stadium_id,
            'settled_on' => $date,
        ]);
    }
}
