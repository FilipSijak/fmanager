<?php

namespace Tests\Unit;

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
        $this->assertSame(700, $calculator->attendance(1000, 20, 0, 0));
        $this->assertSame(500, $calculator->attendance(1000, 0, 20, 0));
        $this->assertSame(500, $calculator->attendance(1000, 0, 0, 10000));
        $this->assertSame(1000, $calculator->attendance(1000, 200, 200, 100000));
        $this->assertSame(0, $calculator->attendance(0, 20, 20, 10000));
    }

    #[Test]
    public function larger_venues_can_serve_more_supporters_up_to_their_customer_limit(): void
    {
        $calculator = new MatchdayCalculator;
        $products = [['base_price' => 500, 'price_multiplier' => 1.0]];

        $small = $calculator->settlement(10000, 20, 20, 10000, 100, [['size' => 1, 'products' => $products]]);
        $medium = $calculator->settlement(10000, 20, 20, 10000, 100, [['size' => 2, 'products' => $products]]);
        $large = $calculator->settlement(10000, 20, 20, 10000, 100, [['size' => 3, 'products' => $products]]);

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
}
