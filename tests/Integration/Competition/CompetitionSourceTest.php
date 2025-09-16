<?php

namespace Tests\Integration\Competition;

use App\Services\CompetitionService\Competitions\League;
use App\Services\CompetitionService\DataLayer\CompetitionDataSource;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CompetitionSourceTest extends TestCase
{
    #[Test]
    public function it_can_store_league_matches()
    {
        /*$competitionDataSource = new CompetitionDataSource();

        $clubs = [1,2,3,4];
        $competitionId = 1;
        $startDate = '15-08-2023';
        $gamesPerRound = count($clubs) / 2;
        $leagueFixtures = (new League())->generateLeagueGames($clubs);

        $competitionDataSource->storeLeagueGames($leagueFixtures, $competitionId, $startDate, $gamesPerRound);

        $this->assertDatabaseCount('games', 12);*/
    }
}
