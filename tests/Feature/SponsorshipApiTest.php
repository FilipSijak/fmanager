<?php

namespace Tests\Feature;

use App\Models\Club;
use App\Models\Instance;
use App\Models\Season;
use App\Models\SponsorshipContract;
use App\Models\SponsorshipPayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SponsorshipApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guests_cannot_view_sponsors(): void
    {
        $this->getJson('/api/finance/sponsors')->assertUnauthorized();
    }

    #[Test]
    public function sponsors_require_an_active_game(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/finance/sponsors')
            ->assertUnprocessable()->assertJsonPath('message', 'Select a game first.');
    }

    #[Test]
    public function unemployed_managers_cannot_view_club_sponsors(): void
    {
        $instance = Instance::factory()->create(['club_id' => null]);

        $this->actingAs($instance->user)->withHeader('instanceHash', $instance->instance_hash)
            ->getJson('/api/finance/sponsors')->assertForbidden();
    }

    #[Test]
    public function another_users_game_cannot_be_used_to_view_sponsors(): void
    {
        $instance = Instance::factory()->create();

        $this->actingAs(User::factory()->create())->withHeader('instanceHash', $instance->instance_hash)
            ->getJson('/api/finance/sponsors')->assertNotFound();
    }

    #[Test]
    public function the_api_and_finance_page_show_only_the_managed_clubs_contracts_and_current_season_payments(): void
    {
        $this->withoutVite();
        $contract = SponsorshipContract::factory()->create();
        $instance = Instance::query()->findOrFail($contract->instance_id);
        $season = Season::factory()->create(['instance_id' => $instance->id, 'start_date' => '2026-08-15', 'end_date' => '2027-06-15']);
        $instance->forceFill(['club_id' => $contract->club_id, 'season_id' => $season->id, 'instance_date' => '2026-08-16'])->save();
        SponsorshipPayment::factory()->create(['sponsorship_contract_id' => $contract->id, 'season_id' => $season->id]);
        $oldSeason = Season::factory()->create(['instance_id' => $instance->id]);
        SponsorshipPayment::factory()->create(['sponsorship_contract_id' => $contract->id, 'season_id' => $oldSeason->id]);
        $expired = SponsorshipContract::factory()->create([
            'instance_id' => $instance->id, 'club_id' => $contract->club_id,
            'sponsor_account_id' => $contract->sponsor_account_id, 'starts_at' => '2025-08-15', 'expires_at' => '2026-06-15',
        ]);
        $otherClub = Club::factory()->create(['instance_id' => $instance->id]);
        SponsorshipContract::factory()->create(['instance_id' => $instance->id, 'club_id' => $otherClub->id]);
        SponsorshipContract::factory()->create();

        $this->actingAs($instance->user)->withHeader('instanceHash', $instance->instance_hash)
            ->getJson('/api/finance/sponsors?club_id='.$otherClub->id)
            ->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $contract->id)
            ->assertJsonPath('data.0.annual_amount', 15000000)
            ->assertJsonPath('data.0.sponsor_rank', 10)
            ->assertJsonPath('data.0.starts_at', '2026-08-15')
            ->assertJsonPath('data.0.expires_at', '2031-06-15')
            ->assertJsonPath('data.0.status', 'active')
            ->assertJsonPath('data.0.paid_this_season', 15000000)
            ->assertJsonPath('data.0.payment_frequency', 'season_start')
            ->assertJsonPath('data.1.id', $expired->id)
            ->assertJsonPath('data.1.status', 'expired');

        $this->get('/finance')->assertInertia(fn (Assert $page) => $page->component('Finance')
            ->has('sponsors', 2)->where('sponsors.0.id', $contract->id)
            ->where('sponsors.0.annual_amount', 15000000)->where('sponsors.0.paid_this_season', 15000000));
    }

    #[Test]
    public function contracts_are_upcoming_before_the_first_day_of_the_season(): void
    {
        $contract = SponsorshipContract::factory()->create();
        $instance = Instance::query()->findOrFail($contract->instance_id);
        $season = Season::factory()->create(['instance_id' => $instance->id, 'start_date' => '2026-08-15', 'end_date' => '2027-06-15']);
        $instance->forceFill(['club_id' => $contract->club_id, 'season_id' => $season->id, 'instance_date' => '2026-07-01'])->save();

        $this->actingAs($instance->user)->withHeader('instanceHash', $instance->instance_hash)
            ->getJson('/api/finance/sponsors')->assertOk()
            ->assertJsonPath('data.0.status', 'upcoming')
            ->assertJsonPath('data.0.starts_at', '2026-08-15')
            ->assertJsonPath('data.0.paid_this_season', 0);
    }

    #[Test]
    public function clubs_without_contracts_receive_an_empty_list(): void
    {
        $instance = Instance::factory()->create();
        $season = Season::factory()->create(['instance_id' => $instance->id]);
        $club = Club::factory()->create(['instance_id' => $instance->id]);
        $instance->forceFill(['club_id' => $club->id, 'season_id' => $season->id])->save();

        $this->actingAs($instance->user)->withHeader('instanceHash', $instance->instance_hash)
            ->getJson('/api/finance/sponsors')->assertOk()->assertJsonPath('data', []);
    }
}
