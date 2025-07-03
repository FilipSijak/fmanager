<?php

namespace Tests\Integration\Services\TransferService;

use App\Models\Account;
use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Models\PlayerContract;
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
        Account::factory()->create(['club_id' => $buyingClub->id, 'transfer_budget' => 1000000]);
        $sellingClub = Club::factory()->create(['id' => 1]);
        $listedPlayer = Player::factory()->create(
            [
                'club_id' => $sellingClub->id,
                'position' => $position,
                'potential' => 120,
                'instance_id' => 1,
                'value' => 50000,
            ]
        );

        // highest potential player in the same position from buying club
        Player::factory()->create(
            [
                'club_id' => $buyingClub->id,
                'position' => $position,
                'potential' => 100,
            ]
        );

        TransferList::factory()->create(
            ['player_id' =>  $listedPlayer->id, 'club_id' => $sellingClub->id, 'transfer_type' => TransferTypes::PERMANENT_TRANSFER]
        );

        $transferSearchRepository = new TransferSearchRepository();
        $transferSearchRepository->setInstanceId(1);
        $clubBudget = $buyingClub->account->transfer_budget;

        $player = $transferSearchRepository->findListedPlayer($buyingClub, TransferTypes::PERMANENT_TRANSFER, $position, $clubBudget);

        $this->assertInstanceOf(Player::class, $player);
        $this->assertEquals($listedPlayer->id, $player->id);
    }

    /** @test */
    public function itCanFindLuxuryPlayer()
    {
        $position = 'CB';
        $buyingClub = Club::factory()->create(['id' => 2]);
        Account::factory()->create(['club_id' => $buyingClub->id, 'transfer_budget' => 60000000]);
        $sellingClub = Club::factory()->create(['id' => 1]);
        $luxuryPlayer = Player::factory()->create(
            [
                'club_id' => $sellingClub->id,
                'position' => $position,
                'potential' => 120,
                'instance_id' => 1,
                'value' => 500000,
            ]
        );

        // highest potential player in the same position from buying club
        Player::factory()->create(
            [
                'club_id' => $buyingClub->id,
                'position' => $position,
                'potential' => 100,
                'instance_id' => 1,
            ]
        );

        $transferSearchRepository = new TransferSearchRepository();
        $transferSearchRepository->setInstanceId(1);
        $clubBudget = $buyingClub->account->transfer_budget;

        $player = $transferSearchRepository->findLuxuryPlayersForPosition($buyingClub, $position, $clubBudget);

        $this->assertInstanceOf(Player::class, $player);
        $this->assertEquals($luxuryPlayer->id, $player->id);
    }

    /** @test */
    public function itCanFindPlayerWithUnprotectedContract()
    {
        $position = 'CB';
        $buyingClub = Club::factory()->create(['id' => 2, 'rank' => 10]);
        Account::factory()->create(['club_id' => $buyingClub->id, 'transfer_budget' => 1000000]);
        $sellingClub = Club::factory()->create(['id' => 1]);
        $unprotectedPlayer = Player::factory()->create(
            [
                'club_id' => $sellingClub->id,
                'position' => $position,
                'potential' => 120,
                'instance_id' => 1,
                'value' => 50000,
                'contract_id' => 1,
            ]
        );
        PlayerContract::factory()->create(['id' => 1, 'contract_end' => '2024-07-20']);
        Instance::factory()->create(['id' =>'1', 'instance_date' => '2023-08-20']);

        $transferSearchRepository = new TransferSearchRepository();
        $transferSearchRepository->setInstanceId(1);
        $clubBudget = $buyingClub->account->transfer_budget;

        $player = $transferSearchRepository->findPlayersWithUnprotectedContracts($buyingClub, $position, $clubBudget);

        $this->assertInstanceOf(Player::class, $player);
        $this->assertEquals($unprotectedPlayer->id, $player->id);
    }
}
