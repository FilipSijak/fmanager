<?php

namespace Tests\Unit;

use App\Services\ClubService\Domain\BoardExpectationCalculator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

class BoardExpectationCalculatorTest extends TestCase
{
    #[Test]
    #[TestWith(['budget'])]
    #[TestWith(['transfer_budget'])]
    #[TestWith(['team_quality'])]
    #[TestWith(['club_rank'])]
    public function each_factor_can_independently_raise_or_lower_expectations(string $factor): void
    {
        $ordinary = ['budget' => 100, 'transfer_budget' => 100, 'team_quality' => 10.0, 'club_rank' => 10];
        $stronger = array_replace($ordinary, [$factor => $ordinary[$factor] * 2]);
        $calculator = new BoardExpectationCalculator;

        $this->assertSame(63, $calculator->score($stronger, [$ordinary, $stronger]));
        $this->assertSame(38, $calculator->score($ordinary, [$ordinary, $stronger]));
    }

    #[Test]
    public function identical_resources_and_a_single_club_produce_a_neutral_score(): void
    {
        $club = ['budget' => 0, 'transfer_budget' => 0, 'team_quality' => 0.0, 'club_rank' => 1];

        $this->assertSame(50, (new BoardExpectationCalculator)->score($club, [$club]));
        $this->assertSame(50, (new BoardExpectationCalculator)->score($club, [$club, $club]));
    }

    #[Test]
    #[TestWith([100, 20, 1])]
    #[TestWith([80, 20, 4])]
    #[TestWith([60, 20, 8])]
    #[TestWith([40, 20, 12])]
    #[TestWith([20, 20, 16])]
    #[TestWith([20, 1, 1])]
    #[TestWith([60, 7, 3])]
    public function league_targets_use_the_expectation_rating_and_the_actual_league_size(int $rating, int $size, int $position): void
    {
        $this->assertSame($position, (new BoardExpectationCalculator)->targetPosition($rating, $size));
    }
}
