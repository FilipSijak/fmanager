<?php

namespace Tests\Unit;

use App\Services\FinanceService\Domain\CompetitionPrizeCalculator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CompetitionPrizeCalculatorTest extends TestCase
{
    #[Test]
    public function it_pays_twenty_million_for_the_highest_ranked_league(): void
    {
        $this->assertSame(20_000_000, (new CompetitionPrizeCalculator)->calculateLeaguePrize(10_000));
    }

    #[Test]
    public function it_scales_league_prizes_by_competition_rank(): void
    {
        $this->assertSame(10_000_000, (new CompetitionPrizeCalculator)->calculateLeaguePrize(5_000));
    }

    #[Test]
    public function it_clamps_league_prizes_to_the_supported_rank_range(): void
    {
        $calculator = new CompetitionPrizeCalculator;

        $this->assertSame(20_000_000, $calculator->calculateLeaguePrize(20_000));
        $this->assertSame(0, $calculator->calculateLeaguePrize(-1));
    }

    #[Test]
    public function it_pays_continental_round_win_and_draw_rewards_at_the_maximum_rank(): void
    {
        $this->assertSame(7_000_000, (new CompetitionPrizeCalculator)->calculateContinentalPrize(10_000, 1, 1, 1));
    }

    #[Test]
    public function it_scales_continental_rewards_for_lower_ranked_competitions(): void
    {
        $this->assertSame(5_880_000, (new CompetitionPrizeCalculator)->calculateContinentalPrize(8_400, 1, 1, 1));
    }

    #[Test]
    public function it_calculates_match_rewards_by_result_at_the_maximum_rank(): void
    {
        $calculator = new CompetitionPrizeCalculator;

        $this->assertSame(2_000_000, $calculator->calculateContinentalMatchPrize(10_000, 'win'));
        $this->assertSame(1_000_000, $calculator->calculateContinentalMatchPrize(10_000, 'draw'));
        $this->assertSame(0, $calculator->calculateContinentalMatchPrize(10_000, 'loss'));
    }

    #[Test]
    public function it_calculates_a_four_million_round_reward_at_the_maximum_rank(): void
    {
        $this->assertSame(4_000_000, (new CompetitionPrizeCalculator)->calculateContinentalRoundPrize(10_000));
    }
}
