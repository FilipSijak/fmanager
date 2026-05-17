<?php

namespace Tests\Integration\Services\TransferService\TransferStatusUpdates;

use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Models\PlayerContract;
use App\Models\Transfer;
use App\Models\TransferContractOffer;
use App\Models\TransferFinancialDetails;
use App\Repositories\PlayerRepository;
use App\Repositories\TransferRepository;
use App\Services\TransferService\TransferConsiderations\ClubConsideration;
use App\Services\TransferService\TransferConsiderations\PlayerConsideration;
use App\Services\TransferService\TransferConsiderations\TransferConsiderations;
use App\Services\TransferService\TransferStatusTypes;
use App\Services\TransferService\TransferStatusUpdates;
use App\Services\TransferService\TransferTypes;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PermanentTransferWaitingPlayerStatusUpdatesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_moves_an_accepted_player_contract_offer_to_waiting_paperwork(): void
    {
        $transfer = $this->createWaitingPlayerTransfer([
            'current_salary' => 1000,
            'offer_salary' => 1000,
        ]);

        app()->make(TransferStatusUpdates::class)->permanentTransferUpdates($transfer);

        $this->assertSame(
            TransferStatusTypes::WAITING_PAPERWORK->value,
            $transfer->refresh()->transfer_status
        );
    }

    #[Test]
    public function it_moves_an_insufficient_player_contract_offer_to_player_counteroffer(): void
    {
        $transfer = $this->createWaitingPlayerTransfer([
            'current_salary' => 1000,
            'offer_salary' => 500,
        ]);

        app()->make(TransferStatusUpdates::class)->permanentTransferUpdates($transfer);

        $this->assertSame(
            TransferStatusTypes::PLAYER_COUNTEROFFER->value,
            $transfer->refresh()->transfer_status
        );
        $this->assertSame(1, TransferContractOffer::where('transfer_id', $transfer->id)->first()->counter_offered);
    }

    #[Test]
    public function it_moves_to_player_declined_when_the_counteroffer_limit_has_been_reached(): void
    {
        $transfer = $this->createWaitingPlayerTransfer([
            'current_salary' => 1000,
            'offer_salary' => 500,
            'counter_offered' => 2,
        ]);

        app()->make(TransferStatusUpdates::class)->permanentTransferUpdates($transfer);

        $this->assertSame(
            TransferStatusTypes::PLAYER_DECLINED->value,
            $transfer->refresh()->transfer_status
        );
        $this->assertSame(2, TransferContractOffer::where('transfer_id', $transfer->id)->first()->counter_offered);
    }

    #[Test]
    public function it_moves_waiting_paperwork_to_move_player_when_the_medical_passes(): void
    {
        Instance::factory()->create([
            'id' => 1,
            'instance_date' => '2024-08-01',
        ]);

        $transfer = $this->createWaitingPlayerTransfer();
        $transfer->transfer_status = TransferStatusTypes::WAITING_PAPERWORK->value;
        $transfer->save();

        $this->transferStatusUpdates()->permanentTransferUpdates($transfer);

        $this->assertSame(
            TransferStatusTypes::MOVE_PLAYER->value,
            $transfer->refresh()->transfer_status
        );
    }

    #[Test]
    public function it_keeps_a_transfer_waiting_when_the_transfer_window_is_closed(): void
    {
        Instance::factory()->create([
            'id' => 1,
            'instance_date' => '2024-03-01',
        ]);

        $transfer = Transfer::factory()->create([
            'instance_id' => 1,
            'transfer_status' => TransferStatusTypes::WAITING_TRANSFER_WINDOW->value,
            'transfer_date' => '2024-07-01',
        ]);

        $transferRepository = $this->createMock(TransferRepository::class);
        $transferRepository->expects($this->never())->method('transferPlayerToNewClub');

        $this->transferStatusUpdatesWithRepository($transferRepository)->permanentTransferUpdates($transfer);
    }

    #[Test]
    public function it_moves_a_waiting_transfer_when_the_transfer_window_is_open(): void
    {
        Instance::factory()->create([
            'id' => 1,
            'instance_date' => '2024-01-10',
        ]);

        $transfer = Transfer::factory()->create([
            'instance_id' => 1,
            'transfer_status' => TransferStatusTypes::WAITING_TRANSFER_WINDOW->value,
            'transfer_date' => '2024-07-01',
        ]);

        $transferRepository = $this->createMock(TransferRepository::class);
        $transferRepository->expects($this->once())
            ->method('transferPlayerToNewClub')
            ->with($transfer);

        $this->transferStatusUpdatesWithRepository($transferRepository)->permanentTransferUpdates($transfer);
    }

    #[Test]
    public function it_moves_a_waiting_transfer_when_its_transfer_window_date_has_arrived(): void
    {
        Instance::factory()->create([
            'id' => 1,
            'instance_date' => '2024-07-01',
        ]);

        $transfer = Transfer::factory()->create([
            'instance_id' => 1,
            'transfer_status' => TransferStatusTypes::WAITING_TRANSFER_WINDOW->value,
            'transfer_date' => '2024-07-01',
        ]);

        $transferRepository = $this->createMock(TransferRepository::class);
        $transferRepository->expects($this->once())
            ->method('transferPlayerToNewClub')
            ->with($transfer);

        $this->transferStatusUpdatesWithRepository($transferRepository)->permanentTransferUpdates($transfer);
    }

    #[Test]
    public function it_removes_failed_transfers_and_their_offers(): void
    {
        $transfer = Transfer::factory()->create([
            'transfer_status' => TransferStatusTypes::TRANSFER_FAILED->value,
        ]);

        TransferContractOffer::factory()->create(['transfer_id' => $transfer->id]);
        TransferFinancialDetails::factory()->create(['transfer_id' => $transfer->id]);

        $this->transferStatusUpdates()->permanentTransferUpdates($transfer);

        $this->assertDatabaseMissing('transfers', ['id' => $transfer->id]);
        $this->assertDatabaseMissing('transfer_contract_offers', ['transfer_id' => $transfer->id]);
        $this->assertDatabaseMissing('transfer_financial_details', ['transfer_id' => $transfer->id]);
    }

    #[Test]
    public function it_does_not_remove_completed_transfers_with_the_failed_transfer_cleanup(): void
    {
        $transfer = Transfer::factory()->create([
            'transfer_status' => TransferStatusTypes::TRANSFER_COMPLETED->value,
        ]);

        $this->transferStatusUpdates()->permanentTransferUpdates($transfer);

        $this->assertDatabaseHas('transfers', ['id' => $transfer->id]);
    }

    #[Test]
    public function it_accepts_a_player_counteroffer_within_ten_percent_when_the_buying_club_has_a_position_shortage(): void
    {
        $transfer = $this->createPlayerCounterOfferTransfer();

        $baselineOffer = app()->make(PlayerRepository::class)
            ->contractBasedOnPotential($transfer->player()->first());

        TransferContractOffer::factory()->create(array_merge(
            ['transfer_id' => $transfer->id],
            $this->counterOfferFromBaseline($baselineOffer, 1.05)
        ));

        $this->transferStatusUpdates()->permanentTransferUpdates($transfer);

        $this->assertSame(
            TransferStatusTypes::WAITING_PAPERWORK->value,
            $transfer->refresh()->transfer_status
        );
    }

    #[Test]
    public function it_fails_a_player_counteroffer_above_ten_percent(): void
    {
        $transfer = $this->createPlayerCounterOfferTransfer();

        $baselineOffer = app()->make(PlayerRepository::class)
            ->contractBasedOnPotential($transfer->player()->first());

        TransferContractOffer::factory()->create(array_merge(
            ['transfer_id' => $transfer->id],
            $this->counterOfferFromBaseline($baselineOffer, 1.11)
        ));

        $this->transferStatusUpdates()->permanentTransferUpdates($transfer);

        $this->assertSame(
            TransferStatusTypes::TRANSFER_FAILED->value,
            $transfer->refresh()->transfer_status
        );
    }

    private function transferStatusUpdates(): TransferStatusUpdates
    {
        $transferRepository = app()->make(TransferRepository::class);
        $transferRepository->setSeasonId(1);
        $transferRepository->setInstanceId(1);

        $transferConsiderations = new TransferConsiderations(
            app()->make(PlayerConsideration::class),
            app()->make(ClubConsideration::class),
            $transferRepository
        );

        return new TransferStatusUpdates($transferConsiderations, $transferRepository);
    }

    private function transferStatusUpdatesWithRepository(TransferRepository $transferRepository): TransferStatusUpdates
    {
        return new TransferStatusUpdates(
            $this->createMock(TransferConsiderations::class),
            $transferRepository
        );
    }

    private function createWaitingPlayerTransfer(array $attributes = []): Transfer
    {
        $attributes = array_merge([
            'current_salary' => 1000,
            'offer_salary' => 1000,
            'counter_offered' => 0,
        ], $attributes);

        $buyingClub = $this->createClub(1);
        $sellingClub = $this->createClub(2);
        $player = $this->createPlayerWithContract($sellingClub->id, $attributes['current_salary']);

        $transfer = Transfer::factory()->create([
            'season_id' => 1,
            'source_club_id' => $buyingClub->id,
            'target_club_id' => $sellingClub->id,
            'player_id' => $player->id,
            'transfer_status' => TransferStatusTypes::WAITING_PLAYER->value,
            'transfer_type' => TransferTypes::PERMANENT_TRANSFER,
        ]);

        TransferContractOffer::factory()->create([
            'transfer_id' => $transfer->id,
            'salary' => $attributes['offer_salary'],
            'appearance' => 0,
            'assist' => 0,
            'goal' => 0,
            'clean_sheet' => 0,
            'league' => 0,
            'promotion' => 0,
            'cup' => 0,
            'el' => 0,
            'cl' => 0,
            'loan_contribution_pc' => 0,
            'counter_offered' => $attributes['counter_offered'],
        ]);

        return $transfer;
    }

    private function createPlayerCounterOfferTransfer(): Transfer
    {
        $buyingClub = $this->createClub(1);
        $sellingClub = $this->createClub(2);
        $player = $this->createPlayerWithContract($sellingClub->id, 1000);

        return Transfer::factory()->create([
            'season_id' => 1,
            'source_club_id' => $buyingClub->id,
            'target_club_id' => $sellingClub->id,
            'player_id' => $player->id,
            'transfer_status' => TransferStatusTypes::PLAYER_COUNTEROFFER->value,
            'transfer_type' => TransferTypes::PERMANENT_TRANSFER,
        ]);
    }

    private function counterOfferFromBaseline(array $baselineOffer, float $multiplier): array
    {
        return [
            'transfer_fee' => (int) round($baselineOffer['transfer_fee'] * $multiplier),
            'salary' => (int) round($baselineOffer['salary'] * $multiplier),
            'appearance' => (int) round($baselineOffer['appearance'] * $multiplier),
            'assist' => (int) round($baselineOffer['assist'] * $multiplier),
            'goal' => (int) round($baselineOffer['goal'] * $multiplier),
            'clean_sheet' => (int) round($baselineOffer['clean_sheet'] * $multiplier),
            'league' => (int) round($baselineOffer['league'] * $multiplier),
            'promotion' => (int) round($baselineOffer['promotion'] * $multiplier),
            'cup' => (int) round($baselineOffer['cup'] * $multiplier),
            'el' => (int) round($baselineOffer['el'] * $multiplier),
            'cl' => (int) round($baselineOffer['cl'] * $multiplier),
            'pc_promotion_salary_raise' => $baselineOffer['salary_raise'],
            'pc_demotion_salary_cut' => $baselineOffer['demotion'],
        ];
    }

    private function createClub(int $id): Club
    {
        return Club::factory()->create([
            'id' => $id,
            'instance_id' => 1,
            'rank' => 10,
            'rank_academy' => 10,
            'rank_training' => 10,
        ]);
    }

    private function createPlayerWithContract(int $clubId, int $salary): Player
    {
        $contract = PlayerContract::factory()->create([
            'contract_start' => Carbon::now()->subYear(),
            'contract_end' => Carbon::now()->addYear(),
            'salary' => $salary,
            'appearance' => 0,
            'assist' => 0,
            'goal' => 0,
            'clean_sheet' => 0,
            'league' => 0,
            'promotion' => 0,
            'cup' => 0,
            'el' => 0,
            'cl' => 0,
            'loan_contribution_pc' => 0,
        ]);

        return Player::factory()->create([
            'club_id' => $clubId,
            'contract_id' => $contract->id,
            'instance_id' => 1,
            'value' => 10000,
            'marketing_rank' => 100,
            'potential' => 100,
            'max_potential' => 120,
            'ambition' => 10,
            'loyalty' => 10,
            'position' => 'CB',
        ]);
    }
}
