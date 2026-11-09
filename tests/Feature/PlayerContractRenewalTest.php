<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Club;
use App\Models\Instance;
use App\Models\Player;
use App\Models\PlayerContract;
use App\Models\Season;
use App\Support\GameContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class PlayerContractRenewalTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_contract_centre_shows_only_active_managed_club_contracts(): void
    {
        [$instance, $club, $player] = $this->world();
        Player::factory()->create(['instance_id' => $instance->id, 'club_id' => $club->id, 'contract_id' => $player->contract_id, 'is_retired' => true]);
        $other = Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => 200]);
        Player::factory()->create(['instance_id' => $instance->id, 'club_id' => $other->id, 'contract_id' => $player->contract_id]);

        $this->withoutVite()->get('/contracts')->assertInertia(fn (Assert $page) => $page
            ->component('contracts/Contracts')->where('club.id', $club->id)->has('players', 1)
            ->where('players.0.id', $player->id)->where('players.0.expiring', true)
            ->where('players.0.ends_at', '2026-12-31'));
    }

    #[Test]
    public function the_renewal_screen_shows_current_terms_without_changing_the_contract(): void
    {
        [, , $player] = $this->world();

        $this->withoutVite()->get('/contracts/'.$player->id.'/renew')->assertInertia(fn (Assert $page) => $page
            ->component('contracts/RenewContract')->where('player.id', $player->id)
            ->where('player.terms.salary', 1000)->where('player.terms.transfer_fee', 0));
        $this->assertDatabaseHas('players_contracts', ['id' => $player->contract_id, 'contract_end' => '2026-12-31']);
    }

    #[Test]
    public function another_clubs_player_and_expired_contracts_are_not_renewable(): void
    {
        [$instance, , $player] = $this->world();
        $other = Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => 200]);
        $outsider = Player::factory()->create(['instance_id' => $instance->id, 'club_id' => $other->id, 'contract_id' => $player->contract_id]);

        $this->get('/contracts/'.$outsider->id.'/renew')->assertNotFound();
        $player->contract->forceFill(['contract_end' => '2026-08-31'])->save();
        $this->get('/contracts/'.$player->id.'/renew')->assertNotFound();
    }

    #[Test]
    public function guests_and_unemployed_managers_cannot_manage_contracts(): void
    {
        $this->get('/contracts')->assertRedirect('/login');
        [$instance] = $this->world();
        $instance->forceFill(['club_id' => null])->save();

        $this->get('/contracts')->assertRedirect('/');
    }

    #[Test]
    #[TestWith([6, '2027-03-01'])]
    #[TestWith([60, '2031-09-01'])]
    public function manual_renewals_accept_the_minimum_and_maximum_terms_and_charge_the_fee_once(int $months, string $endsAt): void
    {
        [, $club, $player] = $this->world();
        $payload = $this->offer($player) + ['months' => $months];

        $this->postJson('/api/contracts/'.$player->id.'/renew', $payload)->assertOk()->assertJsonPath('data.status', 'accepted');

        $this->assertDatabaseHas('players_contracts', ['id' => $player->contract_id, 'contract_start' => '2026-09-01', 'contract_end' => $endsAt, 'salary' => 1200, 'appearance' => 50, 'signing_fee' => 10000]);
        $this->assertDatabaseHas('accounts', ['club_id' => $club->id, 'balance' => 990000, 'future_balance' => 990000, 'transfer_budget' => 990000]);
        $this->assertDatabaseHas('player_contract_renewals', ['player_id' => $player->id, 'months' => $months, 'previous_contract_end' => '2026-12-31', 'contract_end' => $endsAt, 'status' => 'accepted']);
        $this->postJson('/api/contracts/'.$player->id.'/renew', $payload)->assertUnprocessable()->assertJsonValidationErrors('expected_contract_end');
        $this->assertDatabaseCount('player_contract_renewals', 1);
        $this->assertSame(990000, (int) $club->account->balance);
    }

    #[Test]
    #[TestWith(['months', 5])]
    #[TestWith(['months', 61])]
    #[TestWith(['months', 6.5])]
    #[TestWith(['salary', 0])]
    #[TestWith(['transfer_fee', -1])]
    #[TestWith(['goal', -1])]
    public function invalid_terms_return_422_and_leave_the_contract_unchanged(string $field, mixed $value): void
    {
        [, , $player] = $this->world();
        $payload = $this->offer($player) + ['months' => 12];
        $payload[$field] = $value;

        $this->postJson('/api/contracts/'.$player->id.'/renew', $payload)->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertDatabaseHas('players_contracts', ['id' => $player->contract_id, 'contract_end' => '2026-12-31', 'salary' => 1000]);
        $this->assertDatabaseCount('player_contract_renewals', 0);
    }

    #[Test]
    public function a_renewal_cannot_shorten_the_current_contract(): void
    {
        [, , $player] = $this->world();
        $player->contract->forceFill(['contract_end' => '2028-06-30'])->save();

        $this->postJson('/api/contracts/'.$player->id.'/renew', $this->offer($player->fresh()) + ['months' => 6])
            ->assertUnprocessable()->assertJsonValidationErrors('months');
        $this->assertDatabaseCount('player_contract_renewals', 0);
    }

    #[Test]
    public function unaffordable_fees_and_wages_are_rejected_without_spending_money(): void
    {
        [, $club, $player] = $this->world();
        $payload = $this->offer($player) + ['months' => 12];
        $payload['transfer_fee'] = 1000001;
        $this->postJson('/api/contracts/'.$player->id.'/renew', $payload)->assertUnprocessable()->assertJsonValidationErrors('transfer_fee');
        $payload['transfer_fee'] = 0;
        $payload['salary'] = 10000000;
        $this->postJson('/api/contracts/'.$player->id.'/renew', $payload)->assertUnprocessable()->assertJsonValidationErrors('salary');
        $this->assertDatabaseHas('accounts', ['club_id' => $club->id, 'balance' => 1000000]);
        $this->assertDatabaseCount('player_contract_renewals', 0);
    }

    #[Test]
    public function a_manager_cannot_renew_players_from_other_clubs_or_saves(): void
    {
        [$instance, , $player] = $this->world();
        $other = Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => 200]);
        $outsider = Player::factory()->create(['instance_id' => $instance->id, 'club_id' => $other->id, 'contract_id' => $player->contract_id]);
        $this->postJson('/api/contracts/'.$outsider->id.'/renew', $this->offer($player) + ['months' => 12])->assertNotFound();
        $foreign = Instance::factory()->create();
        $player->forceFill(['instance_id' => $foreign->id])->save();
        $this->postJson('/api/contracts/'.$player->id.'/renew', $this->offer($player) + ['months' => 12])->assertNotFound();
        $this->assertDatabaseCount('player_contract_renewals', 0);
    }

    /** @return array<string, mixed> */
    private function offer(Player $player): array
    {
        return ['expected_contract_end' => $player->contract->contract_end, 'salary' => 1200, 'appearance' => 50, 'transfer_fee' => 10000];
    }

    /** @return array{Instance, Club, Player} */
    private function world(): array
    {
        $instance = Instance::factory()->create(['instance_date' => '2026-09-01']);
        $season = Season::factory()->create(['instance_id' => $instance->id, 'start_date' => '2026-07-01', 'end_date' => '2027-06-30']);
        $club = Club::factory()->create(['instance_id' => $instance->id, 'stadium_id' => 100, 'rank' => 10]);
        Account::factory()->create(['club_id' => $club->id, 'balance' => 1000000, 'future_balance' => 1000000, 'transfer_budget' => 1000000]);
        $instance->forceFill(['club_id' => $club->id, 'season_id' => $season->id])->save();
        $contract = PlayerContract::factory()->create(['contract_start' => '2025-07-01', 'contract_end' => '2026-12-31', 'salary' => 1000, 'appearance' => 0, 'goal' => 0, 'assist' => 0, 'clean_sheet' => 0]);
        $player = Player::factory()->create(['instance_id' => $instance->id, 'club_id' => $club->id, 'contract_id' => $contract->id, 'ambition' => 10]);
        $this->actingAs($instance->user)->withHeaders(['instanceHash' => $instance->instance_hash])->withSession(['active_instance_hash' => $instance->instance_hash]);
        app(GameContext::class)->set($instance->id, $season->id, '2026-09-01');

        return [$instance, $club, $player];
    }
}
