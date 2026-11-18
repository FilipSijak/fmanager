<?php

namespace App\Services\CommercialService\Domain;

use App\Services\MatchdayService\Domain\MatchdayCalculator;

class AiCommercialPriceCalculator
{
    private const float MAXIMUM_MONTHLY_CHANGE = 0.10;

    public function __construct(
        private readonly MatchdayCalculator $matchdays = new MatchdayCalculator,
        private readonly TicketPriceCalculator $ticketPrices = new TicketPriceCalculator,
    ) {}

    /**
     * @param  list<array{size: int, products: list<array{id: int, base_price: int, price_multiplier: float}>}>  $venues
     * @param  list<array{capacity: int, away_rank: int, competition_rank: int, ticket_type: TicketType, season_ticket_holders: int}>  $fixtures
     * @param  array{club_rank: int, country_rank: int, performance_score: float, ticket_price_sensitivity: float, commercial_price_sensitivity: float}  $demand
     * @return array{ticket_price: int, product_prices: array<int, float>}
     */
    public function prices(int $leaguePrice, array $venues, array $fixtures, array $demand): array
    {
        $leaguePrice = max(0, $leaguePrice);
        $step = max(1, (int) floor($leaguePrice * self::MAXIMUM_MONTHLY_CHANGE));
        $halfStep = max(1, (int) round($step / 2));
        $candidates = array_unique([$leaguePrice, max(0, $leaguePrice - $step), max(0, $leaguePrice - $halfStep), $leaguePrice + $halfStep, $leaguePrice + $step]);
        $bestPrice = $leaguePrice;
        $bestVenues = $venues;
        $bestIncome = $this->expectedIncome($leaguePrice, $venues, $fixtures, $demand);

        foreach ($candidates as $price) {
            foreach ([0.0, -0.10, -0.05, 0.05, 0.10] as $adjustment) {
                $candidateVenues = $venues;
                foreach ($candidateVenues as &$venue) {
                    foreach ($venue['products'] as &$product) {
                        $current = max(0.0, $product['price_multiplier']);
                        $product['price_multiplier'] = round(max(0.0, min(9999.9999, $current + max(0.1, $current) * $adjustment)), 4);
                    }
                    unset($product);
                }
                unset($venue);
                $income = $this->expectedIncome($price, $candidateVenues, $fixtures, $demand);
                if ($income > $bestIncome) {
                    $bestIncome = $income;
                    $bestPrice = $price;
                    $bestVenues = $candidateVenues;
                }
            }
        }
        $productPrices = [];
        foreach ($bestVenues as $venue) {
            foreach ($venue['products'] as $product) {
                $productPrices[$product['id']] = $product['price_multiplier'];
            }
        }

        return ['ticket_price' => $bestPrice, 'product_prices' => $productPrices];
    }

    /**
     * @param  list<array{size: int, products: list<array{id: int, base_price: int, price_multiplier: float}>}>  $venues
     * @param  list<array{capacity: int, away_rank: int, competition_rank: int, ticket_type: TicketType, season_ticket_holders: int}>  $fixtures
     * @param  array{club_rank: int, country_rank: int, performance_score: float, ticket_price_sensitivity: float, commercial_price_sensitivity: float}  $demand
     */
    private function expectedIncome(int $leaguePrice, array $venues, array $fixtures, array $demand): int
    {
        $income = 0;
        foreach ($fixtures as $fixture) {
            $amounts = $this->matchdays->settlement($fixture['capacity'], $demand['club_rank'], $fixture['away_rank'],
                $fixture['competition_rank'], $this->ticketPrices->forType($leaguePrice, $fixture['ticket_type']), $venues,
                $fixture['ticket_type'], $fixture['season_ticket_holders'], $demand['country_rank'], $demand['performance_score'],
                $demand['ticket_price_sensitivity'], $demand['commercial_price_sensitivity']);
            $income += $amounts['gate_receipts'] + $amounts['commercial_income'] - $amounts['operating_costs'];
        }

        return $income;
    }
}
