<?php

namespace Tests\Feature;

use App\EntityTransactionDirection;
use App\EntityTransactionType;
use App\GameEntityType;
use App\Models\Account;
use App\Models\Club;
use App\Models\GameEntity;
use App\Models\GameEntityAccount;
use App\Models\Instance;
use App\Services\FinanceService\FinanceService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinanceEntityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_moves_money_from_a_game_entity_to_a_club_and_records_the_event(): void
    {
        [$entityAccount, $clubAccount] = $this->accounts();

        $transaction = app(FinanceService::class)->makeEntityTransaction(
            $entityAccount,
            $clubAccount,
            EntityTransactionDirection::ENTITY_TO_CLUB,
            EntityTransactionType::PRIZE,
            2500,
            CarbonImmutable::parse('2026-09-15 12:00:00'),
            42,
        );

        $this->assertSame(12500, $clubAccount->fresh()->balance);
        $this->assertSame(12500, $clubAccount->fresh()->future_balance);
        $this->assertSame(7500, $entityAccount->fresh()->balance);
        $this->assertSame(7500, $entityAccount->fresh()->future_balance);
        $this->assertSame(EntityTransactionDirection::ENTITY_TO_CLUB, $transaction->direction);
        $this->assertSame(EntityTransactionType::PRIZE, $transaction->event_type);
        $this->assertSame(42, $transaction->event_id);
        $this->assertDatabaseHas('finance_transactions_entities', [
            'id' => $transaction->id,
            'game_entity_account_id' => $entityAccount->id,
            'club_account_id' => $clubAccount->id,
            'amount' => 2500,
        ]);
    }

    #[Test]
    public function it_moves_money_from_a_club_to_a_game_entity(): void
    {
        [$entityAccount, $clubAccount] = $this->accounts();

        app(FinanceService::class)->makeEntityTransaction(
            $entityAccount,
            $clubAccount,
            EntityTransactionDirection::CLUB_TO_ENTITY,
            EntityTransactionType::LOAN_REPAYMENT,
            1000,
            CarbonImmutable::parse('2026-09-15 12:00:00'),
        );

        $this->assertSame(9000, $clubAccount->fresh()->balance);
        $this->assertSame(11000, $entityAccount->fresh()->balance);
    }

    /**
     * @return array{0: GameEntityAccount, 1: Account}
     */
    private function accounts(): array
    {
        $instance = Instance::factory()->create();
        $club = Club::factory()->create(['instance_id' => $instance->id]);
        $clubAccount = Account::factory()->create([
            'club_id' => $club->id,
            'balance' => 10000,
            'future_balance' => 10000,
        ]);
        $entity = GameEntity::factory()->create([
            'instance_id' => $instance->id,
            'type' => GameEntityType::BANK,
        ]);
        $entityAccount = GameEntityAccount::factory()->create([
            'game_entity_id' => $entity->id,
            'instance_id' => $instance->id,
            'balance' => 10000,
            'future_balance' => 10000,
        ]);

        return [$entityAccount, $clubAccount];
    }
}
