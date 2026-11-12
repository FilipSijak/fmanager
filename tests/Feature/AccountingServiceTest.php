<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Club;
use App\Models\Instance;
use App\Services\FinanceService\AccountingService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class AccountingServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function transfers_use_the_game_date_and_post_once_even_with_stale_account_objects(): void
    {
        $instance = Instance::factory()->create();
        $sender = $this->account($instance);
        $receiver = $this->account($instance);
        $accounting = app(AccountingService::class);
        $date = CarbonImmutable::parse('2030-01-07');

        $first = $accounting->transfer($sender, $receiver, 200, $date, 'transfer', 'transfer:42');
        $again = $accounting->transfer($sender, $receiver, 200, $date, 'transfer', 'transfer:42');
        $accounting->transfer($sender, $receiver, 300, $date, 'transfer', 'transfer:43');

        $this->assertSame($first->id, $again->id);
        $this->assertSame(500, $sender->fresh()->balance);
        $this->assertSame(500, $sender->fresh()->future_balance);
        $this->assertSame(1500, $receiver->fresh()->balance);
        $this->assertSame(1500, $receiver->fresh()->future_balance);
        $this->assertDatabaseCount('finance_transactions', 2);
        $this->assertDatabaseHas('finance_transactions', ['id' => $first->id, 'transaction_date' => '2030-01-07 00:00:00']);
    }

    #[Test]
    public function expenses_and_income_update_cash_and_projection_without_changing_budgets(): void
    {
        $account = $this->account(Instance::factory()->create());
        $budget = $account->transfer_budget;
        $accounting = app(AccountingService::class);
        $date = CarbonImmutable::parse('2030-01-07');

        $accounting->expense($account, 1500, $date, 'wages', 'wages:1');
        $this->assertSame(-500, $account->fresh()->balance);
        $accounting->income($account, 300, $date, 'gate_receipts', 'match:1');

        $this->assertSame(-200, $account->fresh()->balance);
        $this->assertSame(-200, $account->fresh()->future_balance);
        $this->assertSame($budget, $account->fresh()->transfer_budget);
        $this->assertDatabaseHas('finance_transactions', ['reference' => 'wages:1', 'receiving_account_id' => null, 'amount' => 1500]);
        $this->assertDatabaseHas('finance_transactions', ['reference' => 'match:1', 'sending_account_id' => null, 'amount' => 300]);
    }

    #[Test]
    #[DataProvider('invalidAmounts')]
    public function invalid_amounts_leave_the_account_unchanged(int $amount): void
    {
        $account = $this->account(Instance::factory()->create());

        try {
            app(AccountingService::class)->expense($account, $amount, CarbonImmutable::parse('2030-01-07'), 'wages', 'invalid');
            $this->fail('Expected an invalid amount to be rejected.');
        } catch (DomainException) {
            $this->assertSame(1000, $account->fresh()->balance);
            $this->assertDatabaseCount('finance_transactions', 0);
        }
    }

    public static function invalidAmounts(): array
    {
        return [[0], [-1]];
    }

    #[Test]
    public function a_reference_cannot_be_reused_with_a_different_amount(): void
    {
        $account = $this->account(Instance::factory()->create());
        $accounting = app(AccountingService::class);
        $date = CarbonImmutable::parse('2030-01-07');
        $accounting->expense($account, 100, $date, 'wages', 'wages:1');

        try {
            $accounting->expense($account, 200, $date, 'wages', 'wages:1');
            $this->fail('Expected conflicting references to be rejected.');
        } catch (DomainException) {
            $this->assertSame(900, $account->fresh()->balance);
            $this->assertDatabaseCount('finance_transactions', 1);
        }
    }

    #[Test]
    public function accounts_from_different_games_cannot_exchange_money(): void
    {
        $sender = $this->account(Instance::factory()->create());
        $receiver = $this->account(Instance::factory()->create());

        try {
            app(AccountingService::class)->transfer($sender, $receiver, 100, CarbonImmutable::parse('2030-01-07'), 'transfer', 'invalid');
            $this->fail('Expected cross-game payments to be rejected.');
        } catch (DomainException) {
            $this->assertSame(1000, $sender->fresh()->balance);
            $this->assertSame(1000, $receiver->fresh()->balance);
            $this->assertDatabaseCount('finance_transactions', 0);
        }
    }

    #[Test]
    public function a_transfer_cannot_pay_its_own_account(): void
    {
        $account = $this->account(Instance::factory()->create());

        try {
            app(AccountingService::class)->transfer($account, $account, 100, CarbonImmutable::parse('2030-01-07'), 'transfer', 'self-payment');
            $this->fail('Expected self-payments to be rejected.');
        } catch (DomainException $exception) {
            $this->assertSame('A payment requires different accounts.', $exception->getMessage());
            $this->assertSame(1000, $account->fresh()->balance);
            $this->assertDatabaseCount('finance_transactions', 0);
        }
    }

    #[Test]
    #[DataProvider('missingPostingDetails')]
    public function payments_require_a_category_and_reference(string $category, string $reference): void
    {
        $account = $this->account(Instance::factory()->create());

        try {
            app(AccountingService::class)->expense($account, 100, CarbonImmutable::parse('2030-01-07'), $category, $reference);
            $this->fail('Expected incomplete payment details to be rejected.');
        } catch (DomainException $exception) {
            $this->assertSame('Payments require a category and reference.', $exception->getMessage());
            $this->assertSame(1000, $account->fresh()->balance);
            $this->assertDatabaseCount('finance_transactions', 0);
        }
    }

    /** @return list<array{string, string}> */
    public static function missingPostingDetails(): array
    {
        return [[' ', 'wages:1'], ['wages', ' ']];
    }

    #[Test]
    public function a_failed_business_operation_rolls_back_its_payment(): void
    {
        $account = $this->account(Instance::factory()->create());

        try {
            DB::transaction(function () use ($account): void {
                app(AccountingService::class)->expense($account, 100, CarbonImmutable::parse('2030-01-07'), 'wages', 'rollback');
                throw new RuntimeException('Operation failed.');
            });
        } catch (RuntimeException) {
            $this->assertSame(1000, $account->fresh()->balance);
            $this->assertSame(1000, $account->fresh()->future_balance);
            $this->assertDatabaseCount('finance_transactions', 0);
        }
    }

    private function account(Instance $instance): Account
    {
        $club = Club::factory()->create(['instance_id' => $instance->id]);

        return Account::factory()->create(['club_id' => $club->id, 'balance' => 1000, 'future_balance' => 1000]);
    }
}
