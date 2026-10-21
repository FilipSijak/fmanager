<?php

namespace Tests\Unit;

use App\Services\FinanceService\Domain\SponsorshipCalculator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class SponsorshipCalculatorTest extends TestCase
{
    #[Test]
    #[TestWith([1, 3750])]
    #[TestWith([3, 101250])]
    #[TestWith([5, 468750])]
    #[TestWith([10, 3750000])]
    #[TestWith([15, 12656250])]
    #[TestWith([20, 30000000])]
    #[TestWith([50, 30000000])]
    #[TestWith([0, 0])]
    #[TestWith([-5, 0])]
    public function annual_values_follow_club_rank_and_never_exceed_thirty_million(int $rank, int $expected): void
    {
        $this->assertSame($expected, (new SponsorshipCalculator)->annualAmount($rank));
    }
}
