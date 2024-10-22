<?php

namespace Tests\Integration\Services\TransferService;

use App\Models\Player;
use App\Models\PlayerContract;
use App\Models\Season;
use App\Models\Transfer;
use App\Models\TransferContractOffer;
use App\Services\TransferService\TransferService;
use App\Services\TransferService\TransferStatusTypes;
use App\Services\TransferService\TransferTypes;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class TransferServiceTest extends TestCase
{
    use DatabaseMigrations;

    /** @test  */
    public function itIsAbleToProcessFreeTransfers()
    {
        $buyingClubId = 1;
        $player = Player::factory()->create(
            [
                'id' => 1,
                'club_id' => null,
            ]
        );

        $transfer = Transfer::factory()->create(
            [
                'id' => 1,
                'season_id' => 1,
                'source_club_id' => $buyingClubId,
                'player_id' => $player->id,
                'transfer_type' => TransferTypes::FREE_TRANSFER,
                'source_club_status' => TransferStatusTypes::MOVE_PLAYER,
            ]
        );

        TransferContractOffer::factory()->create(['transfer_id' => $transfer->id]);

        $transferService = app()->make(TransferService::class);

        $transferService->setSeasonId(1);
        $transferService->processTransferBids($transfer);

        //player has a new contract and a new club
        $player = Player::where('id', $player->id)->first();
        $this->assertEquals($player->club_id, $buyingClubId);

        $playerContract = PlayerContract::where('player_id', $player->id)->first();
        $this->assertEquals($player->id, $playerContract->player_id);

        // transfer contract offer was deleted
        $transferContractOffer = TransferContractOffer::where('transfer_id', $transfer->id)->first();
        $this->assertEquals(null, $transferContractOffer);
    }

    /** @test */
    public function isAbleToProcessPermanentTransfers()
    {

    }
}
