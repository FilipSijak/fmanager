<?php

namespace Tests\Feature;

use App\GameEntityType;
use App\Models\Account;
use App\Models\Club;
use App\Models\FinanceEntityLoan;
use App\Models\GameEntity;
use App\Models\GameEntityAccount;
use App\Models\Instance;
use App\Models\Player;
use App\Models\PlayerContract;
use App\Models\Season;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinancePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    #[Test]
    public function guests_must_sign_in_to_view_finances(): void
    {
        $this->get('/finance')->assertRedirect('/login');
    }

    #[Test]
    public function it_renders_the_finance_page(): void
    {
        [$instance, $club] = $this->world();
        $contract = PlayerContract::factory()->create(['salary' => 1000]);
        Player::factory()->create([
            'instance_id' => $instance->id,
            'club_id' => $club->id,
            'contract_id' => $contract->id,
        ]);

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/finance')
            ->assertInertia(fn (Assert $page) => $page->component('Finance')
                ->where('club.name', 'Finance FC')
                ->where('season.start_date', '2026-08-01')
                ->where('season.end_date', '2027-06-30')
                ->where('finances.balance', -1000)
                ->where('finances.future_balance', -2000)
                ->where('finances.transfer_budget', 25000)
                ->where('finances.allowed_debt', 100000)
                ->where('finances.annual_salary_budget', 60000)
                ->where('finances.annual_player_wages', 52000)
                ->where('finances.remaining_annual_salary_budget', 8000)
                ->has('loans', 0)
                ->where('loanOptions.0.lender', 'bank')
                ->where('loanOptions.0.annual_interest_rate', 0.08)
                ->where('loanOptions.0.maximum_months', 36)
                ->where('loanOptions.1.lender', 'loan_sharks')
                ->where('loanOptions.1.annual_interest_rate', 0.15)
                ->where('loanOptions.1.maximum_months', 24));
    }

    #[Test]
    public function approved_loans_refresh_the_balance_and_show_only_the_managed_clubs_repayment_schedule(): void
    {
        [$instance] = $this->world();
        $bank = GameEntity::factory()->create(['instance_id' => $instance->id, 'type' => GameEntityType::BANK]);
        $lender = GameEntityAccount::factory()->create([
            'instance_id' => $instance->id, 'game_entity_id' => $bank->id,
            'balance' => 1000000, 'future_balance' => 1000000,
        ]);
        $otherClub = Club::factory()->create(['instance_id' => $instance->id]);
        $otherAccount = Account::factory()->create(['club_id' => $otherClub->id]);
        FinanceEntityLoan::factory()->create([
            'instance_id' => $instance->id,
            'borrower_club_account_id' => $otherAccount->id,
            'lender_game_entity_account_id' => $lender->id,
        ]);

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->withHeader('Origin', 'http://localhost')
            ->postJson('/api/finance/loans', ['amount' => 12000, 'length_months' => 12, 'lender' => 'bank'])
            ->assertOk();

        $this->get('/finance')->assertInertia(fn (Assert $page) => $page->component('Finance')
            ->where('finances.balance', 11000)
            ->has('loans', 1)
            ->where('loans.0.loan_type', 'cash')
            ->where('loans.0.principal', 12000)
            ->where('loans.0.interest_amount', 960)
            ->where('loans.0.total_amount', 12960)
            ->where('loans.0.status', 'active')
            ->has('loans.0.installments', 12)
            ->where('loans.0.installments.0.amount', 1080)
            ->where('loans.0.installments.0.due_date', '2026-10-15')
            ->where('loans.0.installments.0.paid_at', null));
    }

    #[Test]
    public function finances_without_an_account_are_unavailable_instead_of_showing_mock_values(): void
    {
        [$instance, $club] = $this->world();
        Account::query()->where('club_id', $club->id)->delete();

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/finance')->assertInertia(fn (Assert $page) => $page
            ->where('finances', null)->has('loans', 0));
    }

    #[Test]
    public function unemployed_managers_are_redirected_away_from_finances(): void
    {
        [$instance] = $this->world();
        $instance->forceFill(['club_id' => null])->saveQuietly();

        $this->actingAs($instance->user)->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/finance')->assertRedirect('/');
    }

    #[Test]
    public function another_users_game_cannot_be_used_to_view_finances(): void
    {
        [$instance] = $this->world();

        $this->actingAs(User::factory()->create())->withSession(['active_instance_hash' => $instance->instance_hash])
            ->get('/finance')->assertNotFound();
    }

    /** @return array{Instance, Club} */
    private function world(): array
    {
        $instance = Instance::factory()->create(['instance_date' => '2026-09-15']);
        $season = Season::factory()->create([
            'instance_id' => $instance->id, 'start_date' => '2026-08-01', 'end_date' => '2027-06-30',
        ]);
        $club = Club::factory()->create(['instance_id' => $instance->id, 'name' => 'Finance FC']);
        $instance->forceFill(['club_id' => $club->id, 'season_id' => $season->id])->saveQuietly();
        Account::factory()->create([
            'club_id' => $club->id, 'balance' => -1000, 'future_balance' => -2000,
            'allowed_debt' => 100000, 'transfer_budget' => 25000, 'salaries_yearly_budget' => 60000,
        ]);

        return [$instance, $club];
    }
}
