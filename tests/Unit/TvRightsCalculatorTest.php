<?php

namespace Tests\Unit;

use App\Services\FinanceService\Domain\TvRightsCalculator;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TvRightsCalculatorTest extends TestCase
{
    #[Test]
    public function it_calculates_tv_rights_using_equal_competition_and_club_weights(): void
    {
        $calculator = new TvRightsCalculator;

        $this->assertSame(39_400_000, $calculator->calculate(9700, 20));
        $this->assertSame(20_000_000, $calculator->calculate(5000, 10));
    }

    #[Test]
    public function it_caps_tv_rights_when_both_ranks_are_at_their_maximum(): void
    {
        $this->assertSame(40_000_000, (new TvRightsCalculator)->calculate(10000, 20));
    }

    #[Test]
    public function it_clamps_ranks_to_the_supported_normalized_range(): void
    {
        $calculator = new TvRightsCalculator;

        $this->assertSame(0, $calculator->calculate(-1, -1));
        $this->assertSame(40_000_000, $calculator->calculate(20000, 50));
    }
}
