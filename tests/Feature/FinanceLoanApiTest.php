<?php

namespace Tests\Feature;

use App\GameEntityType;
use App\Models\Account;
use App\Models\Club;
use App\Models\GameEntity;
use App\Models\GameEntityAccount;
use App\Models\Instance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinanceLoanApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_allows_the_managed_club_to_take_out_a_cash_loan(): void
    {
        $instance = Instance::factory()->create([
            'instance_hash' => 'loan-instance',
            'instance_date' => '2026-09-15',
        ]);
        $club = Club::factory()->create(['instance_id' => $instance->id]);
        $instance->forceFill(['club_id' => $club->id])->saveQuietly();
        $clubAccount = Account::factory()->create([
            'club_id' => $club->id,
            'balance' => 1000,
            'future_balance' => 1000,
        ]);
        $bank = GameEntity::factory()->create([
            'instance_id' => $instance->id,
            'type' => GameEntityType::BANK,
        ]);
        $bankAccount = GameEntityAccount::factory()->create([
            'game_entity_id' => $bank->id,
            'instance_id' => $instance->id,
            'balance' => 50_000_000_000,
            'future_balance' => 50_000_000_000,
        ]);

        $response = $this
            ->actingAs($instance->user)
            ->withHeaders(['instanceHash' => $instance->instance_hash])
            ->postJson('/api/finance/loans', [
                'amount' => 12000,
                'length_months' => 24,
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.principal', 12000)
            ->assertJsonPath('data.interest_amount', 1920)
            ->assertJsonPath('data.total_amount', 13920)
            ->assertJsonPath('data.installment_count', 24)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonCount(24, 'data.installments');

        $this->assertSame(13000, $clubAccount->fresh()->balance);
        $this->assertSame(49_999_988_000, $bankAccount->fresh()->balance);
        $this->assertDatabaseHas('finance_entity_loans', [
            'instance_id' => $instance->id,
            'lender_game_entity_account_id' => $bankAccount->id,
            'borrower_club_account_id' => $clubAccount->id,
        ]);
    }

    #[Test]
    public function it_rejects_a_loan_without_amount_or_length(): void
    {
        $instance = Instance::factory()->create([
            'instance_hash' => 'invalid-loan-instance',
        ]);

        $response = $this
            ->actingAs($instance->user)
            ->withHeaders(['instanceHash' => $instance->instance_hash])
            ->postJson('/api/finance/loans', []);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount', 'length_months']);
    }

    #[Test]
    public function it_rejects_a_loan_that_exceeds_the_clubs_allowed_debt(): void
    {
        $instance = Instance::factory()->create([
            'instance_hash' => 'unaffordable-loan-instance',
            'instance_date' => '2026-09-15',
        ]);
        $club = Club::factory()->create(['instance_id' => $instance->id]);
        $instance->forceFill(['club_id' => $club->id])->saveQuietly();
        Account::factory()->create([
            'club_id' => $club->id,
            'balance' => 1000,
            'future_balance' => 1000,
            'allowed_debt' => 500,
        ]);
        $bank = GameEntity::factory()->create([
            'instance_id' => $instance->id,
            'type' => GameEntityType::BANK,
        ]);
        GameEntityAccount::factory()->create([
            'game_entity_id' => $bank->id,
            'instance_id' => $instance->id,
            'balance' => 50_000_000_000,
            'future_balance' => 50_000_000_000,
        ]);

        $response = $this
            ->actingAs($instance->user)
            ->withHeaders(['instanceHash' => $instance->instance_hash])
            ->postJson('/api/finance/loans', [
                'amount' => 12000,
                'length_months' => 24,
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('message', 'The club cannot afford this loan.');

        $this->assertDatabaseCount('finance_entity_loans', 0);
        $this->assertDatabaseCount('finance_transactions_entities', 0);
    }
}
