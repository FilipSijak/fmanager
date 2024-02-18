<?php

namespace Tests\Integration\Services\ClubService;

use App\Models\Club;
use App\Models\Player;
use App\Services\ClubService\SquadAnalysis\SquadAnalysis;
use App\Services\InstanceService\InstanceData\InitialSeed;
use App\Services\PersonService\PersonConfig\Player\PlayerPositionConfig;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\DatabaseMigrations;

use Tests\TestCase;

class SquadAnalysisTest extends TestCase
{
    use DatabaseMigrations;

    /** @test */
    public function it_can_check_club_player_shortage_by_position()
    {
        $squadAnalysis = new SquadAnalysis();

        (new DatabaseSeeder())->run();
        $init = new InitialSeed();
        $init->seedFromBaseTables(1);

        $club = Club::find(1);

        Player::factory()
              ->count(5)
              ->sequence(function (Sequence $sequence) {
                return ['position' => PlayerPositionConfig::PLAYER_POSITIONS[$sequence->index - 1]];
              })
              ->create();

        Player::factory()
              ->count(4)
              ->sequence(function (Sequence $sequence) {
                  return ['position' => 'CB'];
              })
              ->create();

        $positionShortage = $squadAnalysis->optimalNumbersCheckByPosition($club);

        $this->assertEquals(-3, $positionShortage['CB']);
        $this->assertEquals(-2, $positionShortage['LB']);
    }
}
