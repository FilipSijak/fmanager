<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Club;
use App\Models\Instance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinanceApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_finances_for_the_managed_club(): void
    {
        $instance = Instance::factory()->create([
            'instance_hash' => 'finance-instance',
        ]);
        $club = Club::factory()->create([
            'instance_id' => $instance->id,
        ]);
        $instance->forceFill(['club_id' => $club->id])->saveQuietly();

        Account::factory()->create([
            'club_id' => $club->id,
            'balance' => 1000,
            'future_balance' => 900,
            'allowed_debt' => 500,
            'transfer_budget' => 700,
            'salaries_yearly_budget' => 20000,
        ]);

        $response = $this
            ->actingAs($instance->user)
            ->withHeaders(['instanceHash' => $instance->instance_hash])
            ->getJson('/api/finance');

        $response
            ->assertOk()
            ->assertJsonPath('data.balance', 1000)
            ->assertJsonPath('data.future_balance', 900)
            ->assertJsonPath('data.allowed_debt', 500)
            ->assertJsonPath('data.transfer_budget', 700)
            ->assertJsonPath('data.annual_salary_budget', 20000)
            ->assertJsonPath('data.annual_player_wages', 0)
            ->assertJsonPath('data.remaining_annual_salary_budget', 20000);
    }
}
