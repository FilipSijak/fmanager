<?php

namespace App\Services\MatchdayService\Domain;

use App\Services\CommercialService\CommercialRankingConfig;
use App\Services\CommercialService\Domain\TicketType;

class MatchdayCalculator
{
    private const int FIXED_STADIUM_COST = 100;

    private const int COST_PER_SUPPORTER = 2;

    private const int CUSTOMERS_PER_VENUE_SIZE = 250;

    private const int STAFF_COST_PER_VENUE_SIZE = 50;

    /** @param list<array{capacity: int, available: bool}> $stands */
    public function availableCapacity(int $activeCapacity, array $stands): int
    {
        if ($stands === []) {
            return max(0, $activeCapacity);
        }

        return array_sum(array_map(fn (array $stand): int => $stand['available'] ? max(0, $stand['capacity']) : 0, $stands));
    }

    public function attendance(int $capacity, int $homeRank, int $awayRank, int $competitionRank): int
    {
        $occupancy = 0.35 + CommercialRankingConfig::normalizeClubRank($homeRank) * 0.35
            + CommercialRankingConfig::normalizeClubRank($awayRank) * 0.15
            + CommercialRankingConfig::normalizeCompetitionRank($competitionRank) * 0.15;

        return max(0, min($capacity, (int) round($capacity * $occupancy)));
    }

    /**
     * @param  list<array{size: int, products: list<array{base_price: int, price_multiplier: float}>}>  $venues
     * @return array{attendance: int, ticket_type: string, ticket_price: int, season_ticket_attendance: int, paying_attendance: int, gate_receipts: int, commercial_income: int, operating_costs: int}
     */
    public function settlement(int $capacity, int $homeRank, int $awayRank, int $competitionRank, int $ticketPrice, array $venues, TicketType $ticketType = TicketType::LEAGUE, int $seasonTicketHolders = 0): array
    {
        $seasonAttendance = $ticketType === TicketType::LEAGUE ? min(max(0, $capacity), max(0, $seasonTicketHolders)) : 0;
        $attendance = max($seasonAttendance, $this->attendance($capacity, $homeRank, $awayRank, $competitionRank));
        $payingAttendance = $attendance - $seasonAttendance;
        $ticketPrice = max(0, $ticketPrice);
        $commercialIncome = 0;
        $operatingCosts = self::FIXED_STADIUM_COST + $attendance * self::COST_PER_SUPPORTER;

        foreach ($venues as $venue) {
            if ($venue['products'] === []) {
                continue;
            }

            $customers = min((int) floor($attendance * 0.20), self::CUSTOMERS_PER_VENUE_SIZE * $venue['size']);
            $basePrices = array_column($venue['products'], 'base_price');
            $salePrices = array_map(fn (array $product): float => $product['base_price'] * max(0, $product['price_multiplier']), $venue['products']);
            $numberOfProducts = count($venue['products']);
            $commercialIncome += $this->wholeCurrency($customers * array_sum($salePrices) / $numberOfProducts);
            $operatingCosts += $this->wholeCurrency($customers * array_sum($basePrices) / $numberOfProducts * 0.50)
                + self::STAFF_COST_PER_VENUE_SIZE * $venue['size'];
        }

        return [
            'attendance' => $attendance,
            'ticket_type' => $ticketType->value,
            'ticket_price' => $ticketPrice,
            'season_ticket_attendance' => $seasonAttendance,
            'paying_attendance' => $payingAttendance,
            'gate_receipts' => $payingAttendance * $ticketPrice,
            'commercial_income' => $commercialIncome,
            'operating_costs' => $operatingCosts,
        ];
    }

    /** Commercial product prices are in minor units; accounts and tickets use whole currency units. */
    private function wholeCurrency(float $minorUnits): int
    {
        return (int) round($minorUnits / 100);
    }
}
