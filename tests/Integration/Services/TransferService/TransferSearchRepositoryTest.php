<?php

namespace Tests\Integration\Services\TransferService;

use App\Models\Account;
use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Models\PlayerContract;
use App\Models\Transfer;
use App\Models\TransferList;
use App\Repositories\TransferSearchRepository;
use App\Services\TransferService\TransferTypes;
use App\Support\GameContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransferSearchRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
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

    #[Test]
    public function itCanFindPlayersListedForLoan()
    {
        $position = 'CB';
        $buyingClub = Club::factory()->create(['id' => 2]);
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

        TransferList::factory()->create(
            ['player_id' =>  $listedPlayer->id, 'club_id' => $sellingClub->id, 'transfer_type' => TransferTypes::LOAN_TRANSFER]
        );

        Player::factory()->create(
            [
                'club_id' => $buyingClub->id,
                'position' => $position,
                'potential' => 100,
            ]
        );

        $transferSearchRepository = new TransferSearchRepository();
        $transferSearchRepository->setInstanceId(1);

        $player = $transferSearchRepository->findListedLoanPlayer($buyingClub, $position);

        $this->assertInstanceOf(Player::class, $player);
        $this->assertEquals($listedPlayer->id, $player->id);
    }

    #[Test]
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

        $player = $transferSearchRepository->findLuxuryPlayerForPosition($buyingClub, $position, $clubBudget);

        $this->assertInstanceOf(Player::class, $player);
        $this->assertEquals($luxuryPlayer->id, $player->id);
    }

    #[Test]
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
        PlayerContract::factory()->create(['id' => 1, 'contract_end' => '2024-01-20']);
        Instance::factory()->create(['id' =>'1', 'instance_date' => '2023-08-20']);

        $transferSearchRepository = new TransferSearchRepository();
        $transferSearchRepository->setInstanceId(1);
        $clubBudget = $buyingClub->account->transfer_budget;

        $player = $transferSearchRepository->findPlayerWithUnprotectedContract($buyingClub, $position, $clubBudget);

        $this->assertInstanceOf(Player::class, $player);
        $this->assertEquals($unprotectedPlayer->id, $player->id);
    }

    #[Test]
    public function attributeSearchRejectsUnknownColumnsAndReturnsOnlyActiveInstancePlayers(): void
    {
        $repository = $this->repositoryWithContext('2024-07-01');
        $buyingClub = Club::factory()->create(['id' => 10, 'instance_id' => 1]);
        $sellingClub = Club::factory()->create(['id' => 11, 'instance_id' => 1]);
        $eligible = Player::factory()->create([
            'instance_id' => 1,
            'club_id' => $sellingClub->id,
            'pace' => 18,
            'potential' => 120,
        ]);
        Player::factory()->create([
            'instance_id' => 2,
            'club_id' => $sellingClub->id,
            'pace' => 20,
            'potential' => 200,
        ]);
        Player::factory()->create([
            'instance_id' => 1,
            'club_id' => $sellingClub->id,
            'pace' => 20,
            'potential' => 200,
            'is_retired' => true,
        ]);

        $players = $repository->findPlayersByAttributes($buyingClub, ['pace' => 18]);

        $this->assertSame([$eligible->id], $players->pluck('id')->all());

        $this->expectException(\InvalidArgumentException::class);
        $repository->findPlayersByAttributes($buyingClub, ['not_a_player_column' => 1]);
    }

    #[Test]
    public function positionSearchHandlesRecentOldAndOtherClubOffers(): void
    {
        $repository = $this->repositoryWithContext('2024-07-01');
        $buyingClub = Club::factory()->create(['id' => 20, 'instance_id' => 1, 'rank' => 10]);
        $otherBuyingClub = Club::factory()->create(['id' => 21, 'instance_id' => 1]);
        $sellingClub = Club::factory()->create(['id' => 22, 'instance_id' => 1]);
        $recent = Player::factory()->create([
            'instance_id' => 1, 'club_id' => $sellingClub->id, 'position' => 'CB', 'potential' => 150,
        ]);
        $old = Player::factory()->create([
            'instance_id' => 1, 'club_id' => $sellingClub->id, 'position' => 'CB', 'potential' => 140,
        ]);
        $otherClubOffer = Player::factory()->create([
            'instance_id' => 1, 'club_id' => $sellingClub->id, 'position' => 'CB', 'potential' => 130,
        ]);
        Player::factory()->create([
            'instance_id' => 2, 'club_id' => $sellingClub->id, 'position' => 'CB', 'potential' => 200,
        ]);

        Transfer::factory()->create([
            'instance_id' => 1, 'source_club_id' => $buyingClub->id,
            'player_id' => $recent->id, 'offer_date' => '2024-06-01',
        ]);
        Transfer::factory()->create([
            'instance_id' => 1, 'source_club_id' => $buyingClub->id,
            'player_id' => $old->id, 'offer_date' => '2022-06-30',
        ]);
        Transfer::factory()->create([
            'instance_id' => 1, 'source_club_id' => $otherBuyingClub->id,
            'player_id' => $otherClubOffer->id, 'offer_date' => '2024-06-01',
        ]);

        $players = $repository->findPlayersByPositionForClub($buyingClub, 'CB');

        $this->assertSame([$old->id, $otherClubOffer->id], $players->pluck('id')->all());
    }

    #[Test]
    public function listedAndFreeSearchesRespectEligibilityAndPosition(): void
    {
        $repository = $this->repositoryWithContext();
        $buyingClub = Club::factory()->create(['id' => 30, 'instance_id' => 1, 'rank' => 1]);
        $sellingClub = Club::factory()->create(['id' => 31, 'instance_id' => 1]);
        $activeContract = PlayerContract::factory()->create([
            'contract_start' => '2023-01-01', 'contract_end' => '2026-01-01',
        ]);
        Player::factory()->create([
            'instance_id' => 1, 'club_id' => $buyingClub->id,
            'contract_id' => $activeContract->id, 'position' => 'CB', 'potential' => 100,
        ]);
        $listed = Player::factory()->create([
            'instance_id' => 1, 'club_id' => $sellingClub->id,
            'contract_id' => $activeContract->id, 'position' => 'CB',
            'potential' => 120, 'value' => 50000,
        ]);
        $wrongPosition = Player::factory()->create([
            'instance_id' => 1, 'club_id' => $sellingClub->id,
            'contract_id' => $activeContract->id, 'position' => 'GK',
            'potential' => 200, 'value' => 50000,
        ]);
        $tooExpensive = Player::factory()->create([
            'instance_id' => 1, 'club_id' => $sellingClub->id,
            'contract_id' => $activeContract->id, 'position' => 'CB',
            'potential' => 190, 'value' => 100001,
        ]);
        foreach ([$listed, $wrongPosition, $tooExpensive] as $player) {
            TransferList::factory()->create([
                'player_id' => $player->id,
                'club_id' => $sellingClub->id,
                'transfer_type' => TransferTypes::PERMANENT_TRANSFER,
            ]);
        }

        $free = Player::factory()->create([
            'instance_id' => 1, 'club_id' => null, 'contract_id' => null,
            'position' => 'CB', 'potential' => 100,
        ]);
        Player::factory()->create([
            'instance_id' => 1, 'club_id' => null, 'contract_id' => null,
            'position' => 'GK', 'potential' => 200,
        ]);
        Player::factory()->create([
            'instance_id' => 2, 'club_id' => null, 'contract_id' => null,
            'position' => 'CB', 'potential' => 200,
        ]);

        $listedResult = $repository->findListedPlayer(
            $buyingClub,
            TransferTypes::PERMANENT_TRANSFER,
            'CB',
            100000
        );
        $freeResult = $repository->findFreePlayerForPosition($buyingClub, 'CB');

        $this->assertSame($listed->id, $listedResult?->id);
        $this->assertSame($free->id, $freeResult?->id);
    }

    #[Test]
    public function unprotectedContractSearchUsesInclusiveContextDateBoundariesAndStableOrdering(): void
    {
        $repository = $this->repositoryWithContext('2024-01-01');
        $buyingClub = Club::factory()->create(['id' => 40, 'instance_id' => 1, 'rank' => 1]);
        $sellingClub = Club::factory()->create(['id' => 41, 'instance_id' => 1]);
        $startContract = PlayerContract::factory()->create([
            'contract_start' => '2023-01-01', 'contract_end' => '2024-01-01',
        ]);
        $endContract = PlayerContract::factory()->create([
            'contract_start' => '2023-01-01', 'contract_end' => '2024-07-01',
        ]);
        $outsideContract = PlayerContract::factory()->create([
            'contract_start' => '2023-01-01', 'contract_end' => '2024-07-02',
        ]);
        $first = Player::factory()->create([
            'instance_id' => 1, 'club_id' => $sellingClub->id, 'contract_id' => $startContract->id,
            'position' => 'CB', 'potential' => 120,
        ]);
        $second = Player::factory()->create([
            'instance_id' => 1, 'club_id' => $sellingClub->id, 'contract_id' => $endContract->id,
            'position' => 'CB', 'potential' => 120,
        ]);
        Player::factory()->create([
            'instance_id' => 1, 'club_id' => $sellingClub->id, 'contract_id' => $outsideContract->id,
            'position' => 'CB', 'potential' => 200,
        ]);

        $player = $repository->findPlayerWithUnprotectedContract($buyingClub, 'CB', 1000000);

        $this->assertSame(min($first->id, $second->id), $player?->id);
    }

    #[Test]
    public function luxuryFreeSearchWorksWhenClubHasNoPlayerInThatPosition(): void
    {
        $repository = $this->repositoryWithContext();
        $club = Club::factory()->create(['id' => 50, 'instance_id' => 1, 'rank' => 1]);
        $freePlayer = Player::factory()->create([
            'instance_id' => 1, 'club_id' => null, 'contract_id' => null,
            'position' => 'CB', 'potential' => 100,
        ]);

        $player = $repository->findFreePlayerForPosition($club, 'CB', true);

        $this->assertSame($freePlayer->id, $player?->id);
    }

    private function repositoryWithContext(string $instanceDate = '2024-01-01'): TransferSearchRepository
    {
        app(GameContext::class)->set(1, null, $instanceDate);

        return new TransferSearchRepository;
    }
}
