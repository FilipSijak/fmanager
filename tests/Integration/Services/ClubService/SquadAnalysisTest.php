<?php

namespace Tests\Integration\Services\ClubService;

use App\Models\Club;
use App\Models\Player;
use App\Services\TransferService\TransferEntityAnalysis\SquadTransferAnalysis;
use App\Services\InstanceService\InstanceData\InitialSeed;
use App\Services\PersonService\PersonConfig\Player\PlayerPositionConfig;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SquadAnalysisTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_can_check_club_player_shortage_by_position()
    {
        $squadAnalysis = new SquadTransferAnalysis();

        (new DatabaseSeeder())->run();
        $init = new InitialSeed();
        $init->seedFromBaseTables(1);

        $club = Club::where('instance_id', 1)->firstOrFail();

        Player::factory()
              ->count(5)
              ->sequence(function (Sequence $sequence) use ($club) {
                return [
                    'club_id' => $club->id,
                    'position' => PlayerPositionConfig::PLAYER_POSITIONS[$sequence->index + 1],
                ];
              })
              ->create();

        Player::factory()
              ->count(4)
              ->sequence(function () use ($club) {
                  return [
                      'club_id' => $club->id,
                      'position' => 'CB',
                  ];
              })
              ->create();

        $positionShortage = $squadAnalysis->optimalNumbersCheckByPosition($club);

        $this->assertEquals(-3, $positionShortage['CB']);
        $this->assertEquals(-2, $positionShortage['LB']);
    }
}
