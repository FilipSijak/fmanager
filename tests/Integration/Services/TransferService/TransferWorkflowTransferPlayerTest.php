<?php

namespace Tests\Integration\Services\TransferService;

use App\Models\Instance;
use App\Models\Account;
use App\Models\Club;
use App\Models\Player;
use App\Models\PlayerContract;
use App\Models\Transfer;
use App\Models\TransferContractOffer;
use App\Models\TransferFinancialDetails;
use App\Services\TransferService\TransferStatusTypes;
use App\Services\TransferService\TransferTypes;
use App\Services\TransferService\TransferWorkflow;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransferWorkflowTransferPlayerTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_moves_a_transfer_outside_the_window_to_waiting_transfer_window(): void
    {
        Instance::factory()->create([
            'id' => 1,
            'instance_date' => '2024-03-01',
        ]);

        $transfer = Transfer::factory()->create([
            'season_id' => 1,
            'transfer_status' => TransferStatusTypes::MOVE_PLAYER->value,
            'transfer_type' => TransferTypes::PERMANENT_TRANSFER,
        ]);

        $this->transferWorkflow()->transferPlayerToNewClub($transfer);

        $transfer->refresh();

        $this->assertSame(TransferStatusTypes::WAITING_TRANSFER_WINDOW->value, $transfer->transfer_status);
        $this->assertSame('2024-07-01', $transfer->transfer_date);
    }

    #[Test]
    public function it_uses_the_transfers_instance_date_when_checking_the_transfer_window(): void
    {
        Instance::factory()->create([
            'id' => 1,
            'instance_date' => '2024-07-01',
        ]);
        Instance::factory()->create([
            'id' => 2,
            'instance_date' => '2024-03-01',
        ]);

        $transfer = Transfer::factory()->create([
            'instance_id' => 2,
            'season_id' => 1,
            'transfer_status' => TransferStatusTypes::MOVE_PLAYER->value,
            'transfer_type' => TransferTypes::PERMANENT_TRANSFER,
        ]);

        $this->transferWorkflow()->transferPlayerToNewClub($transfer);

        $transfer->refresh();

        $this->assertSame(TransferStatusTypes::WAITING_TRANSFER_WINDOW->value, $transfer->transfer_status);
        $this->assertSame('2024-07-01', $transfer->transfer_date);
    }

    #[Test]
    public function it_fails_explicitly_when_a_transfer_contract_offer_is_missing(): void
    {
        Instance::factory()->create([
            'id' => 1,
            'instance_date' => '2024-07-01',
        ]);
        $sourceClub = Club::factory()->create(['id' => 10]);
        Account::factory()->create([
            'club_id' => $sourceClub->id,
            'transfer_budget' => 10000,
        ]);

        $transfer = Transfer::factory()->create([
            'source_club_id' => $sourceClub->id,
            'season_id' => 1,
            'transfer_status' => TransferStatusTypes::MOVE_PLAYER->value,
            'transfer_type' => TransferTypes::FREE_TRANSFER,
        ]);

        $this->expectException(ModelNotFoundException::class);

        $this->transferWorkflow()->transferPlayerToNewClub($transfer);
    }

    #[Test]
    public function it_completes_a_permanent_transfer(): void
    {
        Instance::factory()->create([
            'id' => 1,
            'instance_date' => '2024-07-01',
        ]);

        $buyingClub = $this->createClubWithAccount(10, 50000);
        $sellingClub = $this->createClubWithAccount(20, 50000);
        $player = $this->createPlayerWithContract($sellingClub->id, 1000);

        $transfer = $this->createMovePlayerTransfer(
            $buyingClub->id,
            $sellingClub->id,
            $player->id,
            TransferTypes::PERMANENT_TRANSFER
        );
        TransferFinancialDetails::factory()->create([
            'transfer_id' => $transfer->id,
            'amount' => 10000,
            'installments' => 0,
        ]);
        TransferContractOffer::factory()->create([
            'transfer_id' => $transfer->id,
            'transfer_fee' => 0,
            'salary' => 2500,
        ]);

        $this->transferWorkflow()->transferPlayerToNewClub($transfer);

        $this->assertSame(TransferStatusTypes::TRANSFER_COMPLETED->value, $transfer->refresh()->transfer_status);
        $this->assertSame($buyingClub->id, $player->refresh()->club_id);
        $this->assertSame(2500, $player->contract()->first()->salary);
        $this->assertDatabaseMissing('transfer_contract_offers', ['transfer_id' => $transfer->id]);

        $playerName = "{$player->first_name} {$player->last_name}";

        $this->assertDatabaseHas('news', [
            'instance_id' => 1,
            'season_id' => 1,
            'club_id' => $buyingClub->id,
            'title' => "{$playerName} joins {$buyingClub->name}",
            'content' => "{$buyingClub->name} have completed the signing of {$playerName} from {$sellingClub->name}.",
            'type' => 'transfer',
            'priority' => 5,
        ]);
        $this->assertDatabaseCount('news', 1);
    }

    #[Test]
    public function it_completes_a_loan_transfer(): void
    {
        Instance::factory()->create([
            'id' => 1,
            'instance_date' => '2024-07-01',
        ]);

        $loanClub = $this->createClubWithAccount(10, 0);
        $parentClub = $this->createClubWithAccount(20, 0);
        $player = $this->createPlayerWithContract($parentClub->id, 1000);

        $transfer = $this->createMovePlayerTransfer(
            $loanClub->id,
            $parentClub->id,
            $player->id,
            TransferTypes::LOAN_TRANSFER
        );
        TransferContractOffer::factory()->create(['transfer_id' => $transfer->id]);

        $this->transferWorkflow()->transferPlayerToNewClub($transfer);

        $this->assertSame(TransferStatusTypes::TRANSFER_COMPLETED->value, $transfer->refresh()->transfer_status);
        $this->assertSame($parentClub->id, $player->refresh()->club_id);
        $this->assertSame($loanClub->id, $player->loan_club_id);
        $this->assertDatabaseMissing('transfer_contract_offers', ['transfer_id' => $transfer->id]);
    }

    #[Test]
    public function it_completes_a_free_transfer_with_a_new_contract(): void
    {
        Instance::factory()->create([
            'id' => 1,
            'instance_date' => '2024-07-01',
        ]);

        $newClub = $this->createClubWithAccount(10, 50000);
        $oldClub = $this->createClubWithAccount(20, 50000);
        $player = $this->createPlayerWithContract($oldClub->id, 1000);
        $oldContractId = $player->contract_id;

        $transfer = $this->createMovePlayerTransfer(
            $newClub->id,
            null,
            $player->id,
            TransferTypes::FREE_TRANSFER
        );
        TransferContractOffer::factory()->create([
            'transfer_id' => $transfer->id,
            'transfer_fee' => 0,
            'salary' => 3000,
        ]);

        $this->transferWorkflow()->transferPlayerToNewClub($transfer);

        $player->refresh();

        $this->assertSame(TransferStatusTypes::TRANSFER_COMPLETED->value, $transfer->refresh()->transfer_status);
        $this->assertSame($newClub->id, $player->club_id);
        $this->assertNotSame($oldContractId, $player->contract_id);
        $this->assertSame(3000, $player->contract()->first()->salary);
        $this->assertDatabaseMissing('transfer_contract_offers', ['transfer_id' => $transfer->id]);

        $playerName = "{$player->first_name} {$player->last_name}";

        $this->assertDatabaseHas('news', [
            'instance_id' => 1,
            'season_id' => 1,
            'club_id' => $newClub->id,
            'title' => "{$playerName} joins {$newClub->name}",
            'content' => "{$newClub->name} have completed the signing of {$playerName} on a free transfer.",
            'type' => 'transfer',
            'priority' => 5,
        ]);
        $this->assertDatabaseCount('news', 1);
    }

    #[Test]
    public function it_fails_a_non_loan_transfer_when_the_buying_club_cannot_afford_it(): void
    {
        Instance::factory()->create([
            'id' => 1,
            'instance_date' => '2024-07-01',
        ]);

        $buyingClub = $this->createClubWithAccount(10, 1000);
        $sellingClub = $this->createClubWithAccount(20, 50000);
        $player = $this->createPlayerWithContract($sellingClub->id, 1000);

        $transfer = $this->createMovePlayerTransfer(
            $buyingClub->id,
            $sellingClub->id,
            $player->id,
            TransferTypes::PERMANENT_TRANSFER
        );
        TransferFinancialDetails::factory()->create([
            'transfer_id' => $transfer->id,
            'amount' => 10000,
            'installments' => 0,
        ]);
        TransferContractOffer::factory()->create([
            'transfer_id' => $transfer->id,
            'transfer_fee' => 5000,
        ]);

        $this->transferWorkflow()->transferPlayerToNewClub($transfer);

        $this->assertSame(TransferStatusTypes::TRANSFER_FAILED->value, $transfer->refresh()->transfer_status);
        $this->assertSame($sellingClub->id, $player->refresh()->club_id);
        $this->assertDatabaseHas('transfer_contract_offers', ['transfer_id' => $transfer->id]);
        $this->assertDatabaseMissing('news', [
            'instance_id' => 1,
            'season_id' => 1,
            'club_id' => $buyingClub->id,
            'title' => 'Transfer completed',
            'type' => 'transfer',
        ]);
        $this->assertDatabaseCount('news', 0);
    }

    private function transferWorkflow(): TransferWorkflow
    {
        return app()->make(TransferWorkflow::class);
    }

    private function createClubWithAccount(int $id, int $transferBudget): Club
    {
        $club = Club::factory()->create([
            'id' => $id,
            'instance_id' => 1,
        ]);

        Account::factory()->create([
            'club_id' => $club->id,
            'balance' => $transferBudget,
            'future_balance' => $transferBudget,
            'transfer_budget' => $transferBudget,
        ]);

        return $club;
    }

    private function createPlayerWithContract(int $clubId, int $salary): Player
    {
        $contract = PlayerContract::factory()->create(['salary' => $salary]);

        return Player::factory()->create([
            'club_id' => $clubId,
            'contract_id' => $contract->id,
            'instance_id' => 1,
        ]);
    }

    private function createMovePlayerTransfer(
        int $sourceClubId,
        ?int $targetClubId,
        int $playerId,
        int $transferType
    ): Transfer {
        return Transfer::factory()->create([
            'instance_id' => 1,
            'season_id' => 1,
            'source_club_id' => $sourceClubId,
            'target_club_id' => $targetClubId,
            'player_id' => $playerId,
            'transfer_status' => TransferStatusTypes::MOVE_PLAYER->value,
            'transfer_type' => $transferType,
        ]);
    }
}
