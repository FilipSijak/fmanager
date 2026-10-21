<?php

namespace Tests\Feature;

use App\EntityTransactionType;
use App\Events\NextDay;
use App\Events\SeasonStarted;
use App\GameEntityType;
use App\Listeners\PayClubSponsorships;
use App\Models\Account;
use App\Models\Club;
use App\Models\FinanceTransactionEntity;
use App\Models\GameEntity;
use App\Models\GameEntityAccount;
use App\Models\Instance;
use App\Models\Season;
use App\Models\SponsorshipContract;
use App\Services\FinanceService\SponsorshipService;
use App\Services\SeasonService\SeasonService;
use App\Support\GameContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SponsorshipTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function initial_contracts_start_with_the_season_and_end_after_one_to_five_whole_seasons(): void
    {
        [$instance, $club, $sponsor] = $this->world('2026-07-01');
        $club->forceFill(['rank' => 10])->save();
        Club::factory()->count(10)->create(['instance_id' => $instance->id, 'rank' => 10]);
        Club::factory()->create(['instance_id' => $instance->id + 1]);
        $service = app(SponsorshipService::class);

        $service->createInitialContracts($instance);
        $originalContracts = SponsorshipContract::query()->orderBy('id')->get();
        $service->createInitialContracts($instance);

        $this->assertCount(11, $originalContracts);
        $this->assertDatabaseCount('sponsorship_contracts', 11);
        $this->assertSame($originalContracts->toArray(), SponsorshipContract::query()->orderBy('id')->get()->toArray());
        foreach ($originalContracts as $contract) {
            $this->assertSame($instance->id, $contract->instance_id);
            $this->assertSame($sponsor->id, $contract->sponsor_account_id);
            $this->assertSame('2026-08-15', $contract->starts_at->toDateString());
            $this->assertContains($contract->expires_at->toDateString(), [
                '2027-06-15', '2028-06-15', '2029-06-15', '2030-06-15', '2031-06-15',
            ]);
            $this->assertSame(3750000, $contract->annual_amount);
        }
        $this->assertDatabaseCount('sponsorship_payments', 0);
        $this->assertSame(0, $club->account->balance);
    }

    #[Test]
    public function contracts_select_the_nearest_ranked_sponsor_in_the_same_game(): void
    {
        [$instance, $club] = $this->world();
        $localClub = Club::factory()->create(['instance_id' => $instance->id, 'rank' => 1]);
        $localEntity = GameEntity::factory()->create(['instance_id' => $instance->id, 'type' => GameEntityType::SPONSOR, 'rank' => 1]);
        $localSponsor = GameEntityAccount::factory()->create(['instance_id' => $instance->id, 'game_entity_id' => $localEntity->id]);
        $globalEntity = GameEntity::factory()->create(['instance_id' => $instance->id, 'type' => GameEntityType::SPONSOR, 'rank' => 18]);
        $globalSponsor = GameEntityAccount::factory()->create(['instance_id' => $instance->id, 'game_entity_id' => $globalEntity->id]);
        $foreignEntity = GameEntity::factory()->create(['type' => GameEntityType::SPONSOR, 'rank' => 20]);
        GameEntityAccount::factory()->create(['instance_id' => $foreignEntity->instance_id, 'game_entity_id' => $foreignEntity->id]);

        app(SponsorshipService::class)->createInitialContracts($instance);

        $this->assertDatabaseHas('sponsorship_contracts', ['club_id' => $club->id, 'sponsor_account_id' => $globalSponsor->id, 'annual_amount' => 25721250]);
        $this->assertDatabaseHas('sponsorship_contracts', ['club_id' => $localClub->id, 'sponsor_account_id' => $localSponsor->id, 'annual_amount' => 3750]);
    }

    #[Test]
    public function initial_contracts_only_use_sponsors_within_three_ranks_and_price_the_combined_rank(): void
    {
        [$instance, $club, $sponsor] = $this->world();
        $sponsor->gameEntity->update(['rank' => 16]);
        $service = app(SponsorshipService::class);

        $service->createInitialContracts($instance);
        $this->assertDatabaseCount('sponsorship_contracts', 0);

        $sponsor->gameEntity->update(['rank' => 17]);
        $service->createInitialContracts($instance);
        $this->assertDatabaseHas('sponsorship_contracts', ['club_id' => $club->id, 'sponsor_account_id' => $sponsor->id, 'annual_amount' => 21870000]);
    }

    #[Test]
    public function initial_contracts_enforce_the_rank_range_when_the_sponsor_is_stronger(): void
    {
        [$instance, $club, $sponsor] = $this->world();
        $club->forceFill(['rank' => 6])->save();
        $service = app(SponsorshipService::class);

        $service->createInitialContracts($instance);
        $this->assertDatabaseCount('sponsorship_contracts', 0);

        $club->forceFill(['rank' => 7])->save();
        $service->createInitialContracts($instance);
        $this->assertDatabaseHas('sponsorship_contracts', ['club_id' => $club->id, 'sponsor_account_id' => $sponsor->id, 'annual_amount' => 1920000]);
    }

    #[Test]
    public function season_start_pays_the_agreed_annual_amount_once_per_season(): void
    {
        [$instance, $club, $sponsor] = $this->world();
        $contract = $this->contract($instance, $club, $sponsor);
        $this->mock(SeasonService::class)->shouldReceive('start')->andReturnNull();
        $club->forceFill(['rank' => 1])->save();

        event(new SeasonStarted($instance));
        event(new SeasonStarted($instance));

        $this->assertSame(30000000, $club->account->fresh()->balance);
        $this->assertSame(30000000, $club->account->fresh()->future_balance);
        $this->assertSame(970000000, $sponsor->fresh()->balance);
        $this->assertSame(970000000, $sponsor->fresh()->future_balance);
        $this->assertDatabaseCount('sponsorship_payments', 1);
        $this->assertDatabaseHas('sponsorship_payments', [
            'sponsorship_contract_id' => $contract->id, 'season_id' => $instance->season_id, 'amount' => 30000000,
        ]);
        $transaction = FinanceTransactionEntity::query()->sole();
        $this->assertSame(EntityTransactionType::SPONSORSHIP, $transaction->event_type);
        $this->assertSame($contract->id, $transaction->event_id);
        $this->assertSame('2026-08-15', $transaction->transaction_date->toDateString());

        $season = Season::factory()->create(['instance_id' => $instance->id, 'start_date' => '2027-08-15', 'end_date' => '2028-06-15']);
        $instance->forceFill(['season_id' => $season->id, 'instance_date' => '2027-06-16'])->save();
        event(new SeasonStarted($instance));
        $this->assertSame(30000000, $club->account->fresh()->balance);
        $this->assertDatabaseCount('sponsorship_payments', 1);

        $instance->forceFill(['instance_date' => '2027-08-15'])->save();
        app(PayClubSponsorships::class)->handle(new NextDay($instance));

        $this->assertSame(60000000, $club->account->fresh()->balance);
        $this->assertSame(940000000, $sponsor->fresh()->balance);
        $this->assertDatabaseCount('sponsorship_payments', 2);
        $this->assertDatabaseCount('finance_transactions_entities', 2);
    }

    #[Test]
    public function the_first_seasons_calendar_start_pays_but_ordinary_days_do_not(): void
    {
        [$instance, $club, $sponsor] = $this->world('2026-08-14');
        $this->contract($instance, $club, $sponsor);
        $listener = app(PayClubSponsorships::class);

        $listener->handle(new NextDay($instance));
        $this->assertSame(0, $club->account->fresh()->balance);
        $instance->forceFill(['instance_date' => '2026-08-15'])->save();
        event(new NextDay($instance));
        $listener->handle(new NextDay($instance));

        $this->assertSame(30000000, $club->account->fresh()->balance);
        $this->assertDatabaseCount('sponsorship_payments', 1);
    }

    #[Test]
    public function expired_future_zero_value_and_other_instances_contracts_do_not_pay(): void
    {
        [$instance, $club, $sponsor] = $this->world();
        $this->contract($instance, $club, $sponsor, ['expires_at' => '2026-06-15']);
        $futureClub = Club::factory()->create(['instance_id' => $instance->id]);
        Account::factory()->create(['club_id' => $futureClub->id, 'balance' => 0]);
        $this->contract($instance, $futureClub, $sponsor, ['starts_at' => '2027-08-15']);
        $zeroClub = Club::factory()->create(['instance_id' => $instance->id]);
        Account::factory()->create(['club_id' => $zeroClub->id, 'balance' => 0]);
        $this->contract($instance, $zeroClub, $sponsor, ['annual_amount' => 0]);
        $foreignContract = SponsorshipContract::factory()->create();
        Account::factory()->create(['club_id' => $foreignContract->club_id, 'balance' => 0]);

        app(SponsorshipService::class)->payForSeason($instance);

        $this->assertSame(0, $club->account->fresh()->balance);
        $this->assertSame(1000000000, $sponsor->fresh()->balance);
        $this->assertDatabaseCount('sponsorship_payments', 0);
        $this->assertDatabaseCount('finance_transactions_entities', 0);
    }

    #[Test]
    public function contracts_pay_for_their_final_season_and_stop_after_its_last_day(): void
    {
        [$instance, $club, $sponsor] = $this->world();
        $this->contract($instance, $club, $sponsor, ['expires_at' => '2027-06-15']);
        $service = app(SponsorshipService::class);

        $service->payForSeason($instance);
        $instance->forceFill(['instance_date' => '2027-06-15'])->save();
        $service->payForSeason($instance);
        $season = Season::factory()->create(['instance_id' => $instance->id, 'start_date' => '2027-08-15', 'end_date' => '2028-06-15']);
        $instance->forceFill(['season_id' => $season->id, 'instance_date' => '2027-08-15'])->save();
        $service->payForSeason($instance);

        $this->assertSame(30000000, $club->account->fresh()->balance);
        $this->assertDatabaseCount('sponsorship_payments', 1);
    }

    /** @return array{Instance, Club, GameEntityAccount} */
    private function world(string $date = '2026-08-15'): array
    {
        $instance = Instance::factory()->create(['instance_date' => $date]);
        $season = Season::factory()->create(['instance_id' => $instance->id, 'start_date' => '2026-08-15', 'end_date' => '2027-06-15']);
        $club = Club::factory()->create(['instance_id' => $instance->id, 'rank' => 20]);
        Account::factory()->create(['club_id' => $club->id, 'balance' => 0, 'future_balance' => 0]);
        $entity = GameEntity::factory()->create(['instance_id' => $instance->id, 'type' => GameEntityType::SPONSOR, 'name' => 'Club Sponsor']);
        $sponsor = GameEntityAccount::factory()->create([
            'instance_id' => $instance->id, 'game_entity_id' => $entity->id,
            'balance' => 1000000000, 'future_balance' => 1000000000,
        ]);
        $instance->forceFill(['club_id' => $club->id, 'season_id' => $season->id])->save();
        app(GameContext::class)->set($instance->id, $season->id, $date);

        return [$instance, $club, $sponsor];
    }

    /** @param array<string, int|string> $attributes */
    private function contract(Instance $instance, Club $club, GameEntityAccount $sponsor, array $attributes = []): SponsorshipContract
    {
        return SponsorshipContract::factory()->create([
            'instance_id' => $instance->id, 'club_id' => $club->id, 'sponsor_account_id' => $sponsor->id,
            'annual_amount' => 30000000, ...$attributes,
        ]);
    }
}
