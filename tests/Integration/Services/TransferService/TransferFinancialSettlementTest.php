<?php

namespace Tests\Integration\Services\TransferService;

use App\Models\Account;
use App\Models\AccountsDebtLines;
use App\Models\Club;
use App\Models\FinanceTransactions;
use App\Models\Transfer;
use App\Models\TransferFinancialDetails;
use App\Services\TransferService\TransferFinancialSettlement;
use App\Services\TransferService\TransferTypes;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
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
            'transaction_date' => Carbon::today()->toDateString(),
        ]);
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
        $this->assertSame(10000, array_sum($installmentAmounts));

        $this->assertSame(100000, $sourceAccount->refresh()->balance);
        $this->assertSame(90000, $sourceAccount->future_balance);
        $this->assertSame(90000, $sourceAccount->transfer_budget);

        $this->assertSame(50000, $targetAccount->refresh()->balance);
        $this->assertSame(60000, $targetAccount->future_balance);
        $this->assertSame(60000, $targetAccount->transfer_budget);

        $this->assertSame(0, FinanceTransactions::count());
    }

    private function createTransferWithAccounts(): array
    {
        $sourceClub = Club::factory()->create(['id' => 10]);
        $targetClub = Club::factory()->create(['id' => 20]);

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
            'source_club_id' => $sourceClub->id,
            'target_club_id' => $targetClub->id,
            'transfer_type' => TransferTypes::PERMANENT_TRANSFER,
        ]);

        return [$transfer, $sourceAccount, $targetAccount];
    }
}
