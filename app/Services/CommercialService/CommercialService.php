<?php

namespace App\Services\CommercialService;

use App\Models\Club;
use App\Models\Competition;
use App\Models\Country;
use App\Services\CommercialService\Domain\TicketPriceCalculator;
use App\Support\GameContext;
use Illuminate\Support\Collection;

class CommercialService
{
    public function __construct(
        private readonly GameContext $gameContext,
        private readonly TicketPriceCalculator $ticketPriceCalculator,
    ) {}

    public function ticketPriceForCompetition(Club $club, Competition $competition): int
    {
        $country = Country::query()->where('code', $club->country_code)->firstOrFail();

        return $this->calculateTicketPrice($club, $competition, $country);
    }

    /** @return Collection<int, int> */
    public function ticketPricesForClub(Club $club): Collection
    {
        $country = Country::query()->where('code', $club->country_code)->firstOrFail();
        $competitions = Competition::query()
            ->join('competition_season', 'competition_season.competition_id', '=', 'competitions.id')
            ->where('competition_season.club_id', $club->id)
            ->where('competition_season.instance_id', $this->gameContext->instanceId())
            ->where('competition_season.season_id', $this->gameContext->seasonId())
            ->where('competitions.instance_id', $this->gameContext->instanceId())
            ->select('competitions.*')
            ->distinct()
            ->get();

        return $competitions->mapWithKeys(
            fn (Competition $competition): array => [
                $competition->id => $this->calculateTicketPrice($club, $competition, $country),
            ]
        );
    }

    private function calculateTicketPrice(Club $club, Competition $competition, Country $country): int
    {
        return $this->ticketPriceCalculator->calculate((int) $club->rank, (int) $competition->rank, (int) $country->ranking);
    }
}
