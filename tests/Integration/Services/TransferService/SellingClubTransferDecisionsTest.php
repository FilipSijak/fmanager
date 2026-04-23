<?php

namespace Tests\Integration\Services\TransferService;

use App\Models\Account;
use App\Models\Club;
use App\Models\Player;
use App\Models\Transfer;
use App\Models\TransferContractOffer;
use App\Models\TransferFinancialDetails;
use App\Repositories\TransferRepository;
use App\Services\TransferService\TransferConsiderations\ClubConsideration;
use App\Services\TransferService\TransferConsiderations\PlayerConsideration;
use App\Services\TransferService\TransferConsiderations\TransferConsiderations;
use App\Services\TransferService\TransferStatusTypes;
use App\Services\TransferService\TransferTypes;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SellingClubTransferDecisionsTest extends TestCase
{
    use DatabaseMigrations;

    #[Test]
    public function it_accepts_a_non_key_player_offer_at_player_value(): void
    {
        $transferConsiderations = $this->transferConsiderations();
        $buyingClub = $this->createClub(2);
        $sellingClub = $this->createClub(1);

        $player = $this->createPlayer($sellingClub->id, [
            'value' => 10000,
            'potential' => 80,
            'position' => 'ST',
        ]);
        $this->createPlayer($sellingClub->id, ['potential' => 180, 'position' => 'CB']);
        $this->createPlayer($sellingClub->id, ['potential' => 170, 'position' => 'CB']);
        $this->createPlayer($sellingClub->id, ['potential' => 160, 'position' => 'CB']);
        $this->createPlayer($sellingClub->id, ['potential' => 70, 'position' => 'ST']);
        $this->createPlayer($sellingClub->id, ['potential' => 60, 'position' => 'ST']);

        $transfer = $this->createTransfer($buyingClub->id, $sellingClub->id, $player->id, 10000);

        $transferConsiderations->sellingClubDecision($transfer);

        $this->assertSame(
            TransferStatusTypes::WAITING_PLAYER->value,
            $transfer->refresh()->transfer_status
        );
        $this->assertDatabaseHas('transfer_contract_offers', [
            'transfer_id' => $transfer->id,
        ]);
    }

    #[Test]
    public function it_counter_offers_for_a_key_player_when_the_offer_is_below_the_maximum_value(): void
    {
        $transferConsiderations = $this->transferConsiderations();
        $buyingClub = $this->createClub(2);
        $sellingClub = $this->createClub(1);

        $player = $this->createPlayer($sellingClub->id, [
            'value' => 10000,
            'potential' => 200,
        ]);
        $transfer = $this->createTransfer($buyingClub->id, $sellingClub->id, $player->id, 12500);

        $transferConsiderations->sellingClubDecision($transfer);

        $this->assertSame(
            TransferStatusTypes::TARGET_CLUB_COUNTEROFFER->value,
            $transfer->refresh()->transfer_status
        );
        $this->assertSame(13000, $transfer->transferFinancialDetails()->first()->amount);
        $this->assertSame(0, TransferContractOffer::where('transfer_id', $transfer->id)->count());
    }

    #[Test]
    public function it_declines_a_non_key_player_offer_below_player_value(): void
    {
        $transferConsiderations = $this->transferConsiderations();
        $buyingClub = $this->createClub(2);
        $sellingClub = $this->createClub(1);

        $player = $this->createPlayer($sellingClub->id, [
            'value' => 10000,
            'potential' => 80,
            'position' => 'ST',
        ]);
        $this->createPlayer($sellingClub->id, ['potential' => 180, 'position' => 'CB']);
        $this->createPlayer($sellingClub->id, ['potential' => 170, 'position' => 'CB']);
        $this->createPlayer($sellingClub->id, ['potential' => 160, 'position' => 'CB']);
        $this->createPlayer($sellingClub->id, ['potential' => 70, 'position' => 'ST']);
        $this->createPlayer($sellingClub->id, ['potential' => 60, 'position' => 'ST']);

        $transfer = $this->createTransfer($buyingClub->id, $sellingClub->id, $player->id, 9000);

        $transferConsiderations->sellingClubDecision($transfer);

        $this->assertSame(
            TransferStatusTypes::TARGET_CLUB_DECLINED->value,
            $transfer->refresh()->transfer_status
        );
        $this->assertSame(0, TransferContractOffer::where('transfer_id', $transfer->id)->count());
    }

    private function transferConsiderations(): TransferConsiderations
    {
        return new TransferConsiderations(
            app()->make(PlayerConsideration::class),
            app()->make(ClubConsideration::class),
            app()->make(TransferRepository::class)
        );
    }

    private function createClub(int $id): Club
    {
        $club = Club::factory()->create([
            'id' => $id,
            'instance_id' => 1,
            'rank' => 10,
            'rank_academy' => 10,
            'rank_training' => 10,
        ]);

        Account::factory()->create([
            'club_id' => $club->id,
            'balance' => 68000000,
            'future_balance' => 68000000,
        ]);

        return $club;
    }

    private function createPlayer(int $clubId, array $attributes = []): Player
    {
        return Player::factory()->create(array_merge([
            'club_id' => $clubId,
            'instance_id' => 1,
            'value' => 10000,
            'marketing_rank' => 100,
            'potential' => 100,
            'max_potential' => 120,
            'ambition' => 10,
            'loyalty' => 10,
            'position' => 'CB',
        ], $attributes));
    }

    private function createTransfer(
        int $buyingClubId,
        int $sellingClubId,
        int $playerId,
        int $amount
    ): Transfer {
        $transfer = Transfer::factory()->create([
            'season_id' => 1,
            'source_club_id' => $buyingClubId,
            'target_club_id' => $sellingClubId,
            'player_id' => $playerId,
            'transfer_status' => TransferStatusTypes::WAITING_TARGET_CLUB->value,
            'transfer_type' => TransferTypes::PERMANENT_TRANSFER,
        ]);

        TransferFinancialDetails::factory()->create([
            'transfer_id' => $transfer->id,
            'amount' => $amount,
            'installments' => 0,
        ]);

        return $transfer;
    }
}
