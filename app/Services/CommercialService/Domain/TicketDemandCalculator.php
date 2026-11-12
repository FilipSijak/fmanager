<?php

namespace App\Services\CommercialService\Domain;

use App\Services\CommercialService\CommercialRankingConfig;

class TicketDemandCalculator
{
    private const float MAXIMUM_PRICE_TOLERANCE = 0.10;

    private const float DISCOUNT_DEMAND_BONUS = 0.50;

    public function __construct(private readonly TicketPriceCalculator $ticketPriceCalculator = new TicketPriceCalculator) {}

    /** @param list<array{home_club_id: int, home_goals: int, away_goals: int}> $results */
    public function performanceScore(int $clubId, array $results): float
    {
        if ($results === []) {
            return 0.5;
        }
        $points = 0;
        foreach ($results as $result) {
            $ownGoals = $result['home_club_id'] === $clubId ? $result['home_goals'] : $result['away_goals'];
            $opponentGoals = $result['home_club_id'] === $clubId ? $result['away_goals'] : $result['home_goals'];
            $points += $ownGoals > $opponentGoals ? 3 : ($ownGoals === $opponentGoals ? 1 : 0);
        }

        return $points / (count($results) * 3);
    }

    public function referencePrice(int $clubRank, int $competitionRank, int $countryRank, TicketType $type): int
    {
        return $this->ticketPriceCalculator->forType($this->ticketPriceCalculator->calculate($clubRank, $competitionRank, $countryRank), $type);
    }

    public function priceFactor(int $price, int $referencePrice, int $countryRank): float
    {
        $country = CommercialRankingConfig::normalizeCountryRank($countryRank);
        $ratio = max(0, $price) / max(1, $referencePrice);
        $elasticity = 1 + 2 * (1 - $country);
        if ($ratio < 1) {
            return 1 + (1 - $ratio) * self::DISCOUNT_DEMAND_BONUS * $elasticity;
        }
        $tolerance = self::MAXIMUM_PRICE_TOLERANCE * $country;
        if ($ratio <= 1 + $tolerance) {
            return 1.0;
        }

        return 1 / (1 + $elasticity * ($ratio - 1 - $tolerance)) ** 2;
    }

    public function attendance(int $capacity, int $homeRank, int $awayRank, int $competitionRank, int $countryRank, float $performance, int $ticketPrice, TicketType $type): int
    {
        $interest = 0.25
            + CommercialRankingConfig::normalizeClubRank($homeRank) * 0.30
            + CommercialRankingConfig::normalizeClubRank($awayRank) * 0.10
            + CommercialRankingConfig::normalizeCompetitionRank($competitionRank) * 0.15
            + CommercialRankingConfig::normalizeCountryRank($countryRank) * 0.10
            + max(0.0, min(1.0, $performance)) * 0.20;
        $factor = $this->priceFactor($ticketPrice, $this->referencePrice($homeRank, $competitionRank, $countryRank, $type), $countryRank);

        return (int) round(max(0, $capacity) * max(0.0, min(1.0, $interest * $factor)));
    }

    public function seasonTicketDemand(int $clubRank, int $competitionRank, int $countryRank, float $performance, int $leaguePrice): float
    {
        $interest = 0.25
            + CommercialRankingConfig::normalizeClubRank($clubRank) * 0.35
            + CommercialRankingConfig::normalizeCompetitionRank($competitionRank) * 0.20
            + CommercialRankingConfig::normalizeCountryRank($countryRank) * 0.10
            + max(0.0, min(1.0, $performance)) * 0.20;
        $factor = $this->priceFactor($leaguePrice, $this->referencePrice($clubRank, $competitionRank, $countryRank, TicketType::LEAGUE), $countryRank);

        return max(0.0, min(1.0, $interest * $factor));
    }
}
