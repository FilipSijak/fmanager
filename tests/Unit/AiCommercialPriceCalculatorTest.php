<?php

namespace Tests\Unit;

use App\Services\CommercialService\Domain\AiCommercialPriceCalculator;
use App\Services\CommercialService\Domain\TicketType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class AiCommercialPriceCalculatorTest extends TestCase
{
    #[Test]
    public function profitable_ticket_and_product_increases_are_limited_to_ten_percent_per_review(): void
    {
        $calculator = new AiCommercialPriceCalculator;
        $venues = [['size' => 1, 'products' => [['id' => 7, 'base_price' => 500, 'price_multiplier' => 1.0]]]];
        $fixtures = [['capacity' => 1000, 'away_rank' => 20, 'competition_rank' => 10000, 'ticket_type' => TicketType::LEAGUE, 'season_ticket_holders' => 400]];

        $prices = $calculator->prices(100, $venues, $fixtures, $this->demand());

        $this->assertSame(110, $prices['ticket_price']);
        $this->assertSame([7 => 1.1], $prices['product_prices']);
    }

    #[Test]
    public function poor_form_and_low_rank_reduce_the_prices_supported_by_demand(): void
    {
        $calculator = new AiCommercialPriceCalculator;
        $fixtures = [['capacity' => 1000, 'away_rank' => 20, 'competition_rank' => 10000, 'ticket_type' => TicketType::LEAGUE, 'season_ticket_holders' => 0]];
        $winning = array_replace($this->demand(), ['performance_score' => 1.0, 'ticket_price_sensitivity' => 1.0]);
        $losing = array_replace($this->demand(), ['performance_score' => 0.0, 'ticket_price_sensitivity' => 2.0]);
        $lowerRanked = array_replace($winning, ['club_rank' => 5, 'ticket_price_sensitivity' => 1.75]);

        $winningPrices = $calculator->prices(110, [], $fixtures, $winning);
        $losingPrices = $calculator->prices(110, [], $fixtures, $losing);
        $lowerRankedPrices = $calculator->prices(110, [], $fixtures, $lowerRanked);

        $this->assertSame(116, $winningPrices['ticket_price']);
        $this->assertSame(110, $losingPrices['ticket_price']);
        $this->assertSame(99, $lowerRankedPrices['ticket_price']);
    }

    #[Test]
    public function prices_stay_unchanged_when_a_closed_stadium_cannot_improve_income(): void
    {
        $calculator = new AiCommercialPriceCalculator;
        $venues = [['size' => 1, 'products' => [['id' => 7, 'base_price' => 500, 'price_multiplier' => 1.0]]]];
        $fixtures = [['capacity' => 0, 'away_rank' => 20, 'competition_rank' => 10000, 'ticket_type' => TicketType::LEAGUE, 'season_ticket_holders' => 400]];

        $prices = $calculator->prices(100, $venues, $fixtures, $this->demand());

        $this->assertSame(['ticket_price' => 100, 'product_prices' => [7 => 1.0]], $prices);
    }

    #[Test]
    public function prepaid_seats_do_not_encourage_unprofitable_ticket_price_changes(): void
    {
        $calculator = new AiCommercialPriceCalculator;
        $fixtures = [['capacity' => 1000, 'away_rank' => 20, 'competition_rank' => 10000, 'ticket_type' => TicketType::LEAGUE, 'season_ticket_holders' => 1000]];

        $prices = $calculator->prices(100, [], $fixtures, $this->demand());

        $this->assertSame(100, $prices['ticket_price']);
    }

    #[Test]
    public function free_prices_can_increase_by_a_small_initial_step(): void
    {
        $calculator = new AiCommercialPriceCalculator;
        $venues = [['size' => 1, 'products' => [['id' => 7, 'base_price' => 500, 'price_multiplier' => 0.0]]]];
        $fixtures = [['capacity' => 1000, 'away_rank' => 20, 'competition_rank' => 10000, 'ticket_type' => TicketType::LEAGUE, 'season_ticket_holders' => 0]];

        $prices = $calculator->prices(0, $venues, $fixtures, $this->demand());

        $this->assertSame(1, $prices['ticket_price']);
        $this->assertSame([7 => 0.01], $prices['product_prices']);
    }

    /** @return array{club_rank: int, country_rank: int, performance_score: float, ticket_price_sensitivity: float, commercial_price_sensitivity: float} */
    private function demand(): array
    {
        return ['club_rank' => 20, 'country_rank' => 100, 'performance_score' => 0.5,
            'ticket_price_sensitivity' => 1.5, 'commercial_price_sensitivity' => 1.5];
    }
}
