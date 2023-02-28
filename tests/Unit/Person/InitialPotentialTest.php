<?php

namespace Tests\Unit\Person;

use App\Services\PersonService\GeneratePeople\PlayerCreateConfig;
use App\Services\PersonService\GeneratePeople\PlayerPotential;
use Tests\TestCase;

class InitialPotentialTest extends TestCase
{
    /** @test */
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

        $this->assertCount(PlayerCreateConfig::PLAYER_COUNT, $playerList);
        $this->assertEquals(count($cbs), PlayerCreateConfig::POSITION_COUNT['CB']);
        $this->assertEquals(count($st), PlayerCreateConfig::POSITION_COUNT['ST']);
    }
}
