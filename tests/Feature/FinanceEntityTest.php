<?php

namespace Tests\Feature;

use App\EntityTransactionDirection;
use App\EntityTransactionType;
use App\FinanceEntityLoanStatus;
use App\GameEntityType;
use App\Models\Account;
use App\Models\AccountsDebtLinesEntity;
use App\Models\Club;
use App\Models\GameEntity;
use App\Models\GameEntityAccount;
use App\Models\Instance;
use App\Services\FinanceService\Domain\CashLoanCalculator;
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

    #[Test]
    public function it_issues_a_loan_with_monthly_installments(): void
    {
        [$entityAccount, $clubAccount] = $this->accounts();

        $loan = app(FinanceService::class)->issueLoan(
            $entityAccount,
            $clubAccount,
            app(CashLoanCalculator::class)->calculate(10000, 12),
            CarbonImmutable::parse('2026-09-15'),
        );

        $this->assertSame(10000, $loan->principal);
        $this->assertSame(800, $loan->interest_amount);
        $this->assertSame(10800, $loan->total_amount);
        $this->assertSame(FinanceEntityLoanStatus::ACTIVE, $loan->status);
        $this->assertSame(12, $loan->installments->count());
        $this->assertSame(10800, $loan->installments->sum('amount'));
        $this->assertSame(
            array_slice(['2026-10-15', '2026-11-15', '2026-12-15'], 0, 3),
            array_slice($loan->installments->pluck('due_date')->map->toDateString()->all(), 0, 3),
        );
        $this->assertSame(20000, $clubAccount->fresh()->balance);
        $this->assertSame(9200, $clubAccount->fresh()->future_balance);
        $this->assertSame(0, $entityAccount->fresh()->balance);
        $this->assertSame(10800, $entityAccount->fresh()->future_balance);
        $this->assertDatabaseHas('finance_transactions_entities', [
            'event_type' => EntityTransactionType::LOAN->value,
            'event_id' => $loan->id,
            'amount' => 10000,
        ]);
    }

    #[Test]
    public function it_settles_loan_installments_and_completes_the_loan(): void
    {
        [$entityAccount, $clubAccount] = $this->accounts();
        $loan = app(FinanceService::class)->issueLoan(
            $entityAccount,
            $clubAccount,
            app(CashLoanCalculator::class)->calculate(10000, 24),
            CarbonImmutable::parse('2026-09-15'),
        );

        $installments = $loan->installments()->orderBy('installment_number')->get();

        foreach ($installments as $installment) {
            app(FinanceService::class)->repayLoanInstallment(
                $installment,
                CarbonImmutable::parse('2026-10-15')->addMonths($installment->installment_number - 1),
            );
        }

        $this->assertSame(FinanceEntityLoanStatus::COMPLETED, $loan->fresh()->status);
        $this->assertSame(9000, $clubAccount->fresh()->balance);
        $this->assertSame(11000, $entityAccount->fresh()->balance);
        $this->assertSame(24, AccountsDebtLinesEntity::query()->whereNotNull('paid_at')->count());
        $this->assertSame(24, $loan->fresh()->installments()->whereNotNull('transaction_id')->count());
    }

    #[Test]
    public function it_processes_installments_when_they_are_due(): void
    {
        [$entityAccount, $clubAccount] = $this->accounts();
        $loan = app(FinanceService::class)->issueLoan(
            $entityAccount,
            $clubAccount,
            app(CashLoanCalculator::class)->calculate(10000, 24),
            CarbonImmutable::parse('2026-09-15'),
        );

        $processed = app(FinanceService::class)->processDueLoanInstallments(
            $loan->instance,
            CarbonImmutable::parse('2026-10-15'),
        );

        $this->assertSame(1, $processed);
        $this->assertSame(483, $entityAccount->fresh()->balance);
        $this->assertSame(19517, $clubAccount->fresh()->balance);
        $this->assertSame(FinanceEntityLoanStatus::ACTIVE, $loan->fresh()->status);
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
