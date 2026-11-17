<?php

namespace App\Repositories;

use App\Models\Club;
use App\Models\Competition;
use App\Models\Game;
use App\Models\Instance;
use App\Models\SeasonTicketSale;
use App\Models\Stadium;
use App\Models\StadiumCommercialProduct;
use App\Services\CommercialService\Domain\TicketType;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

class AiCommercialPricingRepository
{
    public function transaction(Closure $callback): mixed
    {
        return DB::transaction($callback);
    }

    /** @return LazyCollection<int, Club> */
    public function clubs(Instance $instance): LazyCollection
    {
        return Club::query()->forInstance($instance->id)->whereNotNull('stadium_id')->lazyById(200);
    }

    public function instanceForUpdate(int $instanceId): Instance
    {
        return Instance::query()->lockForUpdate()->findOrFail($instanceId);
    }

    public function clubForUpdate(Club $club): Club
    {
        return Club::query()->forInstance($club->instance_id)->lockForUpdate()->findOrFail($club->id);
    }

    public function managedStadiumId(Instance $instance): ?int
    {
        $stadiumId = Club::query()->forInstance($instance->id)->whereKey($instance->club_id)->value('stadium_id');

        return $stadiumId === null ? null : (int) $stadiumId;
    }

    public function stadiumForUpdate(Club $club): ?Stadium
    {
        return Stadium::query()->forInstance($club->instance_id)->whereKey($club->stadium_id)->lockForUpdate()->first();
    }

    /** @return list<array{away_rank: int, competition_rank: int, ticket_type: TicketType, season_ticket_holders: int}> */
    public function fixtures(Club $club, Instance $instance, CarbonInterface $date): array
    {
        $holders = (int) SeasonTicketSale::query()->forClub($club)->where('season_id', $instance->season_id)
            ->whereDate('sold_on', '<=', $date->toDateString())->value('quantity');

        return Game::query()->where('games.instance_id', $instance->id)->where('games.season_id', $instance->season_id)
            ->where('games.hometeam_id', $club->id)->where('games.stadium_id', $club->stadium_id)
            ->whereIn('games.status', [Game::STATUS_SCHEDULED, Game::STATUS_POSTPONED])
            ->where('games.match_start', '>=', $date)->whereDoesntHave('matchdaySettlement')
            ->join('competitions', 'competitions.id', '=', 'games.competition_id')->where('competitions.instance_id', $instance->id)
            ->join('clubs as opponents', 'opponents.id', '=', 'games.awayteam_id')->where('opponents.instance_id', $instance->id)
            ->orderBy('games.match_start')->orderBy('games.id')->limit(5)
            ->get(['opponents.rank as away_rank', 'competitions.rank as competition_rank', 'competitions.type', 'competitions.competition_scope'])
            ->map(function (Game $game) use ($holders): array {
                $type = TicketType::forCompetition($game->type, $game->competition_scope ?? 'domestic');

                return ['away_rank' => (int) $game->away_rank, 'competition_rank' => (int) $game->competition_rank,
                    'ticket_type' => $type, 'season_ticket_holders' => $type === TicketType::LEAGUE ? $holders : 0];
            })->all();
    }

    public function leagueRank(Club $club, Instance $instance): int
    {
        return (int) Competition::query()->where('competitions.instance_id', $instance->id)->domesticLeague()
            ->join('competition_season', 'competition_season.competition_id', '=', 'competitions.id')
            ->where('competition_season.instance_id', $instance->id)->where('competition_season.season_id', $instance->season_id)
            ->where('competition_season.club_id', $club->id)->max('competitions.rank');
    }

    /** @param array<int, float> $productPrices */
    public function storePrices(Stadium $stadium, int $ticketPrice, array $productPrices, CarbonInterface $date): void
    {
        foreach ($productPrices as $productId => $multiplier) {
            StadiumCommercialProduct::query()->forStadium($stadium)->available()->whereKey($productId)->update(['price_change_coef' => $multiplier]);
        }
        $stadium->forceFill(['ticket_price' => $ticketPrice, 'ai_prices_reviewed_on' => $date->toDateString()])->save();
    }
}
