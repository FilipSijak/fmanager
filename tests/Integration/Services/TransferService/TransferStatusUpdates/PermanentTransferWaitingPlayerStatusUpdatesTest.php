<?php

namespace Tests\Integration\Services\TransferService\TransferStatusUpdates;

use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Models\PlayerContract;
use App\Models\Transfer;
use App\Models\TransferContractOffer;
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
