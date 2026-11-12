<?php

namespace App\Services\CommercialService\Domain;

use App\Services\CommercialService\CommercialRankingConfig;

class TicketPriceCalculator
{
    public function calculate(int $clubRank, int $competitionRank, int $countryRank): int
    {
        $demand = CommercialRankingConfig::normalizeCountryRank($countryRank) * 0.25
            + CommercialRankingConfig::normalizeCompetitionRank($competitionRank) * 0.45
            + CommercialRankingConfig::normalizeClubRank($clubRank) * 0.30;

        return (int) round(10 + 90 * $demand);
    }

    public function forType(int $leaguePrice, TicketType $type): int
    {
        return (int) round(max(0, $leaguePrice) * $type->multiplier());
    }

    /** @return array{league: int, cup: int, continental: int} */
    public function prices(int $leaguePrice): array
    {
        return [
            'league' => $this->forType($leaguePrice, TicketType::LEAGUE),
            'cup' => $this->forType($leaguePrice, TicketType::CUP),
            'continental' => $this->forType($leaguePrice, TicketType::CONTINENTAL),
        ];
    }
}
