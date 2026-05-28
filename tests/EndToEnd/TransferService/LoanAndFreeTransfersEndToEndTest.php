<?php

namespace Tests\EndToEnd\TransferService;

use App\Models\Account;
use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Models\PlayerContract;
use App\Models\Transfer;
use App\Models\TransferContractOffer;
use App\Services\NewsService\NewsPriority;
use App\Services\NewsService\NewsType;
use App\Services\TransferService\TransferStatusTypes;
use App\Services\TransferService\TransferStatusUpdates;
use App\Services\TransferService\TransferTypes;
use App\Services\TransferService\TransferWorkflow;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LoanAndFreeTransfersEndToEndTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function loan_waiting_player_accepts_and_moves_to_waiting_paperwork(): void
    {
        $this->createInstance('2024-07-01');
        $loanClub = $this->createClub(1);
        $parentClub = $this->createClub(2);
        $player = $this->createPlayerWithContract($parentClub->id, 1000);
        $transfer = $this->createTransfer(
            $loanClub->id,
            $parentClub->id,
            $player->id,
            TransferStatusTypes::WAITING_PLAYER->value,
            TransferTypes::LOAN_TRANSFER
        );
        $this->createContractOffer($transfer, salary: 1000);

        $this->transferStatusUpdates()->loanTransferUpdates($transfer);

        $this->assertSame(TransferStatusTypes::WAITING_PAPERWORK->value, $transfer->refresh()->transfer_status);

        $playerName = $this->playerName($transfer);

        $this->assertTransferNews(
            $transfer,
            "{$playerName} agrees terms",
            "{$playerName} has agreed personal terms with {$loanClub->name}.",
            NewsPriority::High,
        );
    }

    #[Test]
    public function loan_waits_for_the_transfer_window_then_completes_when_it_opens(): void
    {
        $instance = $this->createInstance('2024-03-01');
        $loanClub = $this->createClub(1);
        $parentClub = $this->createClub(2);
        $player = $this->createPlayerWithContract($parentClub->id, 1000);
        $transfer = $this->createTransfer(
            $loanClub->id,
            $parentClub->id,
            $player->id,
            TransferStatusTypes::MOVE_PLAYER->value,
            TransferTypes::LOAN_TRANSFER
        );
        $this->createContractOffer($transfer);

        $this->transferStatusUpdates()->loanTransferUpdates($transfer);

        $this->assertSame(TransferStatusTypes::WAITING_TRANSFER_WINDOW->value, $transfer->refresh()->transfer_status);
        $this->assertSame('2024-07-01', $transfer->transfer_date);

        $playerName = $this->playerName($transfer);

        $this->assertTransferNews(
            $transfer,
            "{$playerName} transfer delayed",
            "{$playerName}'s move to {$loanClub->name} will be completed when the transfer window opens.",
            NewsPriority::High,
        );

        $instance->instance_date = '2024-07-01';
        $instance->save();

        $this->transferStatusUpdates()->loanTransferUpdates($transfer);

        $this->assertSame(TransferStatusTypes::TRANSFER_COMPLETED->value, $transfer->refresh()->transfer_status);
        $this->assertSame($parentClub->id, $player->refresh()->club_id);
        $this->assertSame($loanClub->id, $player->loan_club_id);
        $this->assertDatabaseMissing('transfer_contract_offers', ['transfer_id' => $transfer->id]);

        $playerName = $this->playerName($transfer);

        $this->assertTransferNews(
            $transfer,
            "{$playerName} joins {$loanClub->name} on loan",
            "{$loanClub->name} have completed the loan signing of {$playerName} from {$parentClub->name}.",
            NewsPriority::Urgent,
        );
    }

    #[Test]
    public function loan_failed_status_removes_the_transfer_and_offer(): void
    {
        $this->createInstance('2024-07-01');
        $loanClub = $this->createClub(1);
        $parentClub = $this->createClub(2);
        $player = $this->createPlayerWithContract($parentClub->id, 1000);
        $transfer = $this->createTransfer(
            $loanClub->id,
            $parentClub->id,
            $player->id,
            TransferStatusTypes::TRANSFER_FAILED->value,
            TransferTypes::LOAN_TRANSFER
        );
        $this->createContractOffer($transfer);

        $this->transferStatusUpdates()->loanTransferUpdates($transfer);

        $this->assertDatabaseMissing('transfers', ['id' => $transfer->id]);
        $this->assertDatabaseMissing('transfer_contract_offers', ['transfer_id' => $transfer->id]);
    }

    #[Test]
    public function free_transfer_waiting_player_accepts_and_moves_to_waiting_paperwork(): void
    {
        $this->createInstance('2024-07-01');
        $newClub = $this->createClub(1);
        $oldClub = $this->createClub(2);
        $player = $this->createPlayerWithContract($oldClub->id, 1000);
        $transfer = $this->createTransfer(
            $newClub->id,
            null,
            $player->id,
            TransferStatusTypes::WAITING_PLAYER->value,
            TransferTypes::FREE_TRANSFER
        );
        $this->createContractOffer($transfer, salary: 1000);

        $this->transferStatusUpdates()->freeTransferUpdates($transfer);

        $this->assertSame(TransferStatusTypes::WAITING_PAPERWORK->value, $transfer->refresh()->transfer_status);

        $playerName = $this->playerName($transfer);

        $this->assertTransferNews(
            $transfer,
            "{$playerName} agrees terms",
            "{$playerName} has agreed personal terms with {$newClub->name}.",
            NewsPriority::High,
        );
    }

    #[Test]
    public function free_transfer_waits_for_the_transfer_window_then_completes_when_it_opens(): void
    {
        $instance = $this->createInstance('2024-03-01');
        $newClub = $this->createClub(1);
        $oldClub = $this->createClub(2);
        $player = $this->createPlayerWithContract($oldClub->id, 1000);
        $oldContractId = $player->contract_id;
        $transfer = $this->createTransfer(
            $newClub->id,
            null,
            $player->id,
            TransferStatusTypes::MOVE_PLAYER->value,
            TransferTypes::FREE_TRANSFER
        );
        $this->createContractOffer($transfer, salary: 3000);

        $this->transferStatusUpdates()->freeTransferUpdates($transfer);

        $this->assertSame(TransferStatusTypes::WAITING_TRANSFER_WINDOW->value, $transfer->refresh()->transfer_status);
        $this->assertSame('2024-07-01', $transfer->transfer_date);

        $playerName = $this->playerName($transfer);

        $this->assertTransferNews(
            $transfer,
            "{$playerName} transfer delayed",
            "{$playerName}'s move to {$newClub->name} will be completed when the transfer window opens.",
            NewsPriority::High,
        );

        $instance->instance_date = '2024-07-01';
        $instance->save();

        $this->transferStatusUpdates()->freeTransferUpdates($transfer);

        $player->refresh();

        $this->assertSame(TransferStatusTypes::TRANSFER_COMPLETED->value, $transfer->refresh()->transfer_status);
        $this->assertSame($newClub->id, $player->club_id);
        $this->assertNotSame($oldContractId, $player->contract_id);
        $this->assertSame(3000, $player->contract()->first()->salary);
        $this->assertDatabaseMissing('transfer_contract_offers', ['transfer_id' => $transfer->id]);

        $playerName = $this->playerName($transfer);

        $this->assertTransferNews(
            $transfer,
            "{$playerName} joins {$newClub->name}",
            "{$newClub->name} have completed the signing of {$playerName} on a free transfer.",
            NewsPriority::Urgent,
        );
    }

    #[Test]
    public function free_transfer_failed_status_removes_the_transfer_and_offer(): void
    {
        $this->createInstance('2024-07-01');
        $newClub = $this->createClub(1);
        $oldClub = $this->createClub(2);
        $player = $this->createPlayerWithContract($oldClub->id, 1000);
        $transfer = $this->createTransfer(
            $newClub->id,
            null,
            $player->id,
            TransferStatusTypes::TRANSFER_FAILED->value,
            TransferTypes::FREE_TRANSFER
        );
        $this->createContractOffer($transfer);

        $this->transferStatusUpdates()->freeTransferUpdates($transfer);

        $this->assertDatabaseMissing('transfers', ['id' => $transfer->id]);
        $this->assertDatabaseMissing('transfer_contract_offers', ['transfer_id' => $transfer->id]);
    }

    private function transferStatusUpdates(): TransferStatusUpdates
    {
        return new TransferStatusUpdates(app()->make(TransferWorkflow::class));
    }

    private function assertTransferNews(Transfer $transfer, string $title, string $content, NewsPriority $priority): void
    {
        $this->assertDatabaseHas('news', [
            'instance_id' => $transfer->instance_id,
            'season_id' => $transfer->season_id,
            'club_id' => $transfer->source_club_id,
            'title' => $title,
            'content' => $content,
            'type' => NewsType::Transfer->value,
            'priority' => $priority->value,
        ]);
    }

    private function playerName(Transfer $transfer): string
    {
        $player = $transfer->player()->first();

        return "{$player->first_name} {$player->last_name}";
    }

    private function createTransfer(
        int $sourceClubId,
        ?int $targetClubId,
        int $playerId,
        int $status,
        int $transferType
    ): Transfer {
        return Transfer::factory()->create([
            'instance_id' => 1,
            'season_id' => 1,
            'source_club_id' => $sourceClubId,
            'target_club_id' => $targetClubId,
            'player_id' => $playerId,
            'transfer_status' => $status,
            'transfer_type' => $transferType,
        ]);
    }

    private function createClub(int $id, int $transferBudget = 68000000): Club
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
            'balance' => $transferBudget,
            'future_balance' => $transferBudget,
            'transfer_budget' => $transferBudget,
        ]);

        return $club;
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

    private function createContractOffer(Transfer $transfer, int $salary = 1200): TransferContractOffer
    {
        return TransferContractOffer::factory()->create([
            'transfer_id' => $transfer->id,
            'transfer_fee' => 0,
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
            'counter_offered' => 0,
        ]);
    }

    private function createInstance(string $date): Instance
    {
        return Instance::factory()->create([
            'id' => 1,
            'instance_date' => $date,
        ]);
    }
}
