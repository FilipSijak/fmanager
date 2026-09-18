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
}
