<?php

namespace Tests\Integration\Services\SeasonService;

use App\Models\AccountsDebtLines;
use App\Models\Club;
use App\Models\Instance;
use App\Models\Person;
use App\Models\Player;
use App\Models\PlayerContract;
use App\Models\Transfer;
use App\Models\TransferContractOffer;
use App\Models\TransferFinancialDetails;
use App\Models\TransferList;
use App\Repositories\PlayerRepository;
use App\Repositories\TransferSearchRepository;
use App\Services\SeasonService\PlayerRetirement;
use App\Services\SeasonService\RetirementDecision;
use App\Services\TransferService\TransferStatusTypes;
use App\Support\GameContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PlayerRetirementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_retires_selected_older_players_and_voids_their_contracts(): void
    {
        $instance = Instance::factory()->create([
            'id' => 1,
            'instance_date' => '2027-06-16',
        ]);
        $club = Club::factory()->create(['id' => 1, 'instance_id' => 1]);
        $asOfDate = CarbonImmutable::parse($instance->instance_date);

        $age31 = $this->playerWithContract(1, $club->id, $asOfDate->subYears(31));
        $age32 = $this->playerWithContract(2, $club->id, $asOfDate->subYears(32));
        $age44 = $this->playerWithContract(3, $club->id, $asOfDate->subYears(44));
        $age45 = $this->playerWithContract(4, $club->id, $asOfDate->subYears(45));

        TransferList::factory()->create([
            'player_id' => $age32->id,
            'club_id' => $club->id,
        ]);

        $ongoingTransfer = Transfer::factory()->create([
            'instance_id' => $instance->id,
            'player_id' => $age32->id,
            'source_club_id' => $club->id,
            'target_club_id' => null,
            'transfer_status' => TransferStatusTypes::WAITING_TARGET_CLUB->value,
        ]);
        TransferContractOffer::factory()->create(['transfer_id' => $ongoingTransfer->id]);
        TransferFinancialDetails::factory()->create(['transfer_id' => $ongoingTransfer->id]);

        $completedTransfer = Transfer::factory()->create([
            'instance_id' => $instance->id,
            'player_id' => $age32->id,
            'source_club_id' => $club->id,
            'target_club_id' => null,
            'transfer_status' => TransferStatusTypes::TRANSFER_COMPLETED->value,
        ]);
        $completedFinancialDetails = TransferFinancialDetails::factory()->create([
            'transfer_id' => $completedTransfer->id,
            'amount' => 10000,
            'installments' => 3,
        ]);
        $installmentDebt = AccountsDebtLines::query()->create([
            'sending_account_id' => 1,
            'receiving_account_id' => 2,
            'amount' => 4000,
            'created_at' => '2027-05-01',
            'due_date' => '2027-08-01',
        ]);

        $rolls = collect([1, 10000, 10000]);
        $service = new PlayerRetirement(
            app(PlayerRepository::class),
            new RetirementDecision(fn (): int => (int) $rolls->shift())
        );

        $retiredPlayers = $service->retireEligiblePlayers($instance);

        $this->assertSame([$age32->id, $age45->id], collect($retiredPlayers)->pluck('id')->all());

        foreach ([$age32, $age45] as $retiredPlayer) {
            $this->assertDatabaseHas('players', [
                'id' => $retiredPlayer->id,
                'is_retired' => true,
                'club_id' => null,
                'loan_club_id' => null,
                'contract_id' => null,
            ]);
            $this->assertDatabaseMissing('players_contracts', [
                'id' => $retiredPlayer->contract_id,
            ]);
            $this->assertDatabaseMissing('players_progress', [
                'player_id' => $retiredPlayer->id,
            ]);
        }

        app(GameContext::class)->setInstanceId($instance->id);

        $this->assertNull(
            app(TransferSearchRepository::class)->findFreePlayerForPosition($club, 'CB')
        );

        $this->assertDatabaseMissing('transfer_list', ['player_id' => $age32->id]);
        $this->assertDatabaseMissing('transfers', ['id' => $ongoingTransfer->id]);
        $this->assertDatabaseMissing('transfer_contract_offers', ['transfer_id' => $ongoingTransfer->id]);
        $this->assertDatabaseMissing('transfer_financial_details', ['transfer_id' => $ongoingTransfer->id]);
        $this->assertDatabaseHas('transfers', ['id' => $completedTransfer->id]);
        $this->assertDatabaseHas('transfer_financial_details', [
            'id' => $completedFinancialDetails->id,
            'transfer_id' => $completedTransfer->id,
            'installments' => 3,
        ]);
        $this->assertDatabaseHas('accounts_debt_lines', ['id' => $installmentDebt->id]);

        foreach ([$age31, $age44] as $activePlayer) {
            $this->assertDatabaseHas('players', [
                'id' => $activePlayer->id,
                'is_retired' => false,
                'club_id' => $club->id,
                'contract_id' => $activePlayer->contract_id,
            ]);
            $this->assertDatabaseHas('players_contracts', [
                'id' => $activePlayer->contract_id,
            ]);
            $this->assertDatabaseHas('players_progress', [
                'player_id' => $activePlayer->id,
            ]);
        }

    }

    private function playerWithContract(
        int $id,
        int $clubId,
        CarbonImmutable $dob
    ): Player {
        $contract = PlayerContract::factory()->create([
            'contract_start' => '2026-07-01',
            'contract_end' => '2028-06-30',
        ]);

        $person = Person::factory()->create([
            'instance_id' => 1,
            'dob' => $dob->toDateString(),
        ]);

        return Player::factory()->create([
            'id' => $id,
            'instance_id' => 1,
            'person_id' => $person->id,
            'club_id' => $clubId,
            'loan_club_id' => $clubId,
            'loan_start' => '2027-01-01',
            'loan_end' => '2027-06-30',
            'contract_id' => $contract->id,
            'is_retired' => false,
        ]);
    }
}
