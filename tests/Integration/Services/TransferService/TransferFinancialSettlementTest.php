<?php

namespace Tests\Integration\Services\TransferService;

use App\Events\NextDay;
use App\Events\WeeklyUpdate;
use App\Listeners\ProcessTransfers;
use App\Models\Account;
use App\Models\AccountsDebtLines;
use App\Models\Club;
use App\Models\FinanceTransactions;
use App\Models\Instance;
use App\Models\Transfer;
use App\Models\TransferFinancialDetails;
use App\Services\TransferService\TransferFinancialSettlement;
use App\Services\TransferService\TransferType;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Tests\TestCase;

class TransferFinancialSettlementTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_settles_a_transfer_without_installments_immediately(): void
    {
        [$transfer, $sourceAccount, $targetAccount] = $this->createTransferWithAccounts();

        TransferFinancialDetails::factory()->create([
            'transfer_id' => $transfer->id,
            'amount' => 10000,
            'installments' => 0,
        ]);

        app()->make(TransferFinancialSettlement::class)->transferMoneyBetweenClubs($transfer);

        $this->assertSame(90000, $sourceAccount->refresh()->balance);
        $this->assertSame(90000, $sourceAccount->future_balance);
        $this->assertSame(90000, $sourceAccount->transfer_budget);
        $this->assertSame(60000, $targetAccount->refresh()->balance);
        $this->assertSame(60000, $targetAccount->future_balance);
        $this->assertSame(60000, $targetAccount->transfer_budget);

        $this->assertDatabaseHas('finance_transactions', [
            'sending_account_id' => $sourceAccount->id,
            'receiving_account_id' => $targetAccount->id,
            'amount' => 10000,
            'transaction_date' => '2030-01-07 00:00:00',
            'category' => 'transfer',
            'reference' => 'transfer:'.$transfer->id,
        ]);
        app(TransferFinancialSettlement::class)->transferMoneyBetweenClubs($transfer);
        $this->assertSame(90000, $sourceAccount->fresh()->balance);
        $this->assertSame(90000, $sourceAccount->fresh()->transfer_budget);
        $this->assertSame(60000, $targetAccount->fresh()->transfer_budget);
        $this->assertDatabaseCount('finance_transactions', 1);
        $this->assertSame(0, AccountsDebtLines::count());
    }

    #[Test]
    public function it_creates_rounded_installment_debt_lines_and_applies_exact_committed_totals(): void
    {
        [$transfer, $sourceAccount, $targetAccount] = $this->createTransferWithAccounts();

        TransferFinancialDetails::factory()->create([
            'transfer_id' => $transfer->id,
            'amount' => 10000,
            'installments' => 3,
        ]);

        app()->make(TransferFinancialSettlement::class)->transferMoneyBetweenClubs($transfer);

        $installmentAmounts = AccountsDebtLines::query()
            ->orderBy('due_date')
            ->pluck('amount')
            ->all();

        $this->assertSame([3000, 3000, 4000], $installmentAmounts);
        $this->assertSame(['2030-02-07', '2030-03-07', '2030-04-07'], AccountsDebtLines::query()->orderBy('due_date')->pluck('due_date')->all());
        $this->assertSame(10000, array_sum($installmentAmounts));

        $this->assertSame(100000, $sourceAccount->refresh()->balance);
        $this->assertSame(90000, $sourceAccount->future_balance);
        $this->assertSame(90000, $sourceAccount->transfer_budget);

        $this->assertSame(50000, $targetAccount->refresh()->balance);
        $this->assertSame(60000, $targetAccount->future_balance);
        $this->assertSame(60000, $targetAccount->transfer_budget);

        $this->assertSame(0, FinanceTransactions::count());

        app(TransferFinancialSettlement::class)->transferMoneyBetweenClubs($transfer);

        $this->assertDatabaseCount('accounts_debt_lines', 3);
        $this->assertDatabaseHas('transfers', ['id' => $transfer->id, 'financially_settled_on' => '2030-01-07']);
        $this->assertSame(90000, $sourceAccount->fresh()->future_balance);
        $this->assertSame(90000, $sourceAccount->fresh()->transfer_budget);
        $this->assertSame(60000, $targetAccount->fresh()->future_balance);
        $this->assertSame(60000, $targetAccount->fresh()->transfer_budget);
    }

    #[Test]
    public function due_and_overdue_installments_move_cash_once_without_recounting_commitments(): void
    {
        [$transfer, $source, $target] = $this->createTransferWithAccounts();
        TransferFinancialDetails::factory()->create(['transfer_id' => $transfer->id, 'amount' => 10000, 'installments' => 3]);
        $settlement = app(TransferFinancialSettlement::class);
        $settlement->transferMoneyBetweenClubs($transfer);
        $instance = $transfer->instance;

        $instance->instance_date = '2030-02-06';
        $settlement->processDueInstallments($instance);
        $this->assertDatabaseCount('finance_transactions', 0);

        $instance->instance_date = '2030-03-07';
        $settlement->processDueInstallments($instance);
        $settlement->processDueInstallments($instance);

        $this->assertSame(94000, $source->fresh()->balance);
        $this->assertSame(56000, $target->fresh()->balance);
        $this->assertSame(90000, $source->fresh()->future_balance);
        $this->assertSame(60000, $target->fresh()->future_balance);
        $this->assertSame(90000, $source->fresh()->transfer_budget);
        $this->assertSame(60000, $target->fresh()->transfer_budget);
        $this->assertSame(2, AccountsDebtLines::whereNotNull('paid_on')->count());
        $this->assertDatabaseCount('finance_transactions', 2);
        $debt = AccountsDebtLines::orderBy('id')->firstOrFail();
        $this->assertDatabaseHas('finance_transactions', [
            'id' => $debt->transaction_id, 'reference' => 'transfer-installment:'.$debt->id,
            'category' => 'transfer_installment', 'amount' => 3000, 'transaction_date' => '2030-03-07 00:00:00',
        ]);

        $instance->instance_date = '2030-04-07';
        $settlement->processDueInstallments($instance);
        $settlement->transferMoneyBetweenClubs($transfer);

        $this->assertSame(90000, $source->fresh()->balance);
        $this->assertSame(60000, $target->fresh()->balance);
        $this->assertSame(3, AccountsDebtLines::whereNotNull('paid_on')->count());
        $this->assertDatabaseCount('finance_transactions', 3);
        $this->assertDatabaseCount('accounts_debt_lines', 3);
    }

    #[Test]
    public function repayments_are_scoped_to_the_supplied_save_and_allow_insufficient_cash(): void
    {
        [$transfer, $source, $target] = $this->createTransferWithAccounts();
        [$foreignTransfer, $foreignSource, $foreignTarget] = $this->createTransferWithAccounts();
        $settlement = app(TransferFinancialSettlement::class);
        foreach ([$transfer, $foreignTransfer] as $item) {
            TransferFinancialDetails::factory()->create(['transfer_id' => $item->id, 'amount' => 120000, 'installments' => 1]);
            $settlement->transferMoneyBetweenClubs($item);
        }
        $instance = $transfer->instance;
        $instance->instance_date = '2030-02-07';

        $settlement->processDueInstallments($instance);

        $this->assertSame(-20000, $source->fresh()->balance);
        $this->assertSame(-20000, $source->fresh()->future_balance);
        $this->assertSame(-20000, $source->fresh()->transfer_budget);
        $this->assertSame(170000, $target->fresh()->balance);
        $this->assertSame(100000, $foreignSource->fresh()->balance);
        $this->assertSame(50000, $foreignTarget->fresh()->balance);
        $this->assertDatabaseCount('finance_transactions', 1);
    }

    #[Test]
    public function a_failed_payment_rolls_back_cash_and_paid_marker_and_can_be_retried(): void
    {
        [$transfer, $source, $target] = $this->createTransferWithAccounts();
        TransferFinancialDetails::factory()->create(['transfer_id' => $transfer->id, 'amount' => 10000, 'installments' => 1]);
        $settlement = app(TransferFinancialSettlement::class);
        $settlement->transferMoneyBetweenClubs($transfer);
        $instance = $transfer->instance;
        $instance->instance_date = '2030-02-07';
        $eventName = 'eloquent.updating: '.AccountsDebtLines::class;
        Event::listen($eventName, function (): void {
            throw new RuntimeException('Paid marker failed.');
        });

        try {
            $settlement->processDueInstallments($instance);
            $this->fail('Expected payment to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Paid marker failed.', $exception->getMessage());
        } finally {
            Event::forget($eventName);
        }

        $this->assertSame(100000, $source->fresh()->balance);
        $this->assertSame(50000, $target->fresh()->balance);
        $this->assertSame(0, AccountsDebtLines::whereNotNull('paid_on')->count());
        $this->assertDatabaseCount('finance_transactions', 0);

        $settlement->processDueInstallments($instance);

        $this->assertSame(90000, $source->fresh()->balance);
        $this->assertSame(60000, $target->fresh()->balance);
        $this->assertSame(1, AccountsDebtLines::whereNotNull('paid_on')->count());
        $this->assertDatabaseCount('finance_transactions', 1);
    }

    #[Test]
    public function a_failed_commitment_rolls_back_its_schedule_and_can_be_retried(): void
    {
        [$transfer, $source, $target] = $this->createTransferWithAccounts();
        TransferFinancialDetails::factory()->create(['transfer_id' => $transfer->id, 'amount' => 10000, 'installments' => 3]);
        $eventName = 'eloquent.updating: '.Transfer::class;
        Event::listen($eventName, function (): void {
            throw new RuntimeException('Settlement marker failed.');
        });

        try {
            app(TransferFinancialSettlement::class)->transferMoneyBetweenClubs($transfer);
            $this->fail('Expected settlement to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Settlement marker failed.', $exception->getMessage());
        } finally {
            Event::forget($eventName);
        }

        $this->assertSame(100000, $source->fresh()->future_balance);
        $this->assertSame(100000, $source->fresh()->transfer_budget);
        $this->assertSame(50000, $target->fresh()->future_balance);
        $this->assertSame(50000, $target->fresh()->transfer_budget);
        $this->assertDatabaseCount('accounts_debt_lines', 0);

        app(TransferFinancialSettlement::class)->transferMoneyBetweenClubs($transfer);

        $this->assertDatabaseCount('accounts_debt_lines', 3);
        $this->assertSame(90000, $source->fresh()->future_balance);
    }

    #[Test]
    public function legacy_debts_without_transfer_references_are_paid_by_the_daily_listener(): void
    {
        [$transfer, $source, $target] = $this->createTransferWithAccounts();
        $source->forceFill(['future_balance' => 90000, 'transfer_budget' => 90000])->save();
        $target->forceFill(['future_balance' => 60000, 'transfer_budget' => 60000])->save();
        $debt = AccountsDebtLines::create([
            'sending_account_id' => $source->id, 'receiving_account_id' => $target->id,
            'amount' => 10000, 'created_at' => '2029-11-07', 'due_date' => '2029-12-07',
        ]);
        Event::fake([WeeklyUpdate::class]);
        Event::assertListening(NextDay::class, ProcessTransfers::class);

        app(ProcessTransfers::class)->handle(new NextDay($transfer->instance));

        $this->assertSame(90000, $source->fresh()->balance);
        $this->assertSame(60000, $target->fresh()->balance);
        $this->assertDatabaseHas('accounts_debt_lines', ['id' => $debt->id, 'paid_on' => '2030-01-07']);
        $this->assertDatabaseCount('finance_transactions', 1);
    }

    #[Test]
    public function zero_fee_settlement_is_also_safe_to_repeat(): void
    {
        [$transfer, $source, $target] = $this->createTransferWithAccounts();
        TransferFinancialDetails::factory()->create(['transfer_id' => $transfer->id, 'amount' => 0, 'installments' => 0]);

        app(TransferFinancialSettlement::class)->transferMoneyBetweenClubs($transfer);
        app(TransferFinancialSettlement::class)->transferMoneyBetweenClubs($transfer);

        $this->assertSame(100000, $source->fresh()->balance);
        $this->assertSame(50000, $target->fresh()->balance);
        $this->assertDatabaseHas('transfers', ['id' => $transfer->id, 'financially_settled_on' => '2030-01-07']);
        $this->assertDatabaseCount('finance_transactions', 0);
    }

    #[Test]
    #[TestWith([10000, 6, [1666, 1666, 1666, 1666, 1666, 1670]])]
    #[TestWith([100, 3, [0, 0, 100]])]
    public function rounded_installments_never_become_negative_and_zero_lines_can_be_processed(int $amount, int $count, array $expected): void
    {
        [$transfer, $source, $target] = $this->createTransferWithAccounts();
        TransferFinancialDetails::factory()->create(['transfer_id' => $transfer->id, 'amount' => $amount, 'installments' => $count]);
        $settlement = app(TransferFinancialSettlement::class);

        $settlement->transferMoneyBetweenClubs($transfer);

        $this->assertSame($expected, AccountsDebtLines::orderBy('installment_number')->pluck('amount')->all());
        $instance = $transfer->instance;
        $instance->instance_date = '2031-01-07';
        $settlement->processDueInstallments($instance);

        $this->assertSame(100000 - $amount, $source->fresh()->balance);
        $this->assertSame(50000 + $amount, $target->fresh()->balance);
        $this->assertSame($count, AccountsDebtLines::whereNotNull('paid_on')->count());
        $this->assertSame($amount, (int) FinanceTransactions::sum('amount'));
    }

    #[Test]
    public function month_end_installments_stay_in_their_due_month(): void
    {
        [$transfer] = $this->createTransferWithAccounts();
        $transfer->instance->forceFill(['instance_date' => '2030-01-31'])->save();
        TransferFinancialDetails::factory()->create(['transfer_id' => $transfer->id, 'amount' => 10000, 'installments' => 3]);

        app(TransferFinancialSettlement::class)->transferMoneyBetweenClubs($transfer);

        $this->assertSame(['2030-02-28', '2030-03-31', '2030-04-30'], AccountsDebtLines::orderBy('installment_number')->pluck('due_date')->all());
    }

    #[Test]
    public function commitments_cannot_be_created_between_saves(): void
    {
        [$transfer, $source, $target] = $this->createTransferWithAccounts();
        $foreignInstance = Instance::factory()->create();
        $transfer->targetClub->forceFill(['instance_id' => $foreignInstance->id])->save();
        TransferFinancialDetails::factory()->create(['transfer_id' => $transfer->id, 'amount' => 10000, 'installments' => 3]);

        try {
            app(TransferFinancialSettlement::class)->transferMoneyBetweenClubs($transfer);
            $this->fail('Expected a cross-save commitment to be rejected.');
        } catch (DomainException $exception) {
            $this->assertSame('Transfer accounts must belong to different clubs in the same game instance.', $exception->getMessage());
        }

        $this->assertSame(100000, $source->fresh()->future_balance);
        $this->assertSame(50000, $target->fresh()->future_balance);
        $this->assertDatabaseCount('accounts_debt_lines', 0);
        $this->assertDatabaseHas('transfers', ['id' => $transfer->id, 'financially_settled_on' => null]);
    }

    private function createTransferWithAccounts(): array
    {
        $instance = Instance::factory()->create(['instance_date' => '2030-01-07']);
        $sourceClub = Club::factory()->create(['instance_id' => $instance->id]);
        $targetClub = Club::factory()->create(['instance_id' => $instance->id]);

        $sourceAccount = Account::factory()->create([
            'club_id' => $sourceClub->id,
            'balance' => 100000,
            'future_balance' => 100000,
            'transfer_budget' => 100000,
        ]);

        $targetAccount = Account::factory()->create([
            'club_id' => $targetClub->id,
            'balance' => 50000,
            'future_balance' => 50000,
            'transfer_budget' => 50000,
        ]);

        $transfer = Transfer::factory()->create([
            'instance_id' => $instance->id,
            'source_club_id' => $sourceClub->id,
            'target_club_id' => $targetClub->id,
            'transfer_type' => TransferType::PERMANENT_TRANSFER->value,
        ]);

        return [$transfer, $sourceAccount, $targetAccount];
    }
}
