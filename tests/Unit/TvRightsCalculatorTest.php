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
    public function it_calculates_total_games_for_each_competition_format(): void
    {
        $calculator = new TvRightsCalculator;

        $this->assertSame(38, $calculator->totalGamesForClub('league', 20, null));
        $this->assertSame(13, $calculator->totalGamesForClub('tournament', 32, 1));
        $this->assertSame(3, $calculator->totalGamesForClub('tournament', 4, 0));
    }

    #[Test]
    public function it_pays_tournament_tv_rights_proportionally_to_games_played(): void
    {
        $calculator = new TvRightsCalculator;

        $this->assertSame(18_461_538, $calculator->calculateForCompetition(10000, 20, 'tournament', 32, 1, 6));
        $this->assertSame(40_000_000, $calculator->calculateForCompetition(10000, 20, 'tournament', 32, 1, 13));
    }

    #[Test]
    public function league_tv_rights_are_not_reduced_for_partial_game_input(): void
    {
        $this->assertSame(40_000_000, (new TvRightsCalculator)->calculateForCompetition(10000, 20, 'league', 20, null, 1));
    }

    #[Test]
    public function it_clamps_tournament_games_played_to_the_total(): void
    {
        $this->assertSame(40_000_000, (new TvRightsCalculator)->calculateForCompetition(10000, 20, 'tournament', 4, 0, 99));
    }
}
