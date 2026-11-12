<?php

namespace Tests\Unit;

use App\Services\CommercialService\Domain\TicketType;
use App\Services\MatchdayService\Domain\MatchdayCalculator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MatchdayCalculatorTest extends TestCase
{
    #[Test]
    public function home_away_and_competition_appeal_increase_attendance_without_exceeding_capacity(): void
    {
        $calculator = new MatchdayCalculator;

        $this->assertSame(350, $calculator->attendance(1000, 0, 0, 0));
        $this->assertSame(650, $calculator->attendance(1000, 20, 0, 0));
        $this->assertSame(450, $calculator->attendance(1000, 0, 20, 0));
        $this->assertSame(500, $calculator->attendance(1000, 0, 0, 10000));
        $this->assertSame(1000, $calculator->attendance(1000, 200, 200, 100000, 100));
        $this->assertSame(0, $calculator->attendance(0, 20, 20, 10000));
    }

    #[Test]
    public function larger_venues_can_serve_more_supporters_up_to_their_customer_limit(): void
    {
        $calculator = new MatchdayCalculator;
        $products = [['base_price' => 500, 'price_multiplier' => 1.0]];

        $small = $calculator->settlement(10000, 20, 20, 10000, 100, [['size' => 1, 'products' => $products]], TicketType::LEAGUE, 0, 100);
        $medium = $calculator->settlement(10000, 20, 20, 10000, 100, [['size' => 2, 'products' => $products]], TicketType::LEAGUE, 0, 100);
        $large = $calculator->settlement(10000, 20, 20, 10000, 100, [['size' => 3, 'products' => $products]], TicketType::LEAGUE, 0, 100);

        $this->assertSame(1250, $small['commercial_income']);
        $this->assertSame(2500, $medium['commercial_income']);
        $this->assertSame(3750, $large['commercial_income']);
        $this->assertSame(20775, $small['operating_costs']);
    }

    #[Test]
    public function cached_capacity_is_used_for_stadiums_without_stands_and_cannot_be_negative(): void
    {
        $calculator = new MatchdayCalculator;

        $this->assertSame(500, $calculator->availableCapacity(500, []));
        $this->assertSame(0, $calculator->availableCapacity(-100, []));
    }

    #[Test]
    public function season_holders_are_counted_in_attendance_but_not_charged_at_the_league_gate(): void
    {
        $calculator = new MatchdayCalculator;

        $league = $calculator->settlement(1000, 0, 0, 0, 100, [], TicketType::LEAGUE, 400);
        $cup = $calculator->settlement(1000, 0, 0, 0, 8, [], TicketType::CUP, 400);

        $this->assertSame(400, $league['attendance']);
        $this->assertSame(400, $league['season_ticket_attendance']);
        $this->assertSame(0, $league['paying_attendance']);
        $this->assertSame(0, $league['gate_receipts']);
        $this->assertSame(0, $cup['season_ticket_attendance']);
        $this->assertSame(350, $cup['paying_attendance']);
        $this->assertSame(2800, $cup['gate_receipts']);
    }

    #[Test]
    public function reduced_capacity_limits_season_ticket_attendance_without_admitting_extra_supporters(): void
    {
        $calculator = new MatchdayCalculator;

        $settlement = $calculator->settlement(200, 20, 20, 10000, 100, [], TicketType::LEAGUE, 400);

        $this->assertSame(200, $settlement['attendance']);
        $this->assertSame(200, $settlement['season_ticket_attendance']);
        $this->assertSame(0, $settlement['paying_attendance']);
        $this->assertSame(0, $settlement['gate_receipts']);
    }
}
