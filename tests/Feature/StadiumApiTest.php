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
