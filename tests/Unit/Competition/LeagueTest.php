<?php

namespace Tests\Unit\Competition;

use App\Services\CompetitionService\Competitions\League;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LeagueTest extends TestCase
{
    #[Test]
    public function it_can_generate_all_season_games_for_x_clubs()
    {
        $clubs = [1,2,3,4,5,6,7,8];
        $league = new League();
        $games = $league->generateLeagueGames($clubs);

        $totalGames = count($clubs) * (count($clubs) - 1);

        $this->assertEquals(count($games), $totalGames);
    }

    #[Test]
    public function it_throws_exception_for_an_odd_number_of_clubs()
    {
        $clubs = [1,2,3,4,5,6,7];
        $league = new League();

        $this->expectException(\UnexpectedValueException::class);
        $league->generateLeagueGames($clubs);
    }

    #[Test]
    public function it_throws_exception_for_three_clubs_only()
    {
        $clubs = [1,2,3];
        $league = new League();

        $this->expectException(\UnexpectedValueException::class);
        $league->generateLeagueGames($clubs);
    }

    #[Test]
    public function it_throws_exception_for_more_than_twenty_clubs()
    {
        $clubs = [];
        $league = new League();

        for ($i = 1; $i < 22; $i++) {
            $clubs[] = $i;
        }

        $this->expectException(\UnexpectedValueException::class);
        $league->generateLeagueGames($clubs);
    }
}

