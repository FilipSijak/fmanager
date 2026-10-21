<?php

namespace Tests\Unit;

use App\Services\FinanceService\Domain\SponsorshipCalculator;
use App\Services\FinanceService\Domain\SponsorshipOfferCalculator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SponsorshipOfferCalculatorTest extends TestCase
{
    #[Test]
    public function offers_and_negotiation_budgets_follow_club_rank_with_a_thirty_million_cap(): void
    {
        $calculator = new SponsorshipOfferCalculator(new SponsorshipCalculator);
        foreach ([[0, 0, 0], [1, 3750, 4125], [10, 3750000, 4125000], [20, 30000000, 30000000]] as [$rank, $initial, $maximum]) {
            $this->assertSame($initial, $calculator->initialAmount($rank, $rank));
            $this->assertSame($maximum, $calculator->maximumAmount($rank, $rank));
        }
        $this->assertSame(3543750, $calculator->counterAmount(3375000, 4125000));
        $this->assertSame(4125000, $calculator->counterAmount(4100000, 4125000));
    }

    #[Test]
    public function the_offer_rank_is_the_midpoint_rounded_down_in_both_directions(): void
    {
        $calculator = new SponsorshipOfferCalculator(new SponsorshipCalculator);
        foreach ([[20, 17, 18, 21870000], [17, 20, 18, 21870000], [10, 7, 8, 1920000], [7, 10, 8, 1920000], [10, 9, 9, 2733750], [10, 11, 10, 3750000]] as [$club, $sponsor, $rank, $amount]) {
            $this->assertSame($rank, $calculator->offerRank($club, $sponsor));
            $this->assertSame($amount, $calculator->initialAmount($club, $sponsor));
        }
        $this->assertSame(24057000, $calculator->maximumAmount(20, 17));
    }

    #[Test]
    public function only_ranked_clubs_within_three_ranks_of_a_sponsor_are_eligible(): void
    {
        $calculator = new SponsorshipOfferCalculator(new SponsorshipCalculator);
        foreach ([[10, 7, true], [10, 13, true], [10, 6, false], [10, 14, false], [10, 10, true], [0, 1, false], [1, 0, false]] as [$club, $sponsor, $eligible]) {
            $this->assertSame($eligible, $calculator->isEligible($club, $sponsor));
        }
    }
}
