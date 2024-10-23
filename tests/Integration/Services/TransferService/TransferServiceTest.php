<?php

namespace Tests\Integration\Services\TransferService;

use App\Models\Account;
use App\Models\Club;
use App\Models\Player;
use App\Models\PlayerContract;
use App\Models\Season;
use App\Models\Transfer;
use App\Models\TransferContractOffer;
use App\Models\TransferFinancialDetails;
use App\Services\TransferService\TransferService;
use App\Services\TransferService\TransferStatusTypes;
use App\Services\TransferService\TransferTypes;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class TransferServiceTest extends TestCase
{
    use DatabaseMigrations;

    /** @test  */
    public function itIsAbleToCompleteFreeTransfers()
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
    public function isAbleToCompletePermanentTransferWithoutInstallments()
    {
        $buyingClubId = 1;
        $sellingClubId = 2;
        $player = Player::factory()->create(
            [
                'id' => 1,
                'club_id' => 2,
            ]
        );

        $transfer = $this->setupTransferBetweenTwoClubs($buyingClubId, $sellingClubId, $player->id, TransferTypes::PERMANENT_TRANSFER);
        $transferContractOffer = TransferContractOffer::factory()->create(['transfer_id' => $transfer->id, 'salary' => 20000]);
        $transferService = app()->make(TransferService::class);

        $transferService->setSeasonId(1);
        $transferService->processTransferBids();

        //player has a new contract and a new club
        $player = Player::where('id', $player->id)->first();
        $this->assertEquals($player->club_id, $buyingClubId);

        $playerContract = PlayerContract::where('player_id', $player->id)->first();
        $this->assertEquals($transferContractOffer->salary, $playerContract->salary);

        $sellingClubAccountAfterTransfer = Account::where('club_id', $sellingClubId)->first();
        $buyingClubAccountAfterTransfer = Account::where('club_id', $buyingClubId)->first();

        $this->assertEquals(20000, $sellingClubAccountAfterTransfer->balance);
        $this->assertEquals(0, $buyingClubAccountAfterTransfer->balance);

        $this->assertEquals(20000, $sellingClubAccountAfterTransfer->transfer_budget);
        $this->assertEquals(0, $buyingClubAccountAfterTransfer->transfer_budget);
    }

    /** @test */
    public function itIsAbleToCompleteTransfersWithInstallments()
    {
        $newClub = 1;
        $currentClub = 2;
        $player = Player::factory()->create(
            [
                'id' => 3,
                'club_id' => 2,
            ]
        );

        $transfer = $this->setupTransferBetweenTwoClubs($newClub, $currentClub, $player->id, TransferTypes::LOAN_TRANSFER);
        $transferService = app()->make(TransferService::class);

        $transferService->setSeasonId(1);
        $transferService->processTransferBids();
        $player = Player::where('id', $player->id)->first();

        $this->assertEquals($player->club_id, $currentClub);
        $this->assertEquals($player->loan_club_id, $newClub);
    }

    private function setupTransferBetweenTwoClubs(int $buyingClubId, int $sellingClubId, int $playerId, int $transferType)
    {
        Club::factory()->create(
            ['id' => 1]
        );

        Club::factory()->create(
            ['id' => 2]
        );

        $transfer = Transfer::factory()->create(
            [
                'season_id' => 1,
                'source_club_id' => $buyingClubId,
                'target_club_id' => $sellingClubId,
                'player_id' => $playerId,
                'transfer_type' => $transferType,
                'source_club_status' => TransferStatusTypes::MOVE_PLAYER,
            ]
        );

        if ($transferType == TransferTypes::PERMANENT_TRANSFER) {
            TransferFinancialDetails::factory()->create(
                [
                    'transfer_id' => $transfer->id,
                    'amount' => 10000,
                    'installments' => 0,
                ]
            );

            Account::factory()->create(
                [
                    'club_id' => $sellingClubId,
                    'balance' => 10000,
                    'transfer_budget' => 10000
                ]
            );

            Account::factory()->create(
                [
                    'club_id' => $buyingClubId,
                    'balance' => 10000,
                    'transfer_budget' => 10000
                ]
            );

            PlayerContract::factory()->create(
                [
                    'id' => 1,
                    'player_id' => $playerId,
                    'salary' => 10000,
                ]
            );
        }

        return $transfer;
    }
}
