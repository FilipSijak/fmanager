<?php

namespace Tests\Feature;

use App\Events\NextDay;
use App\Events\WeeklyUpdate;
use App\Listeners\PayClubWages;
use App\Models\Account;
use App\Models\Club;
use App\Models\FinanceTransactions;
use App\Models\Instance;
use App\Models\Player;
use App\Models\PlayerContract;
use App\Models\StaffCoaching;
use App\Models\StaffContract;
use App\Models\StaffPhysio;
use App\Models\StaffScout;
use App\Services\FinanceService\PayrollService;
use App\Support\GameContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class PayrollServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function monday_payroll_pays_players_and_all_staff_roles_in_each_club_of_the_supplied_game(): void
    {
        [$instance, $club, $account] = $this->world();
        $this->player($club, 1000);
        $this->player($club, 250);
        $this->staff($club, StaffCoaching::class, 200);
        $this->staff($club, StaffScout::class, 300);
        $this->staff($club, StaffPhysio::class, 400);
        $otherClub = Club::factory()->create(['instance_id' => $instance->id]);
        $otherAccount = Account::factory()->create(['club_id' => $otherClub->id, 'balance' => 10000, 'future_balance' => 10000]);
        $this->player($otherClub, 500);
        [$foreignInstance, $foreignClub, $foreignAccount] = $this->world();
        $this->player($foreignClub, 800);
        app(GameContext::class)->set($foreignInstance->id, 999, '2040-01-01');
        $budget = $account->transfer_budget;

        app(PayrollService::class)->payDueWages($instance);

        $this->assertSame(7850, $account->fresh()->balance);
        $this->assertSame(7850, $account->fresh()->future_balance);
        $this->assertSame($budget, $account->fresh()->transfer_budget);
        $this->assertSame(9500, $otherAccount->fresh()->balance);
        $this->assertSame(10000, $foreignAccount->fresh()->balance);
        $this->assertDatabaseHas('club_payroll_runs', ['club_id' => $club->id, 'paid_on' => '2030-01-07', 'player_wages' => 1250, 'staff_wages' => 900]);
        $this->assertDatabaseHas('finance_transactions', ['sending_account_id' => $account->id, 'receiving_account_id' => null, 'amount' => 1250, 'category' => 'player_wages', 'transaction_date' => '2030-01-07 00:00:00']);
        $this->assertDatabaseCount('finance_transactions', 3);
        $this->assertDatabaseCount('club_payroll_runs', 2);
    }

    #[Test]
    public function repeating_payday_does_not_recalculate_or_charge_again_and_next_week_is_paid(): void
    {
        [$instance, $club, $account] = $this->world();
        $player = $this->player($club, 1000);
        $payroll = app(PayrollService::class);
        $payroll->payDueWages($instance);
        $player->contract->forceFill(['salary' => 1500])->save();

        $payroll->payDueWages($instance);
        $this->assertSame(9000, $account->fresh()->balance);
        $this->assertDatabaseCount('club_payroll_runs', 1);

        $instance->forceFill(['instance_date' => '2030-01-14'])->save();
        $payroll->payDueWages($instance);

        $this->assertSame(7500, $account->fresh()->balance);
        $this->assertDatabaseCount('finance_transactions', 2);
        $this->assertDatabaseCount('club_payroll_runs', 2);
    }

    #[Test]
    public function other_weekdays_do_not_pay_wages(): void
    {
        [$instance, $club, $account] = $this->world();
        $this->player($club, 1000);
        $instance->forceFill(['instance_date' => '2030-01-08'])->save();

        app(PayrollService::class)->payDueWages($instance);

        $this->assertSame(10000, $account->fresh()->balance);
        $this->assertDatabaseCount('finance_transactions', 0);
        $this->assertDatabaseCount('club_payroll_runs', 0);
    }

    #[Test]
    public function payroll_excludes_retired_uncontracted_expired_and_future_players_and_staff(): void
    {
        [$instance, $club, $account] = $this->world();
        $this->player($club, 1000, ['contract_start' => '2030-01-07', 'contract_end' => '2030-01-07']);
        $this->player($club, 2000, ['contract_end' => '2030-01-06']);
        $this->player($club, 3000, ['contract_start' => '2030-01-08']);
        $this->player($club, 4000, [], ['is_retired' => true]);
        Player::factory()->create(['instance_id' => $instance->id, 'club_id' => $club->id, 'contract_id' => null]);
        $this->staff($club, StaffCoaching::class, 100, ['contract_start' => '2030-01-07', 'contract_end' => '2030-01-07']);
        $this->staff($club, StaffScout::class, 200, ['contract_end' => '2030-01-06']);
        $this->staff($club, StaffPhysio::class, 300, ['contract_start' => '2030-01-08']);
        $this->staff($club, StaffCoaching::class, 400, [], ['is_retired' => true]);
        StaffScout::factory()->create(['instance_id' => $instance->id, 'club_id' => $club->id, 'contract_id' => null]);

        app(PayrollService::class)->payDueWages($instance);

        $this->assertSame(8900, $account->fresh()->balance);
        $this->assertDatabaseHas('club_payroll_runs', ['club_id' => $club->id, 'player_wages' => 1000, 'staff_wages' => 100]);
    }

    #[Test]
    public function the_parent_club_pays_loaned_players_and_insufficient_cash_allows_an_overdraft(): void
    {
        [$instance, $club, $account] = $this->world();
        $loanClub = Club::factory()->create(['instance_id' => $instance->id]);
        $loanAccount = Account::factory()->create(['club_id' => $loanClub->id, 'balance' => 10000, 'future_balance' => 10000]);
        $this->player($club, 12000, [], ['loan_club_id' => $loanClub->id]);

        app(PayrollService::class)->payDueWages($instance);

        $this->assertSame(-2000, $account->fresh()->balance);
        $this->assertSame(-2000, $account->fresh()->future_balance);
        $this->assertSame(10000, $loanAccount->fresh()->balance);
        $this->assertDatabaseCount('finance_transactions', 1);
        $this->assertDatabaseHas('club_payroll_runs', ['club_id' => $loanClub->id, 'player_wages' => 0, 'staff_wages' => 0]);
    }

    #[Test]
    public function a_failed_staff_payment_rolls_back_player_payment_and_can_be_retried(): void
    {
        [$instance, $club, $account] = $this->world();
        $this->player($club, 1000);
        $this->staff($club, StaffCoaching::class, 200);
        $eventName = 'eloquent.creating: '.FinanceTransactions::class;
        Event::listen($eventName, function (FinanceTransactions $transaction): void {
            if ($transaction->category === 'staff_wages') {
                throw new RuntimeException('Staff payment failed.');
            }
        });

        try {
            app(PayrollService::class)->payDueWages($instance);
            $this->fail('Expected staff payment to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Staff payment failed.', $exception->getMessage());
        } finally {
            Event::forget($eventName);
        }

        $this->assertSame(10000, $account->fresh()->balance);
        $this->assertSame(10000, $account->fresh()->future_balance);
        $this->assertDatabaseCount('finance_transactions', 0);
        $this->assertDatabaseCount('club_payroll_runs', 0);

        app(PayrollService::class)->payDueWages($instance);

        $this->assertSame(8800, $account->fresh()->balance);
        $this->assertDatabaseCount('club_payroll_runs', 1);
    }

    #[Test]
    public function the_weekly_wage_listener_is_registered_and_pays_wages_using_the_event_instance(): void
    {
        [$instance, $club, $account] = $this->world();
        $this->player($club, 1000);
        Event::fake([NextDay::class]);
        Event::assertListening(WeeklyUpdate::class, PayClubWages::class);

        event(new WeeklyUpdate($instance));

        $this->assertSame(9000, $account->fresh()->balance);
    }

    /** @return array{Instance, Club, Account} */
    private function world(): array
    {
        $instance = Instance::factory()->create(['instance_date' => '2030-01-07']);
        $club = Club::factory()->create(['instance_id' => $instance->id]);
        $account = Account::factory()->create(['club_id' => $club->id, 'balance' => 10000, 'future_balance' => 10000]);

        return [$instance, $club, $account];
    }

    /** @param array<string, mixed> $terms
     * @param  array<string, mixed>  $attributes
     */
    private function player(Club $club, int $salary, array $terms = [], array $attributes = []): Player
    {
        $contract = PlayerContract::factory()->create($terms + ['salary' => $salary, 'contract_start' => '2029-07-01', 'contract_end' => '2030-06-30']);

        return Player::factory()->create($attributes + ['instance_id' => $club->instance_id, 'club_id' => $club->id, 'contract_id' => $contract->id]);
    }

    /** @param class-string<StaffCoaching|StaffScout|StaffPhysio> $staffClass
     * @param  array<string, mixed>  $terms
     * @param  array<string, mixed>  $attributes
     */
    private function staff(Club $club, string $staffClass, int $salary, array $terms = [], array $attributes = []): void
    {
        $contract = StaffContract::factory()->create($terms + ['salary' => $salary, 'contract_start' => '2029-07-01', 'contract_end' => '2030-06-30']);
        $staffClass::factory()->create($attributes + ['instance_id' => $club->instance_id, 'club_id' => $club->id, 'contract_id' => $contract->id]);
    }
}
