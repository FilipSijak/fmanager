<?php

namespace Tests\Unit;

use App\Services\CommercialService\Domain\TicketDemandCalculator;
use App\Services\CommercialService\Domain\TicketType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TicketDemandCalculatorTest extends TestCase
{
    #[Test]
    public function top_ranked_countries_tolerate_ten_percent_above_reference_before_demand_drops(): void
    {
        $calculator = new TicketDemandCalculator;

        $this->assertSame(1.0, $calculator->priceFactor(100, 100, 100));
        $this->assertSame(1.0, $calculator->priceFactor(110, 100, 100));
        $this->assertLessThan(1.0, $calculator->priceFactor(111, 100, 100));
        $this->assertLessThan($calculator->priceFactor(111, 100, 100), $calculator->priceFactor(150, 100, 100));
    }

    #[Test]
    public function lower_ranked_countries_respond_more_strongly_to_discounts_and_price_increases(): void
    {
        $calculator = new TicketDemandCalculator;

        $this->assertGreaterThan(1.0, $calculator->priceFactor(75, 100, 100));
        $this->assertGreaterThan($calculator->priceFactor(75, 100, 100), $calculator->priceFactor(75, 100, 0));
        $this->assertLessThan(1.0, $calculator->priceFactor(105, 100, 0));
        $this->assertLessThan($calculator->priceFactor(105, 100, 50), $calculator->priceFactor(105, 100, 0));
        $this->assertLessThan($calculator->priceFactor(200, 100, 100), $calculator->priceFactor(200, 100, 0));
    }

    #[Test]
    public function performance_scores_handle_home_away_draws_and_missing_history(): void
    {
        $calculator = new TicketDemandCalculator;

        $this->assertSame(0.5, $calculator->performanceScore(1, []));
        $this->assertSame(1.0, $calculator->performanceScore(1, [['home_club_id' => 1, 'home_goals' => 2, 'away_goals' => 0]]));
        $this->assertSame(1.0, $calculator->performanceScore(1, [['home_club_id' => 2, 'home_goals' => 0, 'away_goals' => 2]]));
        $this->assertSame(0.0, $calculator->performanceScore(1, [['home_club_id' => 1, 'home_goals' => 0, 'away_goals' => 2]]));
        $this->assertEqualsWithDelta(1 / 3, $calculator->performanceScore(1, [['home_club_id' => 1, 'home_goals' => 1, 'away_goals' => 1]]), 0.00001);
    }

    #[Test]
    public function reference_prices_follow_competition_types_and_are_independent_of_the_chosen_price(): void
    {
        $calculator = new TicketDemandCalculator;

        $this->assertSame(100, $calculator->referencePrice(20, 10000, 100, TicketType::LEAGUE));
        $this->assertSame(80, $calculator->referencePrice(20, 10000, 100, TicketType::CUP));
        $this->assertSame(120, $calculator->referencePrice(20, 10000, 100, TicketType::CONTINENTAL));
        $this->assertSame(66, $calculator->referencePrice(10, 5000, 100, TicketType::LEAGUE));
    }

    #[Test]
    public function competition_appeal_and_performance_raise_interest_at_fair_prices(): void
    {
        $calculator = new TicketDemandCalculator;
        $losing = $calculator->attendance(1000, 10, 10, 5000, 100, 0, 66, TicketType::LEAGUE);
        $neutral = $calculator->attendance(1000, 10, 10, 5000, 100, 0.5, 66, TicketType::LEAGUE);
        $winning = $calculator->attendance(1000, 10, 10, 5000, 100, 1, 66, TicketType::LEAGUE);

        $this->assertSame(625, $losing);
        $this->assertSame(725, $neutral);
        $this->assertSame(825, $winning);
        $this->assertLessThan($neutral, $calculator->attendance(1000, 10, 10, 0, 100, 0.5, 46, TicketType::LEAGUE));
    }

    #[Test]
    public function discount_demand_is_bounded_and_extreme_prices_cannot_generate_unlimited_income(): void
    {
        $calculator = new TicketDemandCalculator;

        $this->assertSame(1000, $calculator->attendance(1000, 20, 20, 10000, 100, 1, 0, TicketType::LEAGUE));
        $this->assertSame(0, $calculator->attendance(0, 20, 20, 10000, 100, 1, 100, TicketType::LEAGUE));
        $expensiveAttendance = $calculator->attendance(1000, 20, 20, 10000, 100, 0.5, 10000, TicketType::LEAGUE);
        $this->assertLessThan(100000, $expensiveAttendance * 10000);
        $this->assertLessThan(1.0, $calculator->seasonTicketDemand(10, 5000, 100, 0.5, 66));
    }
}
