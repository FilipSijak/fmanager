<?php

namespace Tests\Feature;

use App\GameEntityType;
use App\Models\Account;
use App\Models\BaseData\BaseCommercialCategory;
use App\Models\Club;
use App\Models\Country;
use App\Models\GameEntity;
use App\Models\GameEntityAccount;
use App\Models\Instance;
use App\Models\Stadium;
use App\Models\StadiumStandConstruction;
use App\Services\CommercialService\CommercialVenueSize;
use App\Services\StadiumService\StadiumType;
use App\StadiumStandPosition;
use App\StadiumStandStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StadiumApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_the_managed_stadium_with_nested_resources(): void
    {
        [$instance, $stadium] = $this->createManagedStadium();

        $category = BaseCommercialCategory::query()->forceCreate([
            'slug' => 'bar',
            'name' => 'Bar',
            'description' => 'A bar',
        ]);
        $venue = $stadium->commercialVenues()->create([
            'instance_id' => $instance->id,
            'category_id' => $category->id,
            'size' => CommercialVenueSize::MEDIUM,
            'build_cost' => 120000,
        ]);
        $stand = $stadium->stands()->create([
            'position' => StadiumStandPosition::NORTH,
            'capacity' => 5000,
            'status' => StadiumStandStatus::ACTIVE,
        ]);
        $construction = StadiumStandConstruction::query()->create([
            'instance_id' => $instance->id,
            'stadium_id' => $stadium->id,
            'stadium_stand_id' => $stand->id,
            'target_capacity' => 6000,
            'capacity_increase' => 1000,
            'started_at' => '2026-09-15',
            'completes_at' => '2026-09-22',
        ]);

        $response = $this->apiRequest($instance)->getJson('/api/stadium');

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $stadium->id)
            ->assertJsonPath('data.name', $stadium->name)
            ->assertJsonPath('data.type', $stadium->type->value)
            ->assertJsonPath('data.stands.0.id', $stand->id)
            ->assertJsonPath('data.stands.0.construction.id', $construction->id)
            ->assertJsonPath('data.commercial_venues.0.id', $venue->id)
            ->assertJsonPath('data.commercial_venues.0.category.id', $category->id);
    }

    #[Test]
    public function it_returns_buildable_commercial_categories_for_the_managed_stadium(): void
    {
        [$instance, $stadium] = $this->createManagedStadium(StadiumType::LOCAL);

        $available = BaseCommercialCategory::query()->forceCreate([
            'slug' => 'bar',
            'name' => 'Bar',
            'is_active' => true,
        ]);
        $inactive = BaseCommercialCategory::query()->forceCreate([
            'slug' => 'shop',
            'name' => 'Shop',
            'is_active' => false,
        ]);
        $this->mapCategoryToType($available->id, StadiumType::LOCAL);
        $this->mapCategoryToType($inactive->id, StadiumType::LOCAL);

        $response = $this->apiRequest($instance)->getJson('/api/stadium/commercial-categories');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $available->id)
            ->assertJsonPath('data.0.slug', 'bar');
    }

    #[Test]
    public function it_builds_a_commercial_venue_and_returns_a_resource(): void
    {
        [$instance, $stadium] = $this->createManagedStadium(StadiumType::LOCAL);
        $category = BaseCommercialCategory::query()->forceCreate([
            'slug' => 'bar',
            'name' => 'Bar',
        ]);
        $this->mapCategoryToType($category->id, StadiumType::LOCAL);

        $response = $this->apiRequest($instance)->postJson('/api/stadium/construction', [
            'building_type' => 'commercial_venue',
            'category_id' => $category->id,
            'size' => CommercialVenueSize::LARGE->value,
            'payment_method' => 'cash',
            'length_years' => 0,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonPath('data.category.slug', 'bar')
            ->assertJsonPath('data.size', CommercialVenueSize::LARGE->value);

        $this->assertDatabaseHas('stadium_commercial_venues', [
            'stadium_id' => $stadium->id,
            'category_id' => $category->id,
        ]);
    }

    #[Test]
    public function it_does_not_expose_a_stadium_from_another_instance(): void
    {
        [$currentInstance] = $this->createManagedStadium();
        $otherInstance = Instance::factory()->create(['instance_hash' => 'other-instance']);

        $response = $this->apiRequest($currentInstance)->getJson('/api/stadium');

        $response->assertOk()->assertJsonPath('data.id', $currentInstance->club->stadium_id);
        $this->assertNotSame($otherInstance->id, $currentInstance->id);
    }

    #[Test]
    public function it_builds_a_commercial_venue_with_a_mortgage_and_installments(): void
    {
        [$instance, $stadium] = $this->createManagedStadium(StadiumType::LOCAL);
        $category = BaseCommercialCategory::query()->forceCreate(['slug' => 'bar', 'name' => 'Bar']);
        $this->mapCategoryToType($category->id, StadiumType::LOCAL);

        $response = $this->apiRequest($instance)->postJson('/api/stadium/construction', [
            'building_type' => 'commercial_venue',
            'category_id' => $category->id,
            'size' => CommercialVenueSize::LARGE->value,
            'payment_method' => 'mortgage',
            'length_years' => 2,
        ]);

        $response->assertOk();
        $venueId = (int) DB::table('stadium_commercial_venues')->where('stadium_id', $stadium->id)->value('id');
        $this->assertDatabaseHas('finance_entity_loans', [
            'stadium_commercial_venue_id' => $venueId,
            'principal' => 150000,
            'interest_amount' => 15000,
            'total_amount' => 165000,
            'installment_count' => 24,
        ]);
        $this->assertDatabaseCount('accounts_debt_lines_entities', 24);
        $account = Account::query()->where('club_id', $instance->club_id)->firstOrFail();
        $this->assertSame(1000000, $account->fresh()->balance);
        $this->assertSame(835000, $account->fresh()->future_balance);
    }

    #[Test]
    public function it_builds_a_stand_with_cash_and_deducts_the_construction_cost(): void
    {
        [$instance, $stadium] = $this->createManagedStadium(StadiumType::LOCAL);
        $this->seedStandConstructionData();
        $stadium->forceFill(['capacity' => 1000, 'active_capacity' => 1000])->saveQuietly();
        $stand = $stadium->stands()->create(['position' => StadiumStandPosition::NORTH, 'capacity' => 1000, 'status' => StadiumStandStatus::ACTIVE]);

        $response = $this->apiRequest($instance)->postJson('/api/stadium/construction', [
            'building_type' => 'stand',
            'stand_id' => $stand->id,
            'target_capacity' => 2000,
            'payment_method' => 'cash',
            'length_years' => 0,
        ]);

        $response->assertOk();
        $this->assertSame(625000, Account::query()->where('club_id', $instance->club_id)->firstOrFail()->balance);
        $this->assertDatabaseHas('stadium_stand_constructions', ['stadium_stand_id' => $stand->id, 'capacity_increase' => 1000]);
    }

    #[Test]
    public function it_builds_a_stand_with_a_mortgage_and_installments(): void
    {
        [$instance, $stadium] = $this->createManagedStadium(StadiumType::LOCAL);
        $this->seedStandConstructionData();
        $stadium->forceFill(['capacity' => 1000, 'active_capacity' => 1000])->saveQuietly();
        $stand = $stadium->stands()->create(['position' => StadiumStandPosition::NORTH, 'capacity' => 1000, 'status' => StadiumStandStatus::ACTIVE]);

        $response = $this->apiRequest($instance)->postJson('/api/stadium/construction', [
            'building_type' => 'stand',
            'stand_id' => $stand->id,
            'target_capacity' => 2000,
            'payment_method' => 'mortgage',
            'length_years' => 2,
        ]);

        $response->assertOk();
        $constructionId = (int) DB::table('stadium_stand_constructions')->where('stadium_stand_id', $stand->id)->value('id');
        $this->assertDatabaseHas('finance_entity_loans', ['stadium_stand_construction_id' => $constructionId, 'principal' => 375000, 'total_amount' => 412500]);
        $this->assertDatabaseCount('accounts_debt_lines_entities', 24);
        $account = Account::query()->where('club_id', $instance->club_id)->firstOrFail();
        $this->assertSame(1000000, $account->fresh()->balance);
        $this->assertSame(587500, $account->fresh()->future_balance);
    }

    #[Test]
    public function it_rolls_back_a_cash_venue_when_the_club_cannot_pay(): void
    {
        [$instance] = $this->createManagedStadium(StadiumType::LOCAL);
        $category = BaseCommercialCategory::query()->forceCreate(['slug' => 'bar', 'name' => 'Bar']);
        $this->mapCategoryToType($category->id, StadiumType::LOCAL);
        Account::query()->where('club_id', $instance->club_id)->update(['balance' => 1000, 'future_balance' => 1000]);

        $response = $this->apiRequest($instance)->postJson('/api/stadium/construction', [
            'building_type' => 'commercial_venue',
            'category_id' => $category->id,
            'size' => CommercialVenueSize::LARGE->value,
            'payment_method' => 'cash',
            'length_years' => 0,
        ]);

        $response->assertUnprocessable()->assertJsonPath('error', 'The club cannot afford this construction.');
        $this->assertDatabaseCount('stadium_commercial_venues', 0);
        $this->assertDatabaseCount('finance_transactions_entities', 0);
    }

    private function seedStandConstructionData(): void
    {
        DB::table('base_stadium_expansion_costs')->insert(['stadium_type' => StadiumType::LOCAL->value, 'cost_per_1000_seats' => 250000]);
        DB::table('base_stadium_stand_capacity_limits')->insert(['stadium_type' => StadiumType::LOCAL->value, 'position' => StadiumStandPosition::NORTH->value, 'maximum_capacity' => 7000]);
    }

    /**
     * @return array{0: Instance, 1: Stadium}
     */
    private function createManagedStadium(StadiumType $type = StadiumType::LOCAL): array
    {
        $instance = Instance::factory()->create([
            'instance_hash' => 'current-instance',
            'instance_date' => '2026-09-15',
        ]);
        Country::query()->forceCreate([
            'code' => 'GBR',
            'name' => 'United Kingdom',
            'ranking' => 100,
            'population' => 60000000,
        ]);
        $stadium = Stadium::factory()->create([
            'instance_id' => $instance->id,
            'type' => $type,
            'commercial_limit' => 3,
            'country_code' => 'GBR',
        ]);
        $club = Club::factory()->create([
            'instance_id' => $instance->id,
            'stadium_id' => $stadium->id,
        ]);
        $instance->forceFill(['club_id' => $club->id])->saveQuietly();
        Account::factory()->create(['club_id' => $club->id, 'balance' => 1_000_000, 'future_balance' => 1_000_000]);
        $bank = GameEntity::factory()->create(['instance_id' => $instance->id, 'type' => GameEntityType::BANK]);
        GameEntityAccount::factory()->create(['game_entity_id' => $bank->id, 'instance_id' => $instance->id, 'balance' => 50_000_000_000, 'future_balance' => 50_000_000_000]);

        return [$instance->fresh(), $stadium->fresh()];
    }

    private function mapCategoryToType(int $categoryId, StadiumType $type): void
    {
        DB::table('base_commercial_category_stadium_type')->insert([
            'category_id' => $categoryId,
            'stadium_type' => $type->value,
        ]);

        foreach (CommercialVenueSize::cases() as $size) {
            DB::table('base_commercial_venue_costs')->insert([
                'category_id' => $categoryId,
                'size' => $size->value,
                'base_cost' => 100000,
            ]);
        }
    }

    private function apiRequest(Instance $instance): self
    {
        return $this
            ->actingAs($instance->user)
            ->withHeaders(['instanceHash' => $instance->instance_hash]);
    }
}
