<?php

namespace App\Repositories;

use App\Models\BaseData\BaseCommercialProducts;
use App\Models\Club;
use App\Models\Competition;
use App\Models\Country;
use App\Models\Instance;
use App\Models\Stadium;
use App\Models\StadiumCommercialProduct;
use App\Models\StadiumCommercialVenue;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class CommercialRepository
{
    public function transaction(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }

    public function requireInstance(int $instanceId): void
    {
        Instance::query()->findOrFail($instanceId);
    }

    /** @return LazyCollection<int, Stadium> */
    public function stadiums(?int $instanceId): LazyCollection
    {
        return Stadium::query()->when($instanceId !== null, fn (Builder $query): Builder => $query->forInstance($instanceId))->lazyById(200);
    }

    public function lockStadium(Stadium $stadium): Stadium
    {
        return Stadium::query()->forInstance($stadium->instance_id)->lockForUpdate()->findOrFail($stadium->id);
    }

    public function stadiumForClub(Club $club): Stadium
    {
        return Stadium::query()->forInstance($club->instance_id)->findOrFail($club->stadium_id);
    }

    /** @return array{club_rank: int, competition_rank: int, country_rank: int} */
    public function ticketPriceInputs(Stadium $stadium): array
    {
        $club = Club::query()->forInstance($stadium->instance_id)->where('stadium_id', $stadium->id)->orderBy('id')->first();
        $countryRank = (int) Country::query()->where('code', $club?->country_code ?? $stadium->country_code)->value('ranking');
        $seasonId = Instance::query()->whereKey($stadium->instance_id)->value('season_id');
        $competitionRank = $club === null ? 0 : (int) $this->competitionsForClub($club, $seasonId)
            ->where('type', 'league')->where('competition_scope', 'domestic')->max('rank');

        return ['club_rank' => (int) ($club?->rank ?? 0), 'competition_rank' => $competitionRank, 'country_rank' => $countryRank];
    }

    public function storeTicketPrice(Stadium $stadium, int $price): void
    {
        $stadium->forceFill(['ticket_price' => $price])->save();
    }

    /** @return Collection<int, Competition> */
    public function competitionsForClub(Club $club, ?int $seasonId): Collection
    {
        return Competition::query()->where('competitions.instance_id', $club->instance_id)
            ->join('competition_season', 'competition_season.competition_id', '=', 'competitions.id')
            ->where('competition_season.club_id', $club->id)
            ->where('competition_season.instance_id', $club->instance_id)
            ->where('competition_season.season_id', $seasonId)
            ->select('competitions.*')->distinct()->get();
    }

    /** @return list<array{id: int, base_price: int}> */
    public function missingProducts(Stadium $stadium): array
    {
        return BaseCommercialProducts::query()
            ->whereIn('category_id', StadiumCommercialVenue::query()->forStadium($stadium)->select('category_id'))
            ->whereNotIn('id', StadiumCommercialProduct::query()->forStadium($stadium)->select('base_product_id'))
            ->get(['id', 'base_price'])->map(fn (BaseCommercialProducts $product): array => [
                'id' => (int) $product->id, 'base_price' => (int) $product->base_price,
            ])->all();
    }

    /** @param list<array{instance_id: int, stadium_id: int, base_product_id: int, base_price: int, price_change_coef: float, is_available: bool}> $rows */
    public function insertProducts(array $rows): void
    {
        if ($rows !== []) {
            DB::table('stadium_commercial_products')->insertOrIgnore($rows);
        }
    }
}
