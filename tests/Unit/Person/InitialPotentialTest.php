<?php

namespace Tests\Unit\Person;

use App\Services\ClubService\SquadAnalysis\SquadPlayersConfig;
use App\Services\PersonService\GeneratePeople\PlayerPotential;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InitialPotentialTest extends TestCase
{
    #[Test]
    public function it_can_create_player_potential_based_on_club_rank()
    {
        $clubAcademyRank = 15;
        $playerPotential = new PlayerPotential();

        $playerList = $playerPotential->getPlayerPotentialAndInitialPosition($clubAcademyRank);
        $cbs = array_filter($playerList, function ($v, $k) {
            return $v->position == 'CB';
        }, ARRAY_FILTER_USE_BOTH);

        $st = array_filter($playerList, function ($v, $k) {
            return $v->position == 'ST';
        }, ARRAY_FILTER_USE_BOTH);

        $this->assertCount(SquadPlayersConfig::PLAYER_COUNT, $playerList);
        $this->assertEquals(count($cbs), SquadPlayersConfig::POSITION_COUNT['CB']);
        $this->assertEquals(count($st), SquadPlayersConfig::POSITION_COUNT['ST']);
    }
}
