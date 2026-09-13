<?php

namespace App\Services\CommercialService;

use App\Models\Club;
use App\Models\Competition;
use App\Models\Country;
use App\Support\GameContext;
use Illuminate\Support\Collection;

class CommercialService
{
    private const int MINIMUM_TICKET_PRICE = 10;

    private const int MAXIMUM_TICKET_PRICE = 100;

    private const float COUNTRY_WEIGHT = 0.25;

    private const float COMPETITION_WEIGHT = 0.45;

    private const float CLUB_WEIGHT = 0.30;

    public function __construct(private readonly GameContext $gameContext) {}

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
        $demandScore = (CommercialRankingConfig::normalizeCountryRank((int) $country->ranking) * self::COUNTRY_WEIGHT)
            + (CommercialRankingConfig::normalizeCompetitionRank((int) $competition->rank) * self::COMPETITION_WEIGHT)
            + (CommercialRankingConfig::normalizeClubRank((int) $club->rank) * self::CLUB_WEIGHT);

        return (int) round(self::MINIMUM_TICKET_PRICE
            + ((self::MAXIMUM_TICKET_PRICE - self::MINIMUM_TICKET_PRICE) * $demandScore));
    }
}
