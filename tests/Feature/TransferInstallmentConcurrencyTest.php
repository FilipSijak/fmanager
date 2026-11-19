<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\AccountsDebtLines;
use App\Models\Club;
use App\Models\Instance;
use App\Models\Transfer;
use App\Models\TransferFinancialDetails;
use App\Services\TransferService\TransferFinancialSettlement;
use App\Services\TransferService\TransferStatusTypes;
use Illuminate\Concurrency\ProcessDriver;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Process\Factory;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TransferInstallmentConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    #[Test]
    public function upgrading_an_existing_save_preserves_debts_and_prevents_resettling_completed_transfers(): void
    {
        $instance = Instance::factory()->create(['instance_date' => '2030-01-07']);
        $completed = Transfer::factory()->create([
            'instance_id' => $instance->id,
            'transfer_status' => TransferStatusTypes::TRANSFER_COMPLETED->value,
            'transfer_date' => '2029-12-07',
        ]);
        $pending = Transfer::factory()->create(['instance_id' => $instance->id]);
        $debt = AccountsDebtLines::create([
            'sending_account_id' => 1, 'receiving_account_id' => 2,
            'amount' => 10000, 'created_at' => '2029-12-07', 'due_date' => '2030-01-07',
        ]);
        $migration = require database_path('migrations/2026_11_19_104850_add_settlement_tracking_to_transfer_installments.php');

        $migration->down();
        $migration->up();
        app(TransferFinancialSettlement::class)->transferMoneyBetweenClubs($completed);

        $this->assertDatabaseHas('transfers', ['id' => $completed->id, 'financially_settled_on' => '2029-12-07']);
        $this->assertDatabaseHas('transfers', ['id' => $pending->id, 'financially_settled_on' => null]);
        $this->assertDatabaseHas('accounts_debt_lines', [
            'id' => $debt->id, 'amount' => 10000, 'due_date' => '2030-01-07', 'transfer_id' => null, 'paid_on' => null,
        ]);
        $this->assertDatabaseCount('accounts_debt_lines', 1);
        $this->assertDatabaseCount('finance_transactions', 0);
    }

    #[Test]
    public function concurrent_requests_create_one_schedule_and_pay_each_installment_once(): void
    {
        $instance = Instance::factory()->create(['instance_date' => '2030-01-07']);
        $buyer = Club::factory()->create(['instance_id' => $instance->id]);
        $seller = Club::factory()->create(['instance_id' => $instance->id]);
        $buyerAccount = Account::factory()->create(['club_id' => $buyer->id, 'balance' => 100000, 'future_balance' => 100000, 'transfer_budget' => 100000]);
        $sellerAccount = Account::factory()->create(['club_id' => $seller->id, 'balance' => 50000, 'future_balance' => 50000, 'transfer_budget' => 50000]);
        $transfer = Transfer::factory()->create(['instance_id' => $instance->id, 'source_club_id' => $buyer->id, 'target_club_id' => $seller->id]);
        TransferFinancialDetails::factory()->create(['transfer_id' => $transfer->id, 'amount' => 10000, 'installments' => 3]);
        $connection = config('database.connections.'.DB::getDefaultConnection());
        $transferId = $transfer->id;
        $instanceId = $instance->id;
        $settle = static function () use ($connection, $transferId): void {
            config(['database.default' => 'test-mysql', 'database.connections.test-mysql' => $connection]);
            DB::purge('test-mysql');
            app(TransferFinancialSettlement::class)->transferMoneyBetweenClubs(Transfer::findOrFail($transferId));
        };

        $processes = new ProcessDriver(new Factory);
        $processes->run([$settle, $settle]);

        $this->assertDatabaseCount('accounts_debt_lines', 3);
        $this->assertSame(90000, $buyerAccount->fresh()->future_balance);
        $this->assertSame(90000, $buyerAccount->fresh()->transfer_budget);
        $this->assertSame(60000, $sellerAccount->fresh()->future_balance);
        $this->assertSame(60000, $sellerAccount->fresh()->transfer_budget);

        $instance->forceFill(['instance_date' => '2030-04-07'])->save();
        $repay = static function () use ($connection, $instanceId): void {
            config(['database.default' => 'test-mysql', 'database.connections.test-mysql' => $connection]);
            DB::purge('test-mysql');
            app(TransferFinancialSettlement::class)->processDueInstallments(Instance::findOrFail($instanceId));
        };

        $processes->run([$repay, $repay]);

        $this->assertSame(90000, $buyerAccount->fresh()->balance);
        $this->assertSame(60000, $sellerAccount->fresh()->balance);
        $this->assertSame(90000, $buyerAccount->fresh()->future_balance);
        $this->assertSame(60000, $sellerAccount->fresh()->future_balance);
        $this->assertSame(90000, $buyerAccount->fresh()->transfer_budget);
        $this->assertSame(60000, $sellerAccount->fresh()->transfer_budget);
        $this->assertSame(3, AccountsDebtLines::whereNotNull('paid_on')->count());
        $this->assertDatabaseCount('finance_transactions', 3);
    }
}
