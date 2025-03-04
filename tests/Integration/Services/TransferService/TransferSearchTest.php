<?php

namespace Tests\Integration\Services\TransferService;

use App\Models\Club;
use App\Models\Player;
use App\Models\TransferList;
use App\Repositories\TransferSearchRepository;
use App\Services\TransferService\TransferTypes;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class TransferSearchTest extends TestCase
{
    use DatabaseMigrations;

    /** @test */
    public function itCanGetListedPlayer()
    {
        $position = 'CB';
        $buyingClub = Club::factory()->create(['id' => 2]);
        $listedPlayer = Player::factory()->create(
            [
                'club_id' => 1,
                'position' => $position,
                'potential' => 120,
            ]
        );

        Player::factory()->create(
            [
                'club_id' => $buyingClub->id,
                'position' => $position,
                'potential' => 100,
            ]
        );

        TransferList::factory()->create(
            ['player_id' =>  $listedPlayer->id, 'club_id' => $buyingClub->id, 'transfer_type' => TransferTypes::PERMANENT_TRANSFER]
        );

        $transferSearchRepository = new TransferSearchRepository();

        $result = $transferSearchRepository->getHighestListedPlayer($buyingClub, TransferTypes::PERMANENT_TRANSFER, $position);

        $this->assertInstanceOf(Player::class, $result);
    }
}
