<?php

namespace Tests\EndToEnd\TransferService;

use App\Models\Account;
use App\Models\AccountsDebtLines;
use App\Models\Club;
use App\Models\FinanceTransactions;
use App\Models\Instance;
use App\Models\Player;
use App\Models\PlayerContract;
use App\Models\Transfer;
use App\Models\TransferContractOffer;
use App\Models\TransferFinancialDetails;
use App\Repositories\PlayerRepository;
use App\Repositories\TransferRepository;
use App\Services\NewsService\NewsPriority;
use App\Services\TransferService\TransferConsiderations\ClubConsideration;
use App\Services\TransferService\TransferConsiderations\PlayerConsideration;
use App\Services\TransferService\TransferConsiderations\TransferConsiderations;
use App\Services\TransferService\TransferStatusTypes;
use App\Services\TransferService\TransferStatusUpdates;
use App\Services\TransferService\TransferTypes;
use App\Services\TransferService\TransferWorkflow;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PermanentTransfersEndToEndTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function waiting_target_club_accepts_and_moves_to_waiting_player(): void
    {
        $transfer = $this->createTransferWithSellingClubDecisionContext(
            playerAttributes: ['value' => 10000, 'potential' => 80, 'position' => 'ST'],
            amount: 10000
        );

        $this->transferStatusUpdates()->permanentTransferUpdates($transfer);

        $this->assertSame(TransferStatusTypes::WAITING_PLAYER->value, $transfer->refresh()->transfer_status);
        $this->assertDatabaseHas('transfer_contract_offers', ['transfer_id' => $transfer->id]);

        $playerName = $this->playerName($transfer);
        $buyingClub = $transfer->sourceClub()->first();
        $sellingClub = $transfer->targetClub()->first();

        $this->assertTransferNews(
            $transfer,
            "Offer accepted for {$playerName}",
            "{$sellingClub->name} have accepted {$buyingClub->name}'s offer for {$playerName}.",
            NewsPriority::High,
        );
    }

    #[Test]
    public function waiting_player_accepts_and_moves_to_waiting_paperwork(): void
    {
        $transfer = $this->createWaitingPlayerTransfer(currentSalary: 1000, offerSalary: 1000);

        $this->transferStatusUpdates()->permanentTransferUpdates($transfer);

        $this->assertSame(TransferStatusTypes::WAITING_PAPERWORK->value, $transfer->refresh()->transfer_status);

        $playerName = $this->playerName($transfer);
        $buyingClub = $transfer->sourceClub()->first();

        $this->assertTransferNews(
            $transfer,
            "{$playerName} agrees terms",
            "{$playerName} has agreed personal terms with {$buyingClub->name}.",
            NewsPriority::High,
        );
    }

    #[Test]
    public function waiting_paperwork_moves_to_move_player_when_medical_passes(): void
    {
        $this->createInstance('2024-07-01');
        $transfer = $this->createWaitingPlayerTransfer();
        $transfer->transfer_status = TransferStatusTypes::WAITING_PAPERWORK->value;
        $transfer->save();

        $this->transferStatusUpdates()->permanentTransferUpdates($transfer);

        $this->assertSame(TransferStatusTypes::MOVE_PLAYER->value, $transfer->refresh()->transfer_status);
    }

    #[Test]
    public function waiting_transfer_window_completes_when_window_is_open(): void
    {
        $this->createInstance('2024-07-01');
        $transfer = $this->createMoveReadyTransfer(TransferStatusTypes::WAITING_TRANSFER_WINDOW->value);

        $this->transferStatusUpdates()->permanentTransferUpdates($transfer);

        $this->assertCompletedTransfer($transfer);
    }

    #[Test]
    public function move_player_completes_the_transfer(): void
    {
        $this->createInstance('2024-07-01');
        $transfer = $this->createMoveReadyTransfer(TransferStatusTypes::MOVE_PLAYER->value);

        $this->transferStatusUpdates()->permanentTransferUpdates($transfer);

        $this->assertCompletedTransfer($transfer);
        $this->assertImmediatePaymentSettled($transfer, amount: 10000);
    }

    #[Test]
    public function move_player_completes_the_transfer_with_installments(): void
    {
        $this->createInstance('2024-07-01');
        $transfer = $this->createMoveReadyTransfer(
            status: TransferStatusTypes::MOVE_PLAYER->value,
            amount: 10000,
            installments: 3
        );

        $this->transferStatusUpdates()->permanentTransferUpdates($transfer);

        $this->assertCompletedTransfer($transfer);
        $this->assertInstallmentPaymentSettled($transfer, amount: 10000, installmentAmounts: [3000, 3000, 4000]);
    }

    #[Test]
    public function target_club_counteroffer_is_accepted_when_source_club_values_and_can_afford_it(): void
    {
        $transfer = $this->createCounterOfferTransfer(amount: 13000, sourceClubBudget: 68000000);

        $this->transferStatusUpdates()->permanentTransferUpdates($transfer);

        $this->assertSame(TransferStatusTypes::COUNTEROFFER_ACCEPTED->value, $transfer->refresh()->transfer_status);

        $playerName = $this->playerName($transfer);
        $buyingClub = $transfer->sourceClub()->first();
        $sellingClub = $transfer->targetClub()->first();

        $this->assertTransferNews(
            $transfer,
            "Counteroffer accepted for {$playerName}",
            "{$buyingClub->name} have accepted {$sellingClub->name}'s counteroffer for {$playerName}.",
            NewsPriority::High,
        );
    }

    #[Test]
    public function counteroffer_accepted_creates_contract_offer_and_moves_to_waiting_player(): void
    {
        $transfer = $this->createBasePermanentTransfer(TransferStatusTypes::COUNTEROFFER_ACCEPTED->value);

        $this->transferStatusUpdates()->permanentTransferUpdates($transfer);

        $this->assertSame(TransferStatusTypes::WAITING_PLAYER->value, $transfer->refresh()->transfer_status);
        $this->assertDatabaseHas('transfer_contract_offers', ['transfer_id' => $transfer->id]);
    }

    #[Test]
    public function player_counteroffer_within_ten_percent_moves_to_waiting_paperwork(): void
    {
        $transfer = $this->createPlayerCounterOfferTransfer(counterOfferMultiplier: 1.05);

        $this->transferStatusUpdates()->permanentTransferUpdates($transfer);

        $this->assertSame(TransferStatusTypes::WAITING_PAPERWORK->value, $transfer->refresh()->transfer_status);

        $playerName = $this->playerName($transfer);
        $buyingClub = $transfer->sourceClub()->first();

        $this->assertTransferNews(
            $transfer,
            "{$playerName} counteroffer accepted",
            "{$buyingClub->name} have accepted {$playerName}'s contract demands.",
            NewsPriority::High,
        );
    }

    #[Test]
    public function player_declined_moves_to_transfer_failed(): void
    {
        $transfer = $this->createBasePermanentTransfer(TransferStatusTypes::PLAYER_DECLINED->value);

        $this->transferStatusUpdates()->permanentTransferUpdates($transfer);

        $this->assertSame(TransferStatusTypes::TRANSFER_FAILED->value, $transfer->refresh()->transfer_status);

        $playerName = $this->playerName($transfer);
        $buyingClub = $transfer->sourceClub()->first();

        $this->assertTransferNews(
            $transfer,
            "{$playerName} rejects move",
            "{$playerName} has rejected a move to {$buyingClub->name}.",
            NewsPriority::Urgent,
        );
    }

    #[Test]
    public function target_club_declined_moves_to_transfer_failed(): void
    {
        $transfer = $this->createBasePermanentTransfer(TransferStatusTypes::TARGET_CLUB_DECLINED->value);

        $this->transferStatusUpdates()->permanentTransferUpdates($transfer);

        $this->assertSame(TransferStatusTypes::TRANSFER_FAILED->value, $transfer->refresh()->transfer_status);

        $playerName = $this->playerName($transfer);
        $buyingClub = $transfer->sourceClub()->first();
        $sellingClub = $transfer->targetClub()->first();

        $this->assertTransferNews(
            $transfer,
            "{$playerName} transfer rejected",
            "{$sellingClub->name} have rejected {$buyingClub->name}'s approach for {$playerName}.",
            NewsPriority::High,
        );
    }

    #[Test]
    public function transfer_completed_removes_remaining_contract_offer_but_keeps_transfer(): void
    {
        $transfer = $this->createBasePermanentTransfer(TransferStatusTypes::TRANSFER_COMPLETED->value);
        TransferContractOffer::factory()->create(['transfer_id' => $transfer->id]);

        $this->transferStatusUpdates()->permanentTransferUpdates($transfer);

        $this->assertDatabaseHas('transfers', ['id' => $transfer->id]);
        $this->assertDatabaseMissing('transfer_contract_offers', ['transfer_id' => $transfer->id]);
    }

    #[Test]
    public function transfer_failed_removes_transfer_offer_and_financial_details(): void
    {
        $transfer = $this->createBasePermanentTransfer(TransferStatusTypes::TRANSFER_FAILED->value);
        TransferContractOffer::factory()->create(['transfer_id' => $transfer->id]);
        TransferFinancialDetails::factory()->create(['transfer_id' => $transfer->id]);

        $this->transferStatusUpdates()->permanentTransferUpdates($transfer);

        $this->assertDatabaseMissing('transfers', ['id' => $transfer->id]);
        $this->assertDatabaseMissing('transfer_contract_offers', ['transfer_id' => $transfer->id]);
        $this->assertDatabaseMissing('transfer_financial_details', ['transfer_id' => $transfer->id]);
    }

    private function transferStatusUpdates(): TransferStatusUpdates
    {
        return new TransferStatusUpdates(app()->make(TransferWorkflow::class));
    }

    private function createTransferWithSellingClubDecisionContext(array $playerAttributes, int $amount): Transfer
    {
        $buyingClub = $this->createClub(1);
        $sellingClub = $this->createClub(2);

        $player = $this->createPlayerWithContract($sellingClub->id, 1000, $playerAttributes);
        $this->createPlayerWithContract($sellingClub->id, 1000, ['potential' => 180, 'position' => 'CB']);
        $this->createPlayerWithContract($sellingClub->id, 1000, ['potential' => 170, 'position' => 'CB']);
        $this->createPlayerWithContract($sellingClub->id, 1000, ['potential' => 160, 'position' => 'CB']);
        $this->createPlayerWithContract($sellingClub->id, 1000, ['potential' => 70, 'position' => 'ST']);
        $this->createPlayerWithContract($sellingClub->id, 1000, ['potential' => 60, 'position' => 'ST']);

        $transfer = $this->createTransfer(
            $buyingClub->id,
            $sellingClub->id,
            $player->id,
            TransferStatusTypes::WAITING_TARGET_CLUB->value
        );

        TransferFinancialDetails::factory()->create([
            'transfer_id' => $transfer->id,
            'amount' => $amount,
            'installments' => 0,
        ]);

        return $transfer;
    }

    private function createWaitingPlayerTransfer(int $currentSalary = 1000, int $offerSalary = 1000): Transfer
    {
        $buyingClub = $this->createClub(1);
        $sellingClub = $this->createClub(2);
        $player = $this->createPlayerWithContract($sellingClub->id, $currentSalary);
        $transfer = $this->createTransfer(
            $buyingClub->id,
            $sellingClub->id,
            $player->id,
            TransferStatusTypes::WAITING_PLAYER->value
        );

        TransferContractOffer::factory()->create([
            'transfer_id' => $transfer->id,
            'transfer_fee' => 0,
            'salary' => $offerSalary,
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

        return $transfer;
    }

    private function createMoveReadyTransfer(int $status, int $amount = 10000, int $installments = 0): Transfer
    {
        $buyingClub = $this->createClub(1, 68000000);
        $sellingClub = $this->createClub(2, 68000000);
        $player = $this->createPlayerWithContract($sellingClub->id, 1000);

        $transfer = $this->createTransfer($buyingClub->id, $sellingClub->id, $player->id, $status);

        TransferFinancialDetails::factory()->create([
            'transfer_id' => $transfer->id,
            'amount' => $amount,
            'installments' => $installments,
        ]);
        TransferContractOffer::factory()->create([
            'transfer_id' => $transfer->id,
            'transfer_fee' => 0,
            'salary' => 1200,
        ]);

        return $transfer;
    }

    private function createCounterOfferTransfer(int $amount, int $sourceClubBudget): Transfer
    {
        $buyingClub = $this->createClub(1, $sourceClubBudget);
        $sellingClub = $this->createClub(2);
        $player = $this->createPlayerWithContract($sellingClub->id, 1000, [
            'value' => 10000,
            'potential' => 200,
        ]);

        $transfer = $this->createTransfer(
            $buyingClub->id,
            $sellingClub->id,
            $player->id,
            TransferStatusTypes::TARGET_CLUB_COUNTEROFFER->value
        );

        TransferFinancialDetails::factory()->create([
            'transfer_id' => $transfer->id,
            'amount' => $amount,
            'installments' => 0,
        ]);

        return $transfer;
    }

    private function createBasePermanentTransfer(int $status): Transfer
    {
        $buyingClub = $this->createClub(1);
        $sellingClub = $this->createClub(2);
        $player = $this->createPlayerWithContract($sellingClub->id, 1000);

        return $this->createTransfer($buyingClub->id, $sellingClub->id, $player->id, $status);
    }

    private function createPlayerCounterOfferTransfer(float $counterOfferMultiplier): Transfer
    {
        $transfer = $this->createBasePermanentTransfer(TransferStatusTypes::PLAYER_COUNTEROFFER->value);
        $baselineOffer = app()->make(PlayerRepository::class)->contractBasedOnPotential($transfer->player()->first());

        TransferContractOffer::factory()->create(array_merge(
            ['transfer_id' => $transfer->id],
            $this->counterOfferFromBaseline($baselineOffer, $counterOfferMultiplier)
        ));

        return $transfer;
    }

    private function createTransfer(int $sourceClubId, int $targetClubId, int $playerId, int $status): Transfer
    {
        return Transfer::factory()->create([
            'instance_id' => 1,
            'season_id' => 1,
            'source_club_id' => $sourceClubId,
            'target_club_id' => $targetClubId,
            'player_id' => $playerId,
            'transfer_status' => $status,
            'transfer_type' => TransferTypes::PERMANENT_TRANSFER,
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

    private function createPlayerWithContract(int $clubId, int $salary, array $attributes = []): Player
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

        return Player::factory()->create(array_merge([
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
        ], $attributes));
    }

    private function createInstance(string $date): void
    {
        Instance::factory()->create([
            'id' => 1,
            'instance_date' => $date,
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

    private function assertCompletedTransfer(Transfer $transfer): void
    {
        $transfer->refresh();
        $player = $transfer->player()->first();

        $this->assertSame(TransferStatusTypes::TRANSFER_COMPLETED->value, $transfer->transfer_status);
        $this->assertSame($transfer->source_club_id, $player->club_id);
        $this->assertSame(1200, $player->contract()->first()->salary);
        $this->assertDatabaseMissing('transfer_contract_offers', ['transfer_id' => $transfer->id]);

        $playerName = $this->playerName($transfer);
        $buyingClub = $transfer->sourceClub()->first();
        $sellingClub = $transfer->targetClub()->first();

        $this->assertTransferNews(
            $transfer,
            "{$playerName} joins {$buyingClub->name}",
            "{$buyingClub->name} have completed the signing of {$playerName} from {$sellingClub->name}.",
            NewsPriority::Urgent,
        );
    }

    private function assertTransferNews(Transfer $transfer, string $title, string $content, NewsPriority $priority): void
    {
        $this->assertDatabaseHas('news', [
            'instance_id' => $transfer->instance_id,
            'season_id' => $transfer->season_id,
            'club_id' => $transfer->source_club_id,
            'title' => $title,
            'content' => $content,
            'type' => 'transfer',
            'priority' => $priority->value,
        ]);
    }

    private function playerName(Transfer $transfer): string
    {
        $player = $transfer->player()->first();

        return "{$player->first_name} {$player->last_name}";
    }

    private function assertImmediatePaymentSettled(Transfer $transfer, int $amount): void
    {
        $buyingClubAccount = Account::where('club_id', $transfer->source_club_id)->firstOrFail();
        $sellingClubAccount = Account::where('club_id', $transfer->target_club_id)->firstOrFail();

        $this->assertSame(68000000 - $amount, $buyingClubAccount->balance);
        $this->assertSame(68000000 - $amount, $buyingClubAccount->future_balance);
        $this->assertSame(68000000 - $amount, $buyingClubAccount->transfer_budget);

        $this->assertSame(68000000 + $amount, $sellingClubAccount->balance);
        $this->assertSame(68000000 + $amount, $sellingClubAccount->future_balance);
        $this->assertSame(68000000 + $amount, $sellingClubAccount->transfer_budget);

        $this->assertDatabaseHas('finance_transactions', [
            'sending_account_id' => $buyingClubAccount->id,
            'receiving_account_id' => $sellingClubAccount->id,
            'amount' => $amount,
        ]);
        $this->assertSame(0, AccountsDebtLines::count());
    }

    private function assertInstallmentPaymentSettled(Transfer $transfer, int $amount, array $installmentAmounts): void
    {
        $buyingClubAccount = Account::where('club_id', $transfer->source_club_id)->firstOrFail();
        $sellingClubAccount = Account::where('club_id', $transfer->target_club_id)->firstOrFail();

        $this->assertSame(68000000, $buyingClubAccount->balance);
        $this->assertSame(68000000 - $amount, $buyingClubAccount->future_balance);
        $this->assertSame(68000000 - $amount, $buyingClubAccount->transfer_budget);

        $this->assertSame(68000000, $sellingClubAccount->balance);
        $this->assertSame(68000000 + $amount, $sellingClubAccount->future_balance);
        $this->assertSame(68000000 + $amount, $sellingClubAccount->transfer_budget);

        $this->assertSame(0, FinanceTransactions::count());
        $this->assertSame(
            $installmentAmounts,
            AccountsDebtLines::query()->orderBy('due_date')->pluck('amount')->all()
        );
    }
}
